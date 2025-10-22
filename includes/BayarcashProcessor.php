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
                }

                return [
                    'success' => true,
                    'payment_url' => $paymentUrl,
                    'payment_intent_id' => $response->id ?? null
                ];
            }

            return new \WP_Error('bayarcash_error', __('Failed to create payment intent', 'bayarcash-for-fluentcart'));

        } catch (\Exception $e) {
            return new \WP_Error('bayarcash_exception', $e->getMessage());
        }
    }

    





    public function handleCallback($callbackData)
    {
        try {
            $orderNumber = sanitize_text_field($callbackData['order_number'] ?? '');

            
            $order = Order::find(intval($orderNumber));

            if (!$order) {
                return new \WP_Error('bayarcash_order_not_found', __('Order not found', 'bayarcash-for-fluentcart'));
            }

            $protectedStatuses = ['paid', 'refunded', 'partially_refunded'];
            if (in_array($order->payment_status, $protectedStatuses)) {
                return true; 
            }

            
            $recordType = sanitize_text_field($callbackData['record_type'] ?? '');

            
            $credentials = $this->settings->getApiCredentials($order->mode);
            $client = $this->getBayarcashClient($order->mode);

            
            $isValid = $client->verifyTransactionCallbackData($callbackData, $credentials['api_secret']);

            if (!$isValid) {
                return new \WP_Error('bayarcash_invalid_callback', __('Invalid callback signature', 'bayarcash-for-fluentcart'));
            }

            if ($recordType === 'pre_transaction') {

                
                $exchangeRefNumber = sanitize_text_field($callbackData['exchange_reference_number'] ?? '');
                $transactionId = sanitize_text_field($callbackData['transaction_id'] ?? '');

                if ($exchangeRefNumber) {
                    $order->updateMeta('bayarcash_exchange_reference_number', $exchangeRefNumber);
                }
                if ($transactionId) {
                    $order->updateMeta('bayarcash_transaction_id', $transactionId);
                }

                return true;
            }

            if ($recordType === 'transaction') {
                $transactionId = sanitize_text_field($callbackData['transaction_id'] ?? '');
                $exchangeTransactionId = sanitize_text_field($callbackData['exchange_transaction_id'] ?? '');
                $statusDescription = sanitize_text_field($callbackData['status_description'] ?? '');
                $paymentGatewayId = sanitize_text_field($callbackData['payment_gateway_id'] ?? '');

                if ($transactionId) {
                    $order->updateMeta('bayarcash_transaction_id', $transactionId);
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
                    $transaction->status = 'succeeded';
                    $transaction->save();

                    $statusHelper = new StatusHelper($order);
                    $statusHelper->syncOrderStatuses($transaction);

                    return true;
                } elseif ($status === '0' || $status === 0 || $status === '1' || $status === 1) {
                    $transaction->status = 'pending';
                    $transaction->save();

                    return true;
                } else {
                    $transaction->status = 'failed';
                    $transaction->note = __('Payment failed or cancelled', 'bayarcash-for-fluentcart');
                    $transaction->save();

                    $order->payment_status = 'failed';
                    $order->save();

                    return false;
                }
            }

            return new \WP_Error('bayarcash_unknown_record_type', __('Unknown callback record type', 'bayarcash-for-fluentcart'));

        } catch (\Exception $e) {
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

        // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
        $requestUri = isset($_SERVER['REQUEST_URI']) ? wp_unslash($_SERVER['REQUEST_URI']) : '';

        if (strpos($requestUri, 'transaction_id') === false || strpos($requestUri, 'status') === false) {
            return;
        }

        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        if (!isset($_GET['method']) || $_GET['method'] !== 'bayarcash') {
            return;
        }

        $processed = true;

        try {
            $params = [];

            if (substr_count($requestUri, '?') > 1) {
                $firstQuestionMark = strpos($requestUri, '?');
                $fixedUri = substr($requestUri, 0, $firstQuestionMark + 1) .
                           str_replace('?', '&', substr($requestUri, $firstQuestionMark + 1));

                $queryString = wp_parse_url($fixedUri, PHP_URL_QUERY);
                if ($queryString) {
                    parse_str($queryString, $params);
                }
            } else {
                // phpcs:ignore WordPress.Security.NonceVerification.Recommended
                $params = $_GET;
            }

            $orderId = intval($params['order_id'] ?? 0);
            $status = sanitize_text_field($params['status'] ?? '');
            $transactionId = sanitize_text_field($params['transaction_id'] ?? '');

            if (!$orderId) {
                wp_die(esc_html__('Order ID not found', 'bayarcash-for-fluentcart'));
            }

            $order = Order::find($orderId);

            if (!$order) {
                wp_die(esc_html__('Order not found', 'bayarcash-for-fluentcart'));
            }

            $protectedStatuses = ['paid', 'refunded', 'partially_refunded'];
            if (in_array($order->payment_status, $protectedStatuses)) {
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
                wp_die(esc_html__('Transaction not found', 'bayarcash-for-fluentcart'));
            }

            
            $credentials = $this->settings->getApiCredentials($order->mode);
            $client = $this->getBayarcashClient($order->mode);
            $isValid = $client->verifyReturnUrlCallbackData($params, $credentials['api_secret']);

            if ($transactionId) {
                $order->updateMeta('bayarcash_transaction_id', $transactionId);
            }

            if ($status === '3' || $status === 3) {
                $transaction->status = 'succeeded';
                $transaction->save();

                
                $statusHelper = new StatusHelper($order);
                $statusHelper->syncOrderStatuses($transaction);
            } elseif ($status === '0' || $status === 0 || $status === '1' || $status === 1) {
                $transaction->status = 'pending';
                $transaction->save();
            } else {
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

            wp_redirect($cleanReceiptUrl);
            exit;

        } catch (\Exception $e) {
            wp_die(esc_html($e->getMessage()));
        }
    }
}
