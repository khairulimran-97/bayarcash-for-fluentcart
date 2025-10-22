<?php

namespace BayarcashForFluentCart;

use FluentCart\App\Modules\PaymentMethods\Core\AbstractPaymentGateway;
use FluentCart\App\Services\Payments\PaymentInstance;
use FluentCart\App\Models\Order;

defined('ABSPATH') or exit;

class BayarcashGateway extends AbstractPaymentGateway
{
    public $processor;
    public array $supportedFeatures = ['payment'];

    public function __construct()
    {
        $settings = new BayarcashSettings();
        parent::__construct($settings);
        $this->processor = new BayarcashProcessor($this->settings);

        add_filter('fluent_cart/payment_methods_with_custom_checkout_buttons', function ($methods) {
            $methods[] = 'bayarcash';
            return $methods;
        });
    }

    public function boot()
    {
    }

    public function meta(): array
    {
        return [
            'title' => __('Bayarcash', 'bayarcash-for-fluentcart'),
            'route' => 'bayarcash',
            'slug' => 'bayarcash',
            'description' => __('Accept payments via Bayarcash - FPX, DuitNow QR, and more', 'bayarcash-for-fluentcart'),
            'logo' => BAYARCASH_FC_URL . 'assets/img/bayarcash-icon.png',
            'icon' => BAYARCASH_FC_URL . 'assets/img/bayarcash-icon.png',
            'brand_color' => '#00a651',
            'status' => $this->settings->get('is_active') === 'yes',
            'upcoming' => false,
            'supported_features' => $this->supportedFeatures,
        ];
    }

    public function fields(): array
    {
        return [
            'notice' => [
                'value' => $this->renderStoreModeNotice(),
                'label' => __('Store Mode Notice', 'bayarcash-for-fluentcart'),
                'type' => 'notice'
            ],
            'bayarcash_description' => [
                'value' => sprintf(
                    '<div class="pt-4">
                        <p>%s</p>
                        <p>%s</p>
                    </div>',
                    __('Accept payments via Bayarcash payment gateway. Supports FPX, DuitNow QR, and other Malaysian payment methods.', 'bayarcash-for-fluentcart'),
                    sprintf(
                        /* translators: %s: URL to Bayarcash Dashboard */
                        __('Get your API credentials from <a href="%s" target="_blank">Bayarcash Dashboard</a>', 'bayarcash-for-fluentcart'),
                        'https://bayarcash.com'
                    )
                ),
                'label' => __('Description', 'bayarcash-for-fluentcart'),
                'type' => 'html_attr'
            ],
            'live_api_token' => [
                'type' => 'text',
                'label' => __('Personal Access Token (PAT)', 'bayarcash-for-fluentcart'),
                'placeholder' => __('Enter your Personal Access Token', 'bayarcash-for-fluentcart'),
                'help' => __('Enter your Bayarcash Personal Access Token from your dashboard', 'bayarcash-for-fluentcart')
            ],
            'live_api_secret' => [
                'label' => __('API Secret Key', 'bayarcash-for-fluentcart'),
                'type' => 'text',
                'placeholder' => __('Enter your API Secret Key', 'bayarcash-for-fluentcart'),
                'help' => __('Enter your Bayarcash API secret key from your dashboard', 'bayarcash-for-fluentcart')
            ],
            'portal_key' => [
                'label' => __('Portal Key', 'bayarcash-for-fluentcart'),
                'type' => 'text',
                'placeholder' => __('Enter your Portal Key', 'bayarcash-for-fluentcart'),
                'help' => __('Enter your Bayarcash portal key from your dashboard', 'bayarcash-for-fluentcart')
            ],
            'payment_channels' => [
                'label' => __('Payment Channels', 'bayarcash-for-fluentcart'),
                'type' => 'checkbox_group',
                'options' => [
                    '1' => __('FPX (Online Banking)', 'bayarcash-for-fluentcart'),
                    '3' => __('FPX Direct Debit', 'bayarcash-for-fluentcart'),
                    '4' => __('FPX Line of Credit', 'bayarcash-for-fluentcart'),
                    '5' => __('DuitNow Online Banking/Wallets', 'bayarcash-for-fluentcart'),
                    '6' => __('DuitNow QR', 'bayarcash-for-fluentcart'),
                    '8' => __('Boost PayFlex', 'bayarcash-for-fluentcart'),
                    '9' => __('QRIS (Online Banking)', 'bayarcash-for-fluentcart'),
                    '10' => __('QRIS (E-Wallet)', 'bayarcash-for-fluentcart'),
                    '11' => __('NETS', 'bayarcash-for-fluentcart'),
                    '12' => __('Credit Card', 'bayarcash-for-fluentcart'),
                    '13' => __('Alipay', 'bayarcash-for-fluentcart'),
                    '14' => __('WeChat Pay', 'bayarcash-for-fluentcart'),
                    '15' => __('PromptPay', 'bayarcash-for-fluentcart')
                ],
                'default' => ['1', '6'],
                'help' => __('Select which payment channels to enable. Leave empty to show all available channels.', 'bayarcash-for-fluentcart')
            ],
            'bayarcash_checkout_theme' => [
                'value' => 'light',
                'label' => __('Bayarcash Checkout Theme', 'bayarcash-for-fluentcart'),
                'type' => 'select',
                'options' => [
                    'light' => [
                        'label' => __('Light', 'bayarcash-for-fluentcart'),
                        'value' => 'light'
                    ],
                    'dark' => [
                        'label' => __('Dark', 'bayarcash-for-fluentcart'),
                        'value' => 'dark'
                    ]
                ],
                'tooltip' => __('Theme to use for Bayarcash checkout page', 'bayarcash-for-fluentcart')
            ],
            'bayarcash_checkout_button_text' => [
                'value' => __('Pay with Bayarcash', 'bayarcash-for-fluentcart'),
                'label' => __('Bayarcash Checkout Button Text', 'bayarcash-for-fluentcart'),
                'type' => 'text',
                'placeholder' => __('Pay with Bayarcash', 'bayarcash-for-fluentcart'),
                'tooltip' => __('Text to display on the Bayarcash checkout button', 'bayarcash-for-fluentcart')
            ],
            'bayarcash_checkout_button_color' => [
                'value' => '',
                'label' => __('Bayarcash Checkout Button Color', 'bayarcash-for-fluentcart'),
                'type' => 'color',
                'tooltip' => __('Color of the Bayarcash checkout button', 'bayarcash-for-fluentcart')
            ],
            'bayarcash_checkout_button_hover_color' => [
                'value' => '',
                'label' => __('Bayarcash Checkout Button Hover Color', 'bayarcash-for-fluentcart'),
                'type' => 'color',
                'tooltip' => __('Hover color of the Bayarcash checkout button', 'bayarcash-for-fluentcart')
            ],
            'bayarcash_checkout_button_text_color' => [
                'value' => '',
                'label' => __('Bayarcash Checkout Button Text Color', 'bayarcash-for-fluentcart'),
                'type' => 'color',
                'tooltip' => __('Text color of the Bayarcash checkout button', 'bayarcash-for-fluentcart')
            ],
            'bayarcash_checkout_button_font_size' => [
                'value' => '16px',
                'label' => __('Bayarcash Checkout Button Font Size', 'bayarcash-for-fluentcart'),
                'type' => 'text',
                'placeholder' => __('16px', 'bayarcash-for-fluentcart'),
                'tooltip' => __('Font size of the Bayarcash checkout button (e.g., 14px, 1rem)', 'bayarcash-for-fluentcart')
            ]
        ];
    }

