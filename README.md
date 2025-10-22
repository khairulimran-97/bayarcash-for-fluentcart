# Bayarcash for FluentCart

[![WordPress Plugin Version](https://img.shields.io/badge/version-1.0.0-blue.svg)](https://github.com/webimpian/bayarcash-for-fluentcart)
[![PHP Version](https://img.shields.io/badge/PHP-7.4%2B-purple.svg)](https://php.net)
[![License](https://img.shields.io/badge/license-GPL--2.0%2B-green.svg)](https://www.gnu.org/licenses/gpl-2.0.html)

Accept payments via Bayarcash payment gateway for FluentCart. Supports FPX, DuitNow QR, Credit Card, and other Malaysian payment methods.

## Description

Bayarcash for FluentCart is a comprehensive payment gateway integration that enables Malaysian businesses to accept online payments seamlessly. The plugin connects your FluentCart store with Bayarcash, providing access to 13+ popular payment channels in Malaysia.

## Features

- ✅ **Multiple Payment Channels** - FPX, DuitNow QR, Credit Card, and more
- ✅ **Secure Transactions** - Checksum validation for all payments
- ✅ **Real-time Callbacks** - Instant order updates via webhooks and return URLs
- ✅ **Test Mode** - Built-in sandbox credentials for testing
- ✅ **Customizable UI** - Customize button colors, text, and checkout theme
- ✅ **Complete Audit Trail** - All transaction metadata stored in order meta
- ✅ **Status Protection** - Prevents modification of paid/refunded orders
- ✅ **Payment Intent Tracking** - Maintains payment intent ID throughout lifecycle

## Supported Payment Channels

| Channel ID | Payment Method |
|------------|----------------|
| 1 | FPX (Online Banking) |
| 3 | FPX Direct Debit |
| 4 | FPX Line of Credit |
| 5 | DuitNow Online Banking/Wallets |
| 6 | DuitNow QR |
| 8 | Boost PayFlex |
| 9 | QRIS (Online Banking) |
| 10 | QRIS (E-Wallet) |
| 11 | NETS |
| 12 | Credit Card |
| 13 | Alipay |
| 14 | WeChat Pay |
| 15 | PromptPay |

## Requirements

- WordPress 5.0 or higher
- PHP 7.4 or higher
- FluentCart plugin (active)
- Bayarcash merchant account
- SSL certificate (HTTPS)

## Installation

1. Upload `bayarcash-for-fluentcart` folder to `/wp-content/plugins/`
2. Run `composer install` in the plugin directory to install dependencies
3. Activate the plugin through WordPress admin

## Configuration

### 1. Get API Credentials

1. Sign up at [Bayarcash](https://bayarcash.com)
2. Log in to your dashboard
3. Navigate to API Settings
4. Copy your credentials:
   - Personal Access Token (PAT)
   - API Secret Key
   - Portal Key

### 2. Configure Plugin

1. Go to **FluentCart → Settings → Payment Gateways**
2. Find "Bayarcash" and click configure
3. Enter your API credentials
4. Select payment channels to enable
5. Customize checkout appearance (optional)
6. Save settings

### 3. Test Mode

For testing, simply set your FluentCart store to **Test Mode**. The plugin will automatically use sandbox credentials.

## Usage

### Customer Checkout Flow

1. Customer selects Bayarcash at checkout
2. Customer chooses payment channel (FPX, DuitNow QR, etc.)
3. Customer clicks "Pay with Bayarcash"
4. Redirected to Bayarcash payment page
5. Completes payment
6. Redirected back to receipt page
7. Order status updated automatically

### Order Metadata

The plugin stores comprehensive transaction data:

- `bayarcash_transaction_id` - Bayarcash transaction ID
- `bayarcash_exchange_reference_number` - Exchange reference
- `bayarcash_exchange_transaction_id` - Bank transaction ID
- `bayarcash_status_description` - Payment status description
- `bayarcash_payment_gateway_id` - Payment channel used

### Transaction Tracking

- **Payment Intent ID** stored in `vendor_charge_id` (never changes)
- **Transaction ID** stored in order meta
- **Payment Channel** stored in `payment_method_type`

## Development

### File Structure

```
bayarcash-for-fluentcart/
├── assets/
│   ├── img/
│   │   └── bayarcash-icon.png
│   └── js/
│       └── bayarcash-checkout.js
├── includes/
│   ├── BayarcashGateway.php      # Main gateway class
│   ├── BayarcashProcessor.php     # Payment processing
│   └── BayarcashSettings.php      # Settings management
├── vendor/                         # Composer dependencies
├── bayarcash-for-fluentcart.php   # Main plugin file
├── composer.json
├── readme.txt                      # WordPress repo readme
└── README.md                       # This file
```

### Payment Flow

```
User Checkout
    ↓
Create Payment Intent → Store payment_intent_id in vendor_charge_id
    ↓
Redirect to Bayarcash
    ↓
User Completes Payment
    ↓
├─→ Callback (POST) → Update order meta → Mark as paid
└─→ Return URL (GET) → Update order meta → Redirect to receipt
```

### Hooks & Filters

Register custom payment method:
```php
add_action('fluent_cart/register_payment_methods', function() {
    $gateway = new \BayarcashForFluentCart\BayarcashGateway();
    fluent_cart_api()->registerCustomPaymentMethod('bayarcash', $gateway);
});
```

Process return URL:
```php
add_action('template_redirect', function() {
    $processor->handleReturn();
}, 5);
```

## API Integration

### Bayarcash SDK

The plugin uses [Bayarcash PHP SDK v2.0.5](https://github.com/webimpian/bayarcash-php-sdk):

```php
$client = new Bayarcash($apiToken);
$client->setApiVersion('v3');
$client->useSandbox(); // For test mode

$response = $client->createPaymentIntent($paymentData);
$isValid = $client->verifyTransactionCallbackData($data, $secret);
```

## Security

- ✅ Checksum validation for all callbacks
- ✅ Return URL verification
- ✅ Protected order status (paid/refunded)
- ✅ Sanitized input data
- ✅ HTTPS required for production
- ✅ WordPress nonce verification

## Support

- **Documentation**: [Bayarcash Docs](https://docs.bayarcash.com)
- **Issues**: [GitHub Issues](https://github.com/webimpian/bayarcash-for-fluentcart/issues)
- **Email**: support@webimpian.com

## Contributing

Contributions are welcome! Please feel free to submit a Pull Request.

## License

This plugin is licensed under the GPL v2 or later.

```
Copyright (C) 2024 Webimpian

This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 2 of the License, or
(at your option) any later version.
```

## Credits

Developed by [Webimpian](https://webimpian.com)

---

**Made with ❤️ for Malaysian businesses**
