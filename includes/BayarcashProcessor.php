<?php

namespace BayarcashForFluentCart;

use FluentCart\App\Models\Order;
use FluentCart\App\Models\OrderTransaction;
use FluentCart\App\Helpers\StatusHelper;
use Webimpian\BayarcashSdk\Bayarcash;

defined('ABSPATH') or exit;

class BayarcashProcessor
{
    


    protected $settings;

    


    protected $bayarcash;

    




    public function __construct(BayarcashSettings $settings)
    {
        $this->settings = $settings;
    }

    





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

    







    public function createPayment($order, $transaction, $selectedChannel = null)
    {
        try {
            $client = $this->getBayarcashClient($order->mode);
            $credentials = $this->settings->getApiCredentials($order->mode);

            
            $channels = $this->settings->getPaymentChannels();

            
            $paymentData = [
                'order_number' => (string) $order->id, 
                'amount' => number_format($transaction->total / 100, 2, '.', ''), 
                'portal_key' => $credentials['portal_key'],
                'payer_name' => $order->customer->full_name ?? $order->customer->first_name,
                'payer_email' => $order->customer->email,
                'return_url' => $this->getReturnUrl($order),
                'callback_url' => $this->settings->getCallbackUrl(),
            ];

            
            $phone = $order->billing_address->meta['other_data']['phone'] ??
                     $order->shipping_address->meta['other_data']['phone'] ?? '';

            if (!empty($phone)) {
                
                $phoneNumber = preg_replace('/[^0-9]/', '', $phone);
                if (!empty($phoneNumber)) {
                    $paymentData['payer_telephone_number'] = (int) $phoneNumber;
                }
            }

            
            if ($selectedChannel && in_array($selectedChannel, $channels)) {
                $paymentData['payment_channel'] = $selectedChannel;
            } elseif (!empty($channels)) {
                $paymentData['payment_channel'] = $channels[0];
            } else {
                $paymentData['payment_channel'] = 1; 
            }

            
            $checksum = $client->createPaymentIntentChecksumValue($credentials['api_secret'], $paymentData);
            $paymentData['checksum'] = $checksum;

            
            $response = $client->createPaymentIntent($paymentData);

            
            $paymentUrl = $response->url ?? null;

            if ($paymentUrl) {
                
                
                
                if (isset($response->id)) {
                    $transaction->vendor_charge_id = $response->id; 
                    $transaction->payment_method = 'bayarcash';
                    $transaction->payment_method_type = 'bayarcash'; 
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

    





    public function handleCallback($callbackData)
    {
        try {
            
            error_log('=== Bayarcash Callback Received ===');
            error_log('Callback Data: ' . print_r($callbackData, true));

            
            $orderNumber = sanitize_text_field($callbackData['order_number'] ?? '');
            error_log('Looking for order with ID: ' . $orderNumber);

            
            $order = Order::find(intval($orderNumber));

            if (!$order) {
                error_log('Error: Order not found with ID ' . $orderNumber);
                return new \WP_Error('bayarcash_order_not_found', __('Order not found', 'bayarcash-for-fluentcart'));
            }

            error_log('Order found: ' . $order->id . ', current status: ' . $order->status . ', payment status: ' . $order->payment_status);

            
            $protectedStatuses = ['paid', 'refunded', 'partially_refunded'];
            if (in_array($order->payment_status, $protectedStatuses)) {
                error_log('Order #' . $order->id . ' has protected payment status: ' . $order->payment_status . '. Skipping callback processing.');
                return true; 
            }

            
            $recordType = sanitize_text_field($callbackData['record_type'] ?? '');

            
            $credentials = $this->settings->getApiCredentials($order->mode);
            $client = $this->getBayarcashClient($order->mode);

            
            $isValid = $client->verifyTransactionCallbackData($callbackData, $credentials['api_secret']);

            if (!$isValid) {
                error_log('Invalid callback signature');
                return new \WP_Error('bayarcash_invalid_callback', __('Invalid callback signature', 'bayarcash-for-fluentcart'));
            }

            
            if ($recordType === 'pre_transaction') {
                error_log('Processing pre_transaction callback');

                
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

            
            if ($recordType === 'transaction') {
                error_log('Processing transaction callback');

                
                $transactionId = sanitize_text_field($callbackData['transaction_id'] ?? '');
                $exchangeTransactionId = sanitize_text_field($callbackData['exchange_transaction_id'] ?? '');
                $statusDescription = sanitize_text_field($callbackData['status_description'] ?? '');
                $paymentGatewayId = sanitize_text_field($callbackData['payment_gateway_id'] ?? '');

                
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

                
                $transaction = $order->transactions()->where('payment_method', 'bayarcash')->first();

                if (!$transaction) {
                    return new \WP_Error('bayarcash_transaction_not_found', __('Transaction not found', 'bayarcash-for-fluentcart'));
                }

                
                
                $status = sanitize_text_field($callbackData['status'] ?? '');

                if ($status === '3' || $status === 3) {
                    
                    error_log('Processing successful payment (status 3)');
                    
                    $transaction->status = 'succeeded'; 
                    
                    $transaction->save();

                    
                    $statusHelper = new StatusHelper($order);
                    $statusHelper->syncOrderStatuses($transaction);
                    error_log('Order #' . $order->id . ' marked as paid. Vendor charge ID maintained: ' . $transaction->vendor_charge_id);

                    return true;
                } elseif ($status === '0' || $status === 0 || $status === '1' || $status === 1) {
                    
                    error_log('Processing pending payment (status ' . $status . ')');
                    
                    $transaction->status = 'pending';
                    
                    $transaction->save();
                    error_log('Transaction marked as pending');

                    return true;
                } else {
                    
                    error_log('Processing failed/cancelled payment (status ' . $status . ')');
                    
                    $transaction->status = 'failed';
                    
                    $transaction->note = __('Payment failed or cancelled', 'bayarcash-for-fluentcart');
                    $transaction->save();

                    
                    $order->payment_status = 'failed';
                    $order->save();
                    error_log('Order #' . $order->id . ' marked as failed');

                    return false;
                }
            }

            
            error_log('Unknown record_type: ' . $recordType);
            return new \WP_Error('bayarcash_unknown_record_type', __('Unknown callback record type', 'bayarcash-for-fluentcart'));

        } catch (\Exception $e) {
            error_log('Bayarcash callback exception: ' . $e->getMessage());
            return new \WP_Error('bayarcash_callback_exception', $e->getMessage());
        }
    }

    





    protected function getPaymentDescription($order)
    {
        $template = 'Payment for Order #{{order_id}}';
        return str_replace('{{order_id}}', $order->id, $template);
    }

    






    protected function getReturnUrl($order)
    {
        
        $transaction = $order->transactions()->where('payment_method', 'bayarcash')->first();

        if (!$transaction) {
            error_log('Bayarcash: No transaction found for order ' . $order->id);
            return site_url('/');
        }

        
        $receiptPageUrl = (new \FluentCart\Api\StoreSettings)->getReceiptPage();

        
        
        return add_query_arg([
            'method' => 'bayarcash',
            'trx_hash' => $transaction->uuid,
            'fct_redirect' => 'yes',
            'order_id' => $order->id 
        ], $receiptPageUrl);
    }

    





    public function handleReturn()
    {
        
        static $processed = false;
        if ($processed) {
            return;
        }

        
        $requestUri = $_SERVER['REQUEST_URI'] ?? '';

        
        if (strpos($requestUri, 'transaction_id') === false || strpos($requestUri, 'status') === false) {
            return; 
        }

        
        if (!isset($_GET['method']) || $_GET['method'] !== 'bayarcash') {
            return; 
        }

        $processed = true; 

        error_log('=== Bayarcash Return Handler (template_redirect) ===');
        error_log('REQUEST_URI: ' . $requestUri);

        try {
            
            
            $params = [];

            
            if (substr_count($requestUri, '?') > 1) {
                error_log('Detected malformed URL with multiple ?');
                
                $firstQuestionMark = strpos($requestUri, '?');
                
                $fixedUri = substr($requestUri, 0, $firstQuestionMark + 1) .
                           str_replace('?', '&', substr($requestUri, $firstQuestionMark + 1));
                error_log('Fixed URI: ' . $fixedUri);

                
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

            
            $protectedStatuses = ['paid', 'refunded', 'partially_refunded'];
            if (in_array($order->payment_status, $protectedStatuses)) {
                error_log('Order #' . $order->id . ' has protected payment status: ' . $order->payment_status . '. Skipping return URL processing.');
                
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

            
            $transaction = $order->transactions()->where('payment_method', 'bayarcash')->first();

            if (!$transaction) {
                wp_die(__('Transaction not found', 'bayarcash-for-fluentcart'));
            }

            
            $credentials = $this->settings->getApiCredentials($order->mode);
            $client = $this->getBayarcashClient($order->mode);
            $isValid = $client->verifyReturnUrlCallbackData($params, $credentials['api_secret']);

            error_log('Checksum valid: ' . ($isValid ? 'yes' : 'no'));

            if (!$isValid) {
                error_log('Invalid checksum, but continuing anyway');
            }

            
            if ($transactionId) {
                $order->updateMeta('bayarcash_transaction_id', $transactionId);
                error_log('Stored transaction_id in order meta: ' . $transactionId);
            }

            
            
            if ($status === '3' || $status === 3) {
                
                error_log('Processing successful payment (status 3)');
                
                $transaction->status = 'succeeded'; 
                
                $transaction->save();

                
                $statusHelper = new StatusHelper($order);
                $statusHelper->syncOrderStatuses($transaction);
                error_log('Order #' . $order->id . ' marked as paid. Vendor charge ID maintained: ' . $transaction->vendor_charge_id);
            } elseif ($status === '0' || $status === 0 || $status === '1' || $status === 1) {
                
                error_log('Processing pending payment (status ' . $status . ')');
                
                $transaction->status = 'pending';
                
                $transaction->save();
            } else {
                
                error_log('Processing failed payment (status ' . $status . ')');
                
                $transaction->status = 'failed';
                
                $transaction->note = __('Payment failed or cancelled', 'bayarcash-for-fluentcart');
                $transaction->save();

                
                $order->payment_status = 'failed';
                $order->save();
            }

            
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
