<?php

namespace BayarcashForFluentCart;

use FluentCart\App\Services\PaymentGateways\AbstractPaymentGateway;
use FluentCart\App\Models\PaymentInstance;

defined('ABSPATH') or exit;

class BayarcashGateway extends AbstractPaymentGateway
{
    /**
     * @var BayarcashSettings
     */
    protected $settings;

    /**
     * @var BayarcashProcessor
     */
    protected $processor;

    /**
     * Supported features
     *
     * @var array
     */
    protected $supportedFeatures = ['payment', 'webhook'];

    /**
     * Constructor
     */
    public function __construct()
    {
        parent::__construct();
        $this->settings = new BayarcashSettings('bayarcash');
        $this->processor = new BayarcashProcessor($this->settings);
    }

    /**
     * Get gateway metadata
     *
     * @return array
     */
    public function meta()
    {
        return [
            'title' => __('Bayarcash', 'bayarcash-for-fluentcart'),
            'route' => 'bayarcash',
            'slug' => 'bayarcash',
            'logo' => BAYARCASH_FC_URL . 'assets/images/bayarcash-logo.png',
            'description' => __('Accept payments via Bayarcash - FPX, DuitNow QR, and more', 'bayarcash-for-fluentcart'),
            'status' => $this->settings->getSetting('enabled') === 'yes' ? 'active' : 'inactive',
            'settings_component' => 'bayarcash_settings'
        ];
    }

    /**
     * Check if gateway has a feature
     *
     * @param string $feature
     * @return bool
     */
    public function has($feature)
    {
        return in_array($feature, $this->supportedFeatures);
    }

    /**
     * Get gateway settings fields
     *
     * @return array
     */
    public function fields()
    {
        return $this->settings->getSettings();
    }

    /**
     * Process payment from payment instance
     *
     * @param PaymentInstance $paymentInstance
     * @return array
     */
    public function makePaymentFromPaymentInstance(PaymentInstance $paymentInstance)
    {
        $order = $paymentInstance->order;
        $transaction = $paymentInstance->transaction;

        // Check if gateway is configured
        if (!$this->settings->isConfigured()) {
            return [
                'success' => false,
                'message' => __('Bayarcash payment gateway is not properly configured', 'bayarcash-for-fluentcart')
            ];
        }

        // Create payment
        $result = $this->processor->createPayment($order, $transaction);

        if (is_wp_error($result)) {
            return [
                'success' => false,
                'message' => $result->get_error_message()
            ];
        }

        if (!empty($result['payment_url'])) {
            return [
                'success' => true,
                'redirect_url' => $result['payment_url'],
                'message' => __('Redirecting to Bayarcash payment page...', 'bayarcash-for-fluentcart')
            ];
        }

        return [
            'success' => false,
            'message' => __('Failed to create payment', 'bayarcash-for-fluentcart')
        ];
    }

    /**
     * Handle IPN/Webhook callbacks
     *
     * @return void
     */
    public function handleIPN()
    {
        $action = sanitize_text_field($_REQUEST['action'] ?? '');

        if ($action === 'callback') {
            $this->handleWebhookCallback();
        } elseif ($action === 'return') {
            $this->handleReturnCallback();
        }

        exit;
    }

    /**
     * Handle webhook callback from Bayarcash
     *
     * @return void
     */
    protected function handleWebhookCallback()
    {
        $callbackData = $_POST;

        $result = $this->processor->handleCallback($callbackData);

        if (is_wp_error($result)) {
            status_header(400);
            echo wp_json_encode([
                'success' => false,
                'message' => $result->get_error_message()
            ]);
            exit;
        }

        status_header(200);
        echo wp_json_encode([
            'success' => true,
            'message' => 'Callback processed'
        ]);
        exit;
    }

    /**
     * Handle return URL callback
     *
     * @return void
     */
    protected function handleReturnCallback()
    {
        $returnData = $_GET;
        $result = $this->processor->handleReturn($returnData);

        if (!empty($result['redirect_url'])) {
            wp_redirect($result['redirect_url']);
            exit;
        }

        wp_die($result['message'] ?? __('Payment processing error', 'bayarcash-for-fluentcart'));
    }

    /**
     * Get order info for frontend
     *
     * @param int $orderId
     * @return array
     */
    public function getOrderInfo($orderId)
    {
        return [
            'payment_method' => 'bayarcash',
            'button_label' => $this->settings->getSetting('payment_button_label', __('Pay with Bayarcash', 'bayarcash-for-fluentcart')),
            'webhook_url' => $this->settings->getWebhookUrl()
        ];
    }

    /**
     * Get enqueue script source
     *
     * @return string
     */
    public function getEnqueueScriptSrc()
    {
        return BAYARCASH_FC_URL . 'assets/js/bayarcash-checkout.js';
    }

    /**
     * Get localized script data
     *
     * @return array
     */
    public function getLocalizeScriptData()
    {
        return [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('bayarcash_checkout'),
            'payment_method' => 'bayarcash',
            'labels' => [
                'processing' => __('Processing payment...', 'bayarcash-for-fluentcart'),
                'error' => __('Payment error occurred', 'bayarcash-for-fluentcart')
            ]
        ];
    }
}
