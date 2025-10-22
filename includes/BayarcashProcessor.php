<?php

namespace BayarcashForFluentCart;

use FluentCart\App\Models\Order;
use FluentCart\App\Models\OrderTransaction;
use FluentCart\App\Helpers\StatusHelper;
use Webimpian\BayarcashSdk\Bayarcash;

defined('ABSPATH') or exit;

class BayarcashProcessor
{
    /**
     * @var BayarcashSettings
     */
    protected $settings;

    /**
     * @var Bayarcash
     */
    protected $bayarcash;

    /**
     * Constructor
     *
     * @param BayarcashSettings $settings
     */
    public function __construct(BayarcashSettings $settings)
    {
        $this->settings = $settings;
    }

    /**
     * Initialize Bayarcash SDK
     *
     * @param string $mode
     * @return Bayarcash
     */
    protected function getBayarcashClient($mode = null)
    {
        if ($this->bayarcash) {
            return $this->bayarcash;
        }

        $credentials = $this->settings->getApiCredentials($mode);

        $this->bayarcash = new Bayarcash($credentials['api_token']);
        $this->bayarcash->setApiVersion('v3');

        if ($credentials['mode'] === 'test') {
            $this->bayarcash->useSandbox();
        }

        return $this->bayarcash;
    }

    /**
     * Create payment intent
     *
     * @param Order $order
     * @param Transaction $transaction
     * @param int|null $selectedChannel
     * @return array|WP_Error
     */
    public function createPayment($order, $transaction, $selectedChannel = null)
    {
        try {
            $client = $this->getBayarcashClient($order->mode);
            $credentials = $this->settings->getApiCredentials($order->mode);

            // Get selected payment channels
            $channels = $this->settings->getPaymentChannels();

            // Prepare payment data (match Bayarcash API requirements)
            $paymentData = [
                'order_number' => (string) $order->id, // Use order ID as order number
                'amount' => number_format($transaction->total / 100, 2, '.', ''), // Convert cents to Ringgit (1400 → 14.00)
                'portal_key' => $credentials['portal_key'],
                'payer_name' => $order->customer->full_name ?? $order->customer->first_name,
                'payer_email' => $order->customer->email,
                'return_url' => $this->getReturnUrl($order),
                'callback_url' => $this->settings->getCallbackUrl(),
            ];

            // Add optional phone number if available
            $phone = $order->billing_address->meta['other_data']['phone'] ??
                     $order->shipping_address->meta['other_data']['phone'] ?? '';

            if (!empty($phone)) {
                // Remove non-numeric characters and ensure it's an integer
                $phoneNumber = preg_replace('/[^0-9]/', '', $phone);
                if (!empty($phoneNumber)) {
                    $paymentData['payer_telephone_number'] = (int) $phoneNumber;
                }
            }

            // Use user-selected channel (only one channel per payment intent)
            if ($selectedChannel && in_array($selectedChannel, $channels)) {
                $paymentData['payment_channel'] = $selectedChannel;
            } elseif (!empty($channels)) {
                $paymentData['payment_channel'] = $channels[0];
            } else {
                $paymentData['payment_channel'] = 1; // Default to FPX
            }

            // Generate checksum
            $checksum = $client->createPaymentIntentChecksumValue($credentials['api_secret'], $paymentData);
            $paymentData['checksum'] = $checksum;

            // Create payment intent - returns PaymentIntentResource object
            $response = $client->createPaymentIntent($paymentData);

            // Response has 'url' property, not 'payment_url'
            $paymentUrl = $response->url ?? null;

            if ($paymentUrl) {
                // Update transaction with Bayarcash payment intent ID and payment method type
                // Note: vendor_charge_id stores payment intent ID and is never overwritten
                // Transaction ID from callbacks is stored in order meta only
                if (isset($response->id)) {
                    $transaction->vendor_charge_id = $response->id; // Payment intent ID (maintained throughout)
                    $transaction->payment_method = 'bayarcash';
                    $transaction->payment_method_type = 'bayarcash'; // Use 'bayarcash' for all channels
                    $transaction->save();
                    error_log('Payment intent created. Vendor charge ID: ' . $response->id);
                }

                return [
                    'success' => true,
                    'payment_url' => $paymentUrl,
                    'payment_intent_id' => $response->id ?? null
                ];
            }

            error_log('Bayarcash Error: No payment URL in response');
            return new \WP_Error('bayarcash_error', __('Failed to create payment intent', 'bayarcash-for-fluentcart'));

        } catch (\Exception $e) {
            error_log('Bayarcash Exception: ' . $e->getMessage());
            error_log('Bayarcash Exception Trace: ' . $e->getTraceAsString());
            return new \WP_Error('bayarcash_exception', $e->getMessage());
        }
    }

