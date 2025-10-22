# Bayarcash for FluentCart

Accept payments via Bayarcash payment gateway for FluentCart - Supporting FPX, DuitNow QR, and other Malaysian payment methods.

## Description

This plugin integrates Bayarcash payment gateway with FluentCart, allowing you to accept payments through various Malaysian payment channels including:

- FPX (Financial Process Exchange)
- DuitNow QR
- And other payment methods supported by Bayarcash

## Requirements

- WordPress 5.0 or higher
- PHP 7.4 or higher
- FluentCart plugin (active)
- Composer (for installing dependencies)
- Bayarcash account with API credentials

## Installation

### 1. Install the Plugin

Download or clone this repository into your WordPress plugins directory:

```bash
cd wp-content/plugins/
git clone https://github.com/webimpian/bayarcash-for-fluentcart.git
```

### 2. Install Dependencies

Navigate to the plugin directory and install Composer dependencies:

```bash
cd bayarcash-for-fluentcart
composer install
```

### 3. Activate the Plugin

1. Go to WordPress Admin → Plugins
2. Find "Bayarcash for FluentCart"
3. Click "Activate"

## Configuration

### 1. Get Bayarcash API Credentials

1. Log in to your Bayarcash account
2. Navigate to Settings → API Credentials
3. Copy your API Token and API Secret Key
4. Note your Portal Key

### 2. Configure the Gateway

1. Go to FluentCart → Settings → Payment Gateways
2. Find "Bayarcash" and click to configure
3. Fill in the required settings:
   - **Enable/Disable**: Check to enable Bayarcash payments
   - **Payment Mode**: Select "Test Mode" for testing or "Live Mode" for production
   - **Test API Token**: Your Bayarcash test API token
   - **Test API Secret Key**: Your Bayarcash test API secret key
   - **Live API Token**: Your Bayarcash live API token
   - **Live API Secret Key**: Your Bayarcash live API secret key
   - **Portal Key**: Your Bayarcash portal key
   - **Payment Description**: Custom description shown to customers (use `{{order_id}}` for order number)
   - **Payment Button Label**: Text displayed on the payment button

### 3. Set Up Webhooks

Configure the following webhook URL in your Bayarcash account:

```
https://yoursite.com/?fluent_cart_payment_api=1&payment_method=bayarcash&action=callback
```

Replace `yoursite.com` with your actual domain.

## Usage

Once configured, Bayarcash will appear as a payment option during checkout. Customers will be redirected to Bayarcash's secure payment page to complete their payment.

### Payment Flow

1. Customer selects Bayarcash as payment method
2. Customer clicks "Pay with Bayarcash" button
3. Redirected to Bayarcash payment page
4. Customer selects payment method (FPX, DuitNow QR, etc.)
5. Completes payment
6. Redirected back to your site
7. Order status updated based on payment result

## Features

- ✅ Direct payment integration (no subscription support)
- ✅ Support for multiple payment channels
- ✅ Test and Live mode support
- ✅ Secure webhook callback handling
- ✅ Automatic order status updates
- ✅ Clean and modern checkout experience

## Development

### File Structure

```
bayarcash-for-fluentcart/
├── assets/
│   ├── images/          # Logo and images
│   └── js/              # Frontend JavaScript
│       └── bayarcash-checkout.js
├── includes/            # Core plugin classes
│   ├── BayarcashGateway.php
│   ├── BayarcashSettings.php
│   └── BayarcashProcessor.php
├── vendor/              # Composer dependencies (gitignored)
├── bayarcash-for-fluentcart.php  # Main plugin file
├── composer.json        # Composer configuration
└── README.md
```

### Hooks and Filters

The plugin uses FluentCart's standard payment gateway hooks:

- `fluent_cart/register_payment_methods` - Register the gateway
- Payment callbacks via FluentCart's API router

## Troubleshooting

### Composer Dependencies Not Installed

If you see an error about missing dependencies:

```bash
cd wp-content/plugins/bayarcash-for-fluentcart
composer install
```

### Payment Not Working

1. Verify API credentials are correct
2. Check payment mode (test/live) matches your credentials
3. Ensure webhook URL is configured in Bayarcash dashboard
4. Check WordPress error logs for detailed error messages

### Webhook Not Receiving Callbacks

1. Verify webhook URL is correctly configured in Bayarcash
2. Check that your server accepts POST requests to the webhook URL
3. Verify SSL certificate is valid if using HTTPS

## Support

For issues, questions, or contributions:

- GitHub Issues: [https://github.com/webimpian/bayarcash-for-fluentcart/issues](https://github.com/webimpian/bayarcash-for-fluentcart/issues)
- Bayarcash SDK: [https://github.com/webimpian/bayarcash-php-sdk](https://github.com/webimpian/bayarcash-php-sdk)

## License

GPL v2 or later

## Credits

- Developed by [Webimpian](https://webimpian.com)
- Uses [Bayarcash PHP SDK](https://github.com/webimpian/bayarcash-php-sdk)
- Built for [FluentCart](https://fluentcart.com)

## Changelog

### 1.0.0
- Initial release
- Direct payment support
- FPX and DuitNow QR support
- Test and Live mode
- Webhook integration
