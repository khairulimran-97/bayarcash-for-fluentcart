<?php

namespace BayarcashForFluentCart;

use FluentCart\Api\StoreSettings;
use FluentCart\App\Modules\PaymentMethods\Core\BaseGatewaySettings;

defined('ABSPATH') or exit;

class BayarcashSettings extends BaseGatewaySettings
{
    public $settings;
    public $methodHandler = 'fluent_cart_payment_settings_bayarcash';

    /**
     * Get default settings
     *
     * @return array
     */
    public static function getDefaults(): array
    {
        return [
            'is_active' => 'no',
            'payment_mode' => 'test',
            'test_api_token' => '',
            'test_api_secret' => '',
            'live_api_token' => '',
            'live_api_secret' => '',
            'portal_key' => '',
            'payment_description' => 'Payment for Order #{{order_id}}',
            'payment_button_label' => __('Pay with Bayarcash', 'bayarcash-for-fluentcart')
        ];
    }

    /**
     * Get settings or specific key
     *
     * @param string $key
     * @return mixed
     */
    public function get($key = '')
    {
        $settings = $this->settings;

        if ($key && isset($this->settings[$key])) {
            return $this->settings[$key];
        }
        return $settings;
    }

    /**
     * Get payment mode
     *
     * @return string
     */
    public function getMode()
    {
        return $this->get('payment_mode') ?: 'test';
    }

    /**
     * Check if gateway is active
     *
     * @return bool
     */
    public function isActive(): bool
    {
        return $this->get('is_active') === 'yes';
    }

    /**
     * Get current API credentials based on mode
     *
     * @param string|null $mode
     * @return array
     */
    public function getApiCredentials($mode = null)
    {
        if (!$mode) {
            $mode = $this->getMode();
        }

        return [
            'api_token' => $this->get($mode . '_api_token'),
            'api_secret' => $this->get($mode . '_api_secret'),
            'portal_key' => $this->get('portal_key'),
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
        $mode = $this->getMode();
        $apiToken = $this->get($mode . '_api_token');
        $apiSecret = $this->get($mode . '_api_secret');
        $portalKey = $this->get('portal_key');

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
