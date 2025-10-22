<?php

namespace BayarcashForFluentCart;

use FluentCart\App\Modules\PaymentMethods\Core\AbstractPaymentGateway;
use FluentCart\App\Services\Payments\PaymentInstance;
use FluentCart\App\Models\Order;

defined('ABSPATH') or exit;

class BayarcashGateway extends AbstractPaymentGateway
{
    /**
     * @var BayarcashProcessor
     */
    protected $processor;

    /**
     * Supported features
     *
     * @var array
     */
    public array $supportedFeatures = ['payment', 'webhook'];

    /**
     * Constructor
     */
    public function __construct()
    {
        $settings = new BayarcashSettings();
        parent::__construct($settings);
        $this->processor = new BayarcashProcessor($this->settings);
    }

    /**
     * Boot method for additional initialization
     */
    public function boot()
    {
        // Additional initialization if needed
    }

    /**
     * Get gateway metadata
     *
     * @return array
     */
    public function meta(): array
    {
        return [
            'title' => __('Bayarcash', 'bayarcash-for-fluentcart'),
            'route' => 'bayarcash',
            'slug' => 'bayarcash',
            'logo' => BAYARCASH_FC_URL . 'assets/images/bayarcash-logo.png',
            'description' => __('Accept payments via Bayarcash - FPX, DuitNow QR, and more', 'bayarcash-for-fluentcart'),
            'status' => $this->settings->get('is_active') === 'yes',
            'upcoming' => false,
        ];
    }

    /**
     * Get gateway settings fields
     *
     * @return array
     */
    public function fields(): array
    {
        return [
            'bayarcash_description' => [
                'value' => sprintf(
                    '<div class="pt-4">
                        <p>%s</p>
                        <p>%s</p>
                    </div>',
                    __('Accept payments via Bayarcash payment gateway. Supports FPX, DuitNow QR, and other Malaysian payment methods.', 'bayarcash-for-fluentcart'),
                    sprintf(
                        __('Get your API credentials from <a href="%s" target="_blank">Bayarcash Dashboard</a>', 'bayarcash-for-fluentcart'),
                        'https://bayarcash.com'
                    )
                ),
                'label' => __('Description', 'bayarcash-for-fluentcart'),
                'type' => 'html_attr'
            ],
            'payment_mode' => [
                'label' => __('Payment Mode', 'bayarcash-for-fluentcart'),
                'type' => 'payment_mode_selector',
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
                'conditional_logic' => [
                    [
                        'key' => 'payment_mode',
                        'compare' => '=',
                        'value' => 'test'
                    ]
                ]
            ],
            'test_api_secret' => [
                'label' => __('Test API Secret Key', 'bayarcash-for-fluentcart'),
                'type' => 'password',
                'help' => __('Enter your Bayarcash test API secret key', 'bayarcash-for-fluentcart'),
                'conditional_logic' => [
                    [
                        'key' => 'payment_mode',
                        'compare' => '=',
                        'value' => 'test'
                    ]
                ]
            ],
            'live_api_token' => [
                'label' => __('Live API Token', 'bayarcash-for-fluentcart'),
                'type' => 'password',
                'help' => __('Enter your Bayarcash live API token', 'bayarcash-for-fluentcart'),
                'conditional_logic' => [
                    [
                        'key' => 'payment_mode',
                        'compare' => '=',
                        'value' => 'live'
                    ]
                ]
            ],
            'live_api_secret' => [
                'label' => __('Live API Secret Key', 'bayarcash-for-fluentcart'),
                'type' => 'password',
                'help' => __('Enter your Bayarcash live API secret key', 'bayarcash-for-fluentcart'),
                'conditional_logic' => [
                    [
                        'key' => 'payment_mode',
                        'compare' => '=',
                        'value' => 'live'
                    ]
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
            ],
            'webhook_url' => [
                'value' => sprintf(
                    '<div class="pt-4">
                        <p><strong>%s:</strong></p>
                        <code style="display: block; padding: 10px; background: #f5f5f5; margin-top: 5px;">%s</code>
                        <p class="description" style="margin-top: 10px;">%s</p>
                    </div>',
                    __('Webhook URL', 'bayarcash-for-fluentcart'),
                    $this->settings->getWebhookUrl(),
                    __('Copy this URL and add it to your Bayarcash dashboard webhook settings', 'bayarcash-for-fluentcart')
                ),
                'label' => __('Webhook Configuration', 'bayarcash-for-fluentcart'),
                'type' => 'html_attr'
            ]
        ];
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
                'status' => 'failed',
                'message' => __('Bayarcash payment gateway is not properly configured', 'bayarcash-for-fluentcart')
            ];
        }

        // Create payment
        $result = $this->processor->createPayment($order, $transaction);

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
     * Get enqueue script source
     *
     * @param string $hasSubscription
     * @return array
     */
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

    /**
     * Get localized script data
     *
     * @return array
     */
    public function getLocalizeData(): array
    {
        return [
            'fct_bayarcash_data' => [
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('bayarcash_checkout'),
                'payment_method' => 'bayarcash',
                'translations' => [
                    'processing' => __('Processing payment...', 'bayarcash-for-fluentcart'),
                    'error' => __('Payment error occurred', 'bayarcash-for-fluentcart')
                ]
            ]
        ];
    }
}
