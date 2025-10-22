<?php

namespace BayarcashForFluentCart;

use FluentCart\Api\StoreSettings;
use FluentCart\App\Modules\PaymentMethods\Core\BaseGatewaySettings;

defined('ABSPATH') or exit;

class BayarcashSettings extends BaseGatewaySettings
{
    public $settings;
    public $methodHandler = 'fluent_cart_payment_settings_bayarcash';

    const TEST_API_TOKEN = 'eyJ0eXAiOiJKV1QiLCJhbGciOiJSUzI1NiJ9.eyJhdWQiOiI1IiwianRpIjoiZDM4MjI5ZTQ5NTllYzQ4Mjg5NDU1Yzg1ZmYyMzMwMTQxNTVlYzI4ZjA0MDIyZjc4YzVhOWFkNWZlZDIxZDBjNzYwYmIwYWI2MGY5YjM5ZDMiLCJpYXQiOjE3MjQxNDAwNDEuMDgwNTM1LCJuYmYiOjE3MjQxNDAwNDEuMDgwNTM3LCJleHAiOjIwMzk2NzI4NDEuMDc5OTAxLCJzdWIiOiI2Iiwic2NvcGVzIjpbIioiXX0.Kn6MXwi6d33aZQpnQqq_Ng7b6UeNlZiXIJ-Jth6PmUoRJBTmw4hdAlDQVSJRosHN4giUBm1lquflNnjqpwI9-bBv-ttqF79X3GjW2GMkYzAnvghGyEn5ldQwBQdmp8pjm7o4Pn1faMe81I5rehQLM8rJFnnQsArKzHl6ZHi7w4gMscIsP-ISWnTN7zO0nBNw6KA5ZpGhhPPhM8Zfrq4nmDWtne6-8h1VoFErPTaKu_GfDXma3PnfJaGwGtWJdJePB6wpR_FwrsB8zgByyOilgRTNZiTBHio4-c-T0V1UU48SDojmCEYNuD1iSdQC-MRaAKUaHdWy7kfmyOy7FohmBbqsag8F47UjDD97VoVOmfUYP6FeKGTMOBuqcOcgN42KXs0Pa6juWIHXtOqn6_WFU9oAhuELIRDX8qR_0-CEIQSJxeeKj8AWBcAvgM2iUeD15QTHJAC41EKpLpL31HboNvk4bJfol4vo3j1SBdHMLmZzI3iENBJtGEO-jNgovhzDkPkCu39u0PrA6-La7VqZ3a-6ItvRyVHcR4ud_zl2oHBl-ZggPB92XVV7yNGUOgHpbshptWbcSWR6XeHHkbNU2K9T8y9c62r-R9KzK07fvn0C3bgR7f8wwgBrZn7WR_dC6Rk_pjumCi8UvItFOgDa5TQXgUnZVBFMPZY3h8APQA0';
    const TEST_API_SECRET = 'CBFSkTgiaIcro1lZLyaiD8zyFNaa2Fsa';
    const TEST_PORTAL_KEY = '80650707781bd2c466cd7ae14ff3debf';

    public static function getDefaults(): array
    {
        return [
            'is_active' => 'no',
            'live_api_token' => '',
            'live_api_secret' => '',
            'portal_key' => '',
            'payment_channels' => ['1', '6'],
            'bayarcash_checkout_theme' => 'light',
            'bayarcash_checkout_button_text' => __('Pay with Bayarcash', 'bayarcash-for-fluentcart'),
            'bayarcash_checkout_button_color' => '',
            'bayarcash_checkout_button_hover_color' => '',
            'bayarcash_checkout_button_text_color' => '',
            'bayarcash_checkout_button_font_size' => '16px'
        ];
    }

    public function get($key = '')
    {
        $settings = $this->settings;

        if ($key && isset($this->settings[$key])) {
            return $this->settings[$key];
        }
        return $settings;
    }

    public function getMode()
    {
        return (new StoreSettings)->get('order_mode');
    }

    public function isTestMode()
    {
        return $this->getMode() === 'test';
    }

    public function isActive(): bool
    {
        return $this->get('is_active') === 'yes';
    }

    public function getApiCredentials($mode = null)
    {
        if (!$mode) {
            $mode = $this->getMode();
        }

        if ($mode === 'test') {
            return [
                'api_token' => self::TEST_API_TOKEN,
                'api_secret' => self::TEST_API_SECRET,
                'portal_key' => self::TEST_PORTAL_KEY,
                'mode' => $mode
            ];
        }

        return [
            'api_token' => $this->get('live_api_token'),
            'api_secret' => $this->get('live_api_secret'),
            'portal_key' => $this->get('portal_key'),
            'mode' => $mode
        ];
    }

    public function isConfigured()
    {
        $mode = $this->getMode();

        if ($mode === 'test') {
            return true;
        }

        $apiToken = $this->get('live_api_token');
        $apiSecret = $this->get('live_api_secret');
        $portalKey = $this->get('portal_key');

        return !empty($apiToken) && !empty($apiSecret) && !empty($portalKey);
    }

    public function getPaymentChannels()
    {
        $channels = $this->get('payment_channels');

        if (empty($channels) || !is_array($channels)) {
            return [];
        }

        return array_map('intval', $channels);
    }

    public function getCallbackUrl()
    {
        return add_query_arg([
            'fluent-cart' => 'fct_payment_listener_ipn',
            'method' => 'bayarcash'
        ], site_url('/'));
    }
}