    public static function validateSettings($data): array
    {
        $storeSettings = new \FluentCart\Api\StoreSettings();
        $storeMode = $storeSettings->get('order_mode');

        $apiToken = trim($data['live_api_token'] ?? '');
        $apiSecret = trim($data['live_api_secret'] ?? '');
        $portalKey = trim($data['portal_key'] ?? '');

        if (empty($apiToken)) {
            return [
                'status' => 'failed',
                'message' => __('Personal Access Token (PAT) is required to activate the gateway.', 'bayarcash-for-fluentcart')
            ];
        }

        if (empty($apiSecret)) {
            return [
                'status' => 'failed',
                'message' => __('API Secret Key is required to activate the gateway.', 'bayarcash-for-fluentcart')
            ];
        }

        if (empty($portalKey)) {
            return [
                'status' => 'failed',
                'message' => __('Portal Key is required to activate the gateway.', 'bayarcash-for-fluentcart')
            ];
        }

        $modeMessage = $storeMode === 'test'
            ? __('Bayarcash test mode is enabled with your credentials.', 'bayarcash-for-fluentcart')
            : __('Bayarcash gateway credentials verified successfully!', 'bayarcash-for-fluentcart');

        return [
            'status' => 'success',
            'message' => $modeMessage
        ];
    }