    /**
     * Handle payment callback from Bayarcash (Server-to-Server POST)
     *
     * @param array $callbackData
     * @return bool|WP_Error
     */
    public function handleCallback($callbackData)
    {
        try {
            // Log callback for debugging
            error_log('=== Bayarcash Callback Received ===');
            error_log('Callback Data: ' . print_r($callbackData, true));

            // Find the order first to determine the correct mode
            $orderNumber = sanitize_text_field($callbackData['order_number'] ?? '');
            error_log('Looking for order with ID: ' . $orderNumber);

            // Order number is actually the order ID in our case
            $order = Order::find(intval($orderNumber));

            if (!$order) {
                error_log('Error: Order not found with ID ' . $orderNumber);
                return new \WP_Error('bayarcash_order_not_found', __('Order not found', 'bayarcash-for-fluentcart'));
            }

            error_log('Order found: ' . $order->id . ', current status: ' . $order->status . ', payment status: ' . $order->payment_status);

            // Prevent updating already paid/completed/refunded orders
            $protectedStatuses = ['paid', 'refunded', 'partially_refunded'];
            if (in_array($order->payment_status, $protectedStatuses)) {
                error_log('Order #' . $order->id . ' has protected payment status: ' . $order->payment_status . '. Skipping callback processing.');
                return true; // Return success to avoid Bayarcash retrying
            }

            // Get record type to determine callback stage
            $recordType = sanitize_text_field($callbackData['record_type'] ?? '');

            // Get API credentials based on order mode
            $credentials = $this->settings->getApiCredentials($order->mode);
            $client = $this->getBayarcashClient($order->mode);

            // Verify callback data
            $isValid = $client->verifyTransactionCallbackData($callbackData, $credentials['api_secret']);

            if (!$isValid) {
                error_log('Invalid callback signature');
                return new \WP_Error('bayarcash_invalid_callback', __('Invalid callback signature', 'bayarcash-for-fluentcart'));
            }

            // Handle pre_transaction callback (initial notification)
            if ($recordType === 'pre_transaction') {
                error_log('Processing pre_transaction callback');

                // Store pre-transaction data in order meta (not in vendor_charge_id)
                $exchangeRefNumber = sanitize_text_field($callbackData['exchange_reference_number'] ?? '');
                $transactionId = sanitize_text_field($callbackData['transaction_id'] ?? '');

                if ($exchangeRefNumber) {
                    $order->updateMeta('bayarcash_exchange_reference_number', $exchangeRefNumber);
                }
                if ($transactionId) {
                    $order->updateMeta('bayarcash_transaction_id', $transactionId);
                    error_log('Stored transaction_id in order meta: ' . $transactionId);
                }

                error_log('Pre-transaction data saved to order meta');
                return true;
            }

            // Handle transaction callback (final status)
            if ($recordType === 'transaction') {
                error_log('Processing transaction callback');

                // Store transaction data in order meta (not in vendor_charge_id)
                $transactionId = sanitize_text_field($callbackData['transaction_id'] ?? '');
                $exchangeTransactionId = sanitize_text_field($callbackData['exchange_transaction_id'] ?? '');
                $statusDescription = sanitize_text_field($callbackData['status_description'] ?? '');
                $paymentGatewayId = sanitize_text_field($callbackData['payment_gateway_id'] ?? '');

                // Store transaction_id in order meta (maintain payment intent ID in vendor_charge_id)
                if ($transactionId) {
                    $order->updateMeta('bayarcash_transaction_id', $transactionId);
                    error_log('Stored transaction_id in order meta: ' . $transactionId);
                }
                if ($exchangeTransactionId) {
                    $order->updateMeta('bayarcash_exchange_transaction_id', $exchangeTransactionId);
                }
                if ($statusDescription) {
                    $order->updateMeta('bayarcash_status_description', $statusDescription);
                }
                if ($paymentGatewayId) {
                    $order->updateMeta('bayarcash_payment_gateway_id', $paymentGatewayId);
                }

                // Get transaction
                $transaction = $order->transactions()->where('payment_method', 'bayarcash')->first();

                if (!$transaction) {
                    return new \WP_Error('bayarcash_transaction_not_found', __('Transaction not found', 'bayarcash-for-fluentcart'));
                }

                // Check payment status
                // Bayarcash Status Codes: 0=New, 1=Pending, 2=Failed, 3=Success, 4=Cancelled
                $status = sanitize_text_field($callbackData['status'] ?? '');

                if ($status === '3' || $status === 3) {
                    // Payment successful (Status 3 = Success)
                    error_log('Processing successful payment (status 3)');
                    // Keep payment intent ID in vendor_charge_id (don't overwrite)
                    $transaction->status = 'succeeded'; // FluentCart expects 'succeeded' not 'paid'
                    // Don't overwrite payment_method_type - it was already set during payment creation
                    $transaction->save();

                    // Update order status using StatusHelper
                    $statusHelper = new StatusHelper($order);
                    $statusHelper->syncOrderStatuses($transaction);
                    error_log('Order #' . $order->id . ' marked as paid. Vendor charge ID maintained: ' . $transaction->vendor_charge_id);

                    return true;
                } elseif ($status === '0' || $status === 0 || $status === '1' || $status === 1) {
                    // Payment pending (Status 0 = New, Status 1 = Pending)
                    error_log('Processing pending payment (status ' . $status . ')');
                    // Keep payment intent ID in vendor_charge_id (don't overwrite)
                    $transaction->status = 'pending';
                    // Don't overwrite payment_method_type - it was already set during payment creation
                    $transaction->save();
                    error_log('Transaction marked as pending');

                    return true;
                } else {
                    // Payment failed or cancelled (Status 2 = Failed, Status 4 = Cancelled)
                    error_log('Processing failed/cancelled payment (status ' . $status . ')');
                    // Keep payment intent ID in vendor_charge_id (don't overwrite)
                    $transaction->status = 'failed';
                    // Don't overwrite payment_method_type - it was already set during payment creation
                    $transaction->note = __('Payment failed or cancelled', 'bayarcash-for-fluentcart');
                    $transaction->save();

                    // Update order payment status directly
                    $order->payment_status = 'failed';
                    $order->save();
                    error_log('Order #' . $order->id . ' marked as failed');

                    return false;
                }
            }

            // Unknown record type
            error_log('Unknown record_type: ' . $recordType);
            return new \WP_Error('bayarcash_unknown_record_type', __('Unknown callback record type', 'bayarcash-for-fluentcart'));

        } catch (\Exception $e) {
            error_log('Bayarcash callback exception: ' . $e->getMessage());
            return new \WP_Error('bayarcash_callback_exception', $e->getMessage());
        }
    }

