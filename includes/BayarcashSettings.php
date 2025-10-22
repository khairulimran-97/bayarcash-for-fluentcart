<?php

namespace BayarcashForFluentCart;

use FluentCart\App\Services\PaymentGateways\BaseGatewaySettings;

defined('ABSPATH') or exit;

class BayarcashSettings extends BaseGatewaySettings
{
    /**
     * Get gateway settings fields
     *
     * @return array
     */
    public function getSettings()
    {
        return [
            'enabled' => [
                'label' => __('Enable/Disable', 'bayarcash-for-fluentcart'),
                'type' => 'checkbox',
                'default' => 'no',
                'checkbox_label' => __('Enable Bayarcash Payment Gateway', 'bayarcash-for-fluentcart')
            ],
            'payment_mode' => [
                'label' => __('Payment Mode', 'bayarcash-for-fluentcart'),
                'type' => 'select',
                'options' => [
                    'test' => __('Test Mode', 'bayarcash-for-fluentcart'),
                    'live' => __('Live Mode', 'bayarcash-for-fluentcart')
                ],
                'default' => 'test',
                'help' => __('Select Test Mode for testing payments, Live Mode for production', 'bayarcash-for-fluentcart')
            ],
            'test_api_token' => [
                'label' => __('Test API Token', 'bayarcash-for-fluentcart'),
                'type' => 'password',
                'help' => __('Enter your Bayarcash test API token', 'bayarcash-for-fluentcart'),
                'dependency' => [
                    'depends_on' => 'payment_mode',
                    'value' => 'test'
                ]
            ],
            'test_api_secret' => [
                'label' => __('Test API Secret Key', 'bayarcash-for-fluentcart'),
                'type' => 'password',
                'help' => __('Enter your Bayarcash test API secret key', 'bayarcash-for-fluentcart'),
                'dependency' => [
                    'depends_on' => 'payment_mode',
                    'value' => 'test'
                ]
            ],
            'live_api_token' => [
                'label' => __('Live API Token', 'bayarcash-for-fluentcart'),
                'type' => 'password',
                'help' => __('Enter your Bayarcash live API token', 'bayarcash-for-fluentcart'),
                'dependency' => [
                    'depends_on' => 'payment_mode',
                    'value' => 'live'
                ]
            ],
            'live_api_secret' => [
                'label' => __('Live API Secret Key', 'bayarcash-for-fluentcart'),
                'type' => 'password',
                'help' => __('Enter your Bayarcash live API secret key', 'bayarcash-for-fluentcart'),
                'dependency' => [
                    'depends_on' => 'payment_mode',
                    'value' => 'live'
                ]
            ],
            'portal_key' => [
                'label' => __('Portal Key', 'bayarcash-for-fluentcart'),
                'type' => 'text',
                'help' => __('Enter your Bayarcash portal key', 'bayarcash-for-fluentcart')
            ],
            'payment_description' => [
                'label' => __('Payment Description', 'bayarcash-for-fluentcart'),
                'type' => 'text',
                'default' => 'Payment for Order #{{order_id}}',
                'help' => __('Description shown to customer during payment. Use {{order_id}} for order number', 'bayarcash-for-fluentcart')
            ],
            'payment_button_label' => [
                'label' => __('Payment Button Label', 'bayarcash-for-fluentcart'),
                'type' => 'text',
                'default' => __('Pay with Bayarcash', 'bayarcash-for-fluentcart'),
                'help' => __('Text displayed on the payment button', 'bayarcash-for-fluentcart')
            ]
        ];
    }

    /**
     * Get current API credentials based on mode
     *
     * @param string $mode
     * @return array
     */
    public function getApiCredentials($mode = null)
    {
        if (!$mode) {
            $mode = $this->getSetting('payment_mode', 'test');
        }

        return [
            'api_token' => $this->getSetting($mode . '_api_token'),
            'api_secret' => $this->getSetting($mode . '_api_secret'),
            'portal_key' => $this->getSetting('portal_key'),
            'mode' => $mode
        ];
    }

    /**
     * Check if gateway is properly configured
     *
     * @return bool
     */
    public function isConfigured()
    {
        $mode = $this->getSetting('payment_mode', 'test');
        $apiToken = $this->getSetting($mode . '_api_token');
        $apiSecret = $this->getSetting($mode . '_api_secret');
        $portalKey = $this->getSetting('portal_key');

        return !empty($apiToken) && !empty($apiSecret) && !empty($portalKey);
    }

    /**
     * Get webhook URL for Bayarcash callbacks
     *
     * @return string
     */
    public function getWebhookUrl()
    {
        return add_query_arg([
            'fluent_cart_payment_api' => '1',
            'payment_method' => 'bayarcash',
            'action' => 'callback'
        ], site_url('/'));
    }
}