    public function makePaymentFromPaymentInstance(PaymentInstance $paymentInstance)
    {
        $order = $paymentInstance->order;
        $transaction = $paymentInstance->transaction;

        if (!$this->settings->isConfigured()) {
            return [
                'status' => 'failed',
                'message' => __('Bayarcash payment gateway is not properly configured', 'bayarcash-for-fluentcart')
            ];
        }

        $selectedChannel = null;
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        if (isset($_REQUEST['bayarcash_selected_channel'])) {
            // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            $selectedChannel = intval($_REQUEST['bayarcash_selected_channel']);
        }

        $result = $this->processor->createPayment($order, $transaction, $selectedChannel);

        if (is_wp_error($result)) {
            return [
                'status' => 'failed',
                'message' => $result->get_error_message()
            ];
        }

        if (!empty($result['payment_url'])) {
            return [
                'status' => 'success',
                'redirect_to' => $result['payment_url'],
                'message' => __('Redirecting to Bayarcash payment page...', 'bayarcash-for-fluentcart')
            ];
        }

        return [
            'status' => 'failed',
            'message' => __('Failed to create payment', 'bayarcash-for-fluentcart')
        ];
    }

    public function handleIPN()
    {
        // phpcs:ignore WordPress.Security.NonceVerification.Missing
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

    public function getEnqueueScriptSrc($hasSubscription = 'no'): array
    {
        return [
            [
                'handle' => 'bayarcash-checkout',
                'src' => BAYARCASH_FC_URL . 'assets/js/bayarcash-checkout.js',
                'deps' => ['jquery'],
                'version' => BAYARCASH_FC_VERSION
            ]
        ];
    }

    public function getLocalizeData(): array
    {
        return [
            'fct_bayarcash_data' => [
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('bayarcash_checkout'),
                'payment_method' => 'bayarcash',
                'translations' => [
                    'processing' => __('Processing payment...', 'bayarcash-for-fluentcart'),
                    'error' => __('Payment error occurred', 'bayarcash-for-fluentcart'),
                    'Please select a payment method' => __('Please select a payment method', 'bayarcash-for-fluentcart'),
                    'Select Payment Method' => __('Select Payment Method', 'bayarcash-for-fluentcart')
                ]
            ]
        ];
    }

    public function getOrderInfo(array $data)
    {
        $settings = $this->settings->get();
        $selectedChannels = $this->settings->getPaymentChannels();

        $channelNames = [
            1 => __('FPX (Online Banking)', 'bayarcash-for-fluentcart'),
            3 => __('FPX Direct Debit', 'bayarcash-for-fluentcart'),
            4 => __('FPX Line of Credit', 'bayarcash-for-fluentcart'),
            5 => __('DuitNow Online Banking/Wallets', 'bayarcash-for-fluentcart'),
            6 => __('DuitNow QR', 'bayarcash-for-fluentcart'),
            8 => __('Boost PayFlex', 'bayarcash-for-fluentcart'),
            9 => __('QRIS (Online Banking)', 'bayarcash-for-fluentcart'),
            10 => __('QRIS (E-Wallet)', 'bayarcash-for-fluentcart'),
            11 => __('NETS', 'bayarcash-for-fluentcart'),
            12 => __('Credit Card', 'bayarcash-for-fluentcart'),
            13 => __('Alipay', 'bayarcash-for-fluentcart'),
            14 => __('WeChat Pay', 'bayarcash-for-fluentcart'),
            15 => __('PromptPay', 'bayarcash-for-fluentcart')
        ];

        $availableChannels = [];
        if (empty($selectedChannels)) {
            foreach ($channelNames as $id => $name) {
                $availableChannels[] = ['id' => $id, 'name' => $name];
            }
        } else {
            foreach ($selectedChannels as $channelId) {
                if (isset($channelNames[$channelId])) {
                    $availableChannels[] = ['id' => $channelId, 'name' => $channelNames[$channelId]];
                }
            }
        }

        $paymentArgs = [
            'mode' => $this->settings->getMode(),
            'bayarcash_checkout_button_text' => $settings['bayarcash_checkout_button_text'] ?? __('Pay with Bayarcash', 'bayarcash-for-fluentcart'),
            'bayarcash_checkout_button_color' => $settings['bayarcash_checkout_button_color'] ?? '',
            'bayarcash_checkout_button_hover_color' => $settings['bayarcash_checkout_button_hover_color'] ?? '',
            'bayarcash_checkout_button_text_color' => $settings['bayarcash_checkout_button_text_color'] ?? '',
            'bayarcash_checkout_button_font_size' => $settings['bayarcash_checkout_button_font_size'] ?? '16px',
            'available_channels' => $availableChannels
        ];

        $paymentDetails = [
            'theme' => $settings['bayarcash_checkout_theme'] ?? 'light',
        ];

        wp_send_json([
            'status' => 'success',
            'payment_args' => $paymentArgs,
            'intent' => $paymentDetails,
            'message' => __('Order info retrieved!', 'bayarcash-for-fluentcart')
        ], 200);
    }
}