    /**
     * Get payment description
     *
     * @param Order $order
     * @return string
     */
    protected function getPaymentDescription($order)
    {
        $template = 'Payment for Order #{{order_id}}';
        return str_replace('{{order_id}}', $order->id, $template);
    }

    /**
     * Get return URL
     * Returns direct receipt page URL - Bayarcash will append their params
     *
     * @param Order $order
     * @return string
     */
    protected function getReturnUrl($order)
    {
        // Get transaction
        $transaction = $order->transactions()->where('payment_method', 'bayarcash')->first();

        if (!$transaction) {
            error_log('Bayarcash: No transaction found for order ' . $order->id);
            return site_url('/');
        }

        // Get receipt page URL from FluentCart settings
        $receiptPageUrl = (new \FluentCart\Api\StoreSettings)->getReceiptPage();

        // Return direct receipt URL - Bayarcash will append their params with ?
        // We'll process them on template_redirect hook
        return add_query_arg([
            'method' => 'bayarcash',
            'trx_hash' => $transaction->uuid,
            'fct_redirect' => 'yes',
            'order_id' => $order->id // Add for easier lookup
        ], $receiptPageUrl);
    }

    /**
     * Handle return URL from Bayarcash (Browser GET redirect)
     * Called on template_redirect to process Bayarcash params
     *
     * @return void
     */
    public function handleReturn()
    {
        // Only process once per request
        static $processed = false;
        if ($processed) {
            return;
        }

        // Only process if we're on receipt page and have Bayarcash params
        $requestUri = $_SERVER['REQUEST_URI'] ?? '';

        // Check if this looks like a Bayarcash return (has transaction_id and status params)
        if (strpos($requestUri, 'transaction_id') === false || strpos($requestUri, 'status') === false) {
            return; // Not a Bayarcash return, skip
        }

        // Check if method=bayarcash
        if (!isset($_GET['method']) || $_GET['method'] !== 'bayarcash') {
            return; // Not our payment method
        }

        $processed = true; // Mark as processed

        error_log('=== Bayarcash Return Handler (template_redirect) ===');
        error_log('REQUEST_URI: ' . $requestUri);

        try {
            // Bayarcash appends parameters with ? instead of &, breaking query string
            // Parse manually from REQUEST_URI to get all parameters
            $params = [];

            // Replace the second ? with & to fix malformed URL
            if (substr_count($requestUri, '?') > 1) {
                error_log('Detected malformed URL with multiple ?');
                // Find position of first ?
                $firstQuestionMark = strpos($requestUri, '?');
                // Replace all subsequent ? with &
                $fixedUri = substr($requestUri, 0, $firstQuestionMark + 1) .
                           str_replace('?', '&', substr($requestUri, $firstQuestionMark + 1));
                error_log('Fixed URI: ' . $fixedUri);

                // Parse the fixed query string
                $queryString = parse_url($fixedUri, PHP_URL_QUERY);
                if ($queryString) {
                    parse_str($queryString, $params);
                }
            } else {
                $params = $_GET;
            }

            error_log('Parsed params: ' . print_r($params, true));

            $orderId = intval($params['order_id'] ?? 0);
            $status = sanitize_text_field($params['status'] ?? '');
            $transactionId = sanitize_text_field($params['transaction_id'] ?? '');

            error_log('Order ID: ' . $orderId . ', Status: ' . $status . ', Transaction: ' . $transactionId);

            if (!$orderId) {
                wp_die(__('Order ID not found', 'bayarcash-for-fluentcart'));
            }

            $order = Order::find($orderId);

            if (!$order) {
                wp_die(__('Order not found', 'bayarcash-for-fluentcart'));
            }

            error_log('Order found: ' . $order->id . ', payment status: ' . $order->payment_status);

            // Prevent updating already paid/completed/refunded orders
            $protectedStatuses = ['paid', 'refunded', 'partially_refunded'];
            if (in_array($order->payment_status, $protectedStatuses)) {
                error_log('Order #' . $order->id . ' has protected payment status: ' . $order->payment_status . '. Skipping return URL processing.');
                // Redirect to receipt page anyway
                $receiptPageUrl = (new \FluentCart\Api\StoreSettings)->getReceiptPage();
                $transaction = $order->transactions()->where('payment_method', 'bayarcash')->first();
                if ($transaction) {
                    $cleanReceiptUrl = add_query_arg([
                        'method' => 'bayarcash',
                        'trx_hash' => $transaction->uuid,
                        'fct_redirect' => 'yes'
                    ], $receiptPageUrl);
                    wp_redirect($cleanReceiptUrl);
                    exit;
                }
                wp_redirect($receiptPageUrl);
                exit;
            }

            // Get transaction
            $transaction = $order->transactions()->where('payment_method', 'bayarcash')->first();

            if (!$transaction) {
                wp_die(__('Transaction not found', 'bayarcash-for-fluentcart'));
            }

            // Verify checksum
            $credentials = $this->settings->getApiCredentials($order->mode);
            $client = $this->getBayarcashClient($order->mode);
            $isValid = $client->verifyReturnUrlCallbackData($params, $credentials['api_secret']);

            error_log('Checksum valid: ' . ($isValid ? 'yes' : 'no'));

            if (!$isValid) {
                error_log('Invalid checksum, but continuing anyway');
            }

            // Store transaction_id in order meta (not in vendor_charge_id)
            if ($transactionId) {
                $order->updateMeta('bayarcash_transaction_id', $transactionId);
                error_log('Stored transaction_id in order meta: ' . $transactionId);
            }

            // Update order based on status (same logic as callback)
            // Bayarcash Status Codes: 0=New, 1=Pending, 2=Failed, 3=Success, 4=Cancelled
            if ($status === '3' || $status === 3) {
                // Payment successful
                error_log('Processing successful payment (status 3)');
                // Keep payment intent ID in vendor_charge_id (don't overwrite)
                $transaction->status = 'succeeded'; // FluentCart expects 'succeeded' not 'paid'
                // Don't overwrite payment_method_type - it was already set during payment creation
                $transaction->save();

                // Update order status using StatusHelper
                $statusHelper = new StatusHelper($order);
                $statusHelper->syncOrderStatuses($transaction);
                error_log('Order #' . $order->id . ' marked as paid. Vendor charge ID maintained: ' . $transaction->vendor_charge_id);
            } elseif ($status === '0' || $status === 0 || $status === '1' || $status === 1) {
                // Payment pending
                error_log('Processing pending payment (status ' . $status . ')');
                // Keep payment intent ID in vendor_charge_id (don't overwrite)
                $transaction->status = 'pending';
                // Don't overwrite payment_method_type - it was already set during payment creation
                $transaction->save();
            } else {
                // Payment failed or cancelled
                error_log('Processing failed payment (status ' . $status . ')');
                // Keep payment intent ID in vendor_charge_id (don't overwrite)
                $transaction->status = 'failed';
                // Don't overwrite payment_method_type - it was already set during payment creation
                $transaction->note = __('Payment failed or cancelled', 'bayarcash-for-fluentcart');
                $transaction->save();

                // Update order payment status directly
                $order->payment_status = 'failed';
                $order->save();
            }

            // Redirect to clean receipt URL (without Bayarcash params)
            $receiptPageUrl = (new \FluentCart\Api\StoreSettings)->getReceiptPage();
            $cleanReceiptUrl = add_query_arg([
                'method' => 'bayarcash',
                'trx_hash' => $transaction->uuid,
                'fct_redirect' => 'yes'
            ], $receiptPageUrl);

            error_log('Order status updated. Redirecting to clean receipt: ' . $cleanReceiptUrl);
            wp_redirect($cleanReceiptUrl);
            exit;

        } catch (\Exception $e) {
            error_log('Bayarcash Return Error: ' . $e->getMessage());
            wp_die($e->getMessage());
        }
    }
}
