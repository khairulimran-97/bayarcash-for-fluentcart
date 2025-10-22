<?php

namespace BayarcashForFluentCart;

use FluentCart\App\Models\Order;
use FluentCart\App\Models\Transaction;
use FluentCart\App\Services\PaymentGateways\StatusHelper;
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
     * @return array|WP_Error
     */
    public function createPayment($order, $transaction)
    {
        try {
            $client = $this->getBayarcashClient($order->mode);
            $credentials = $this->settings->getApiCredentials($order->mode);

            // Prepare payment data
            $paymentData = [
                'order_number' => $order->order_number,
                'amount' => number_format($transaction->payment_total, 2, '.', ''),
                'portal_key' => $credentials['portal_key'],
                'payer_name' => $order->customer->name,
                'payer_email' => $order->customer->email,
                'payer_telephone' => $order->customer->phone ?? '',
                'transaction_description' => $this->getPaymentDescription($order),
                'return_url' => $this->getReturnUrl($order),
                'callback_url' => $this->settings->getWebhookUrl(),
            ];

            // Generate checksum
            $checksum = $client->createPaymentIntenChecksumValue($paymentData, $credentials['api_secret']);
            $paymentData['checksum_value'] = $checksum;

            // Create payment intent
            $response = $client->createPaymentIntent($paymentData);

            if (isset($response['record']['payment_url'])) {
                // Update transaction with Bayarcash reference
                if (isset($response['record']['order_number'])) {
                    $transaction->charge_id = $response['record']['order_number'];
                    $transaction->save();
                }

                return [
                    'success' => true,
                    'payment_url' => $response['record']['payment_url'],
                    'order_number' => $response['record']['order_number'] ?? null
                ];
            }

            return new \WP_Error('bayarcash_error', __('Failed to create payment intent', 'bayarcash-for-fluentcart'));

        } catch (\Exception $e) {
            return new \WP_Error('bayarcash_exception', $e->getMessage());
        }
    }

    /**
     * Handle payment callback from Bayarcash
     *
     * @param array $callbackData
     * @return bool|WP_Error
     */
    public function handleCallback($callbackData)
    {
        try {
            // Get API secret for verification
            $mode = isset($callbackData['fpx_debitAuthCode']) && $callbackData['fpx_debitAuthCode'] !== '00' ? 'test' : 'live';
            $credentials = $this->settings->getApiCredentials($mode);
            $client = $this->getBayarcashClient($mode);

            // Verify callback data
            $isValid = $client->verifyTransactionCallbackData($callbackData, $credentials['api_secret']);

            if (!$isValid) {
                return new \WP_Error('bayarcash_invalid_callback', __('Invalid callback signature', 'bayarcash-for-fluentcart'));
            }

            // Find the order by order number
            $orderNumber = sanitize_text_field($callbackData['order_number'] ?? '');
            $order = Order::where('order_number', $orderNumber)->first();

            if (!$order) {
                return new \WP_Error('bayarcash_order_not_found', __('Order not found', 'bayarcash-for-fluentcart'));
            }

            // Get transaction
            $transaction = $order->transactions()->where('payment_method', 'bayarcash')->first();

            if (!$transaction) {
                return new \WP_Error('bayarcash_transaction_not_found', __('Transaction not found', 'bayarcash-for-fluentcart'));
            }

            // Check payment status
            $status = sanitize_text_field($callbackData['record_status'] ?? '');
            $transactionId = sanitize_text_field($callbackData['transaction_id'] ?? '');

            if ($status === '1' || $status === 1) {
                // Payment successful
                $transaction->charge_id = $transactionId;
                $transaction->status = 'paid';
                $transaction->save();

                // Update order status
                StatusHelper::updateOrderStatus($order->id, 'paid');

                return true;
            } elseif ($status === '3' || $status === 3) {
                // Payment pending
                $transaction->status = 'pending';
                $transaction->save();

                return true;
            } else {
                // Payment failed
                $transaction->status = 'failed';
                $transaction->note = __('Payment failed or cancelled', 'bayarcash-for-fluentcart');
                $transaction->save();

                StatusHelper::updateOrderStatus($order->id, 'failed');

                return false;
            }

        } catch (\Exception $e) {
            return new \WP_Error('bayarcash_callback_exception', $e->getMessage());
        }
    }

    /**
     * Handle return URL callback
     *
     * @param array $returnData
     * @return array
     */
    public function handleReturn($returnData)
    {
        try {
            $credentials = $this->settings->getApiCredentials();
            $client = $this->getBayarcashClient();

            // Verify return data
            $isValid = $client->verifyReturnUrlCallbackData($returnData, $credentials['api_secret']);

            $orderNumber = sanitize_text_field($returnData['order_number'] ?? '');
            $order = Order::where('order_number', $orderNumber)->first();

            if (!$order) {
                return [
                    'success' => false,
                    'message' => __('Order not found', 'bayarcash-for-fluentcart')
                ];
            }

            $status = sanitize_text_field($returnData['record_status'] ?? '');

            if ($isValid && ($status === '1' || $status === 1)) {
                return [
                    'success' => true,
                    'redirect_url' => $order->getSuccessUrl(),
                    'message' => __('Payment successful', 'bayarcash-for-fluentcart')
                ];
            } elseif ($status === '3' || $status === 3) {
                return [
                    'success' => true,
                    'redirect_url' => $order->getSuccessUrl(),
                    'message' => __('Payment is being processed', 'bayarcash-for-fluentcart')
                ];
            } else {
                return [
                    'success' => false,
                    'redirect_url' => $order->getFailedUrl(),
                    'message' => __('Payment failed or was cancelled', 'bayarcash-for-fluentcart')
                ];
            }

        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
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
        $template = $this->settings->getSetting('payment_description', 'Payment for Order #{{order_id}}');
        return str_replace('{{order_id}}', $order->order_number, $template);
    }

    /**
     * Get return URL
     *
     * @param Order $order
     * @return string
     */
    protected function getReturnUrl($order)
    {
        return add_query_arg([
            'fluent_cart_payment_api' => '1',
            'payment_method' => 'bayarcash',
            'action' => 'return',
            'order_id' => $order->id
        ], site_url('/'));
    }
}
