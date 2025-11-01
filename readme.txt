=== Bayarcash for FluentCart ===
Contributors: bayarcash
Tags: bayarcash, payment gateway, fluentcart, fpx, duitnow
Requires at least: 5.0
Tested up to: 6.8
Stable tag: 1.0.0
Requires PHP: 7.4
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Accept payments via Bayarcash payment gateway for FluentCart. Supports FPX, DuitNow QR, and other Malaysian payment methods.

== Description ==

Bayarcash for FluentCart is a powerful payment gateway integration that enables Malaysian businesses to accept online payments through FluentCart. This plugin seamlessly connects your FluentCart store with Bayarcash, providing access to multiple popular payment channels in Malaysia.

= Key Features =

* **Multiple Payment Channels** - Support for FPX (Online Banking), DuitNow QR, Credit Card, and more
* **Secure Transactions** - All transactions are verified with checksum validation
* **Real-time Callbacks** - Instant order status updates via webhooks
* **Test Mode** - Built-in sandbox mode for testing before going live
* **Customizable Checkout** - Customize button colors, text, and themes
* **Order Metadata** - Stores all transaction details for complete audit trail
* **Status Management** - Automatic order status updates based on payment status

= Supported Payment Channels =

* FPX (Online Banking)
* FPX Direct Debit
* FPX Line of Credit
* DuitNow Online Banking/Wallets
* DuitNow QR
* Boost PayFlex
* QRIS (Online Banking & E-Wallet)
* NETS
* Credit Card
* Alipay
* WeChat Pay
* PromptPay

= API & SDK Information =

This plugin integrates with external services to process payments:

**Bayarcash Payment API v3**
* Service: Bayarcash Payment Gateway (https://bayarcash.com)
* API Documentation: https://docs.bayarcash.com
* Purpose: Process payment transactions and handle payment callbacks
* Service Terms: https://bayarcash.com/terms
* Privacy Policy: https://bayarcash.com/privacy

**SDK Used:**
* Bayarcash PHP SDK v2.0.5
* Repository: https://github.com/webimpian/bayarcash-php-sdk
* License: MIT License

= Data Collection & Privacy =

**What data is sent to Bayarcash:**

When a customer makes a payment, the following information is transmitted to Bayarcash's secure servers:

* Order ID and amount
* Customer name and email address
* Customer phone number
* Selected payment channel
* Return URL and callback URL

**Data Processing:**
* All payment data is transmitted securely via HTTPS
* Payment information is processed by Bayarcash in accordance with their privacy policy
* Transaction IDs and payment status are returned and stored in your WordPress database
* No credit card details are stored on your server - all sensitive payment data is handled by Bayarcash

**Callbacks & Webhooks:**
* Bayarcash sends payment status updates to your site via secure callbacks
* All callbacks are verified using cryptographic checksums to prevent tampering
* Your site stores transaction metadata (transaction IDs, status, payment channel) for order management

By using this plugin, you acknowledge that customer payment data will be transmitted to Bayarcash for processing. Please ensure your privacy policy reflects this third-party data processing.

= Requirements =

* FluentCart plugin (active)
* Bayarcash merchant account
* PHP 7.4 or higher
* WordPress 5.0 or higher

== Installation ==

1. Upload the `bayarcash-for-fluentcart` folder to the `/wp-content/plugins/` directory
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Navigate to FluentCart > Settings > Payments (/wp-admin/admin.php?page=fluent-cart#/settings/payments)
4. Configure your Bayarcash API credentials
5. Select your preferred payment channels
6. Save settings and start accepting payments

= Getting API Credentials =

1. Sign up for a Bayarcash merchant account at https://bayarcash.com
2. Log in to your Bayarcash dashboard
3. Navigate to API Settings
4. Copy your Personal Access Token (PAT), API Secret Key, and Portal Key
5. Paste these credentials in the plugin settings

== Frequently Asked Questions ==

= Do I need a Bayarcash account? =

Yes, you need an active Bayarcash merchant account to use this plugin. Sign up at https://bayarcash.com

= Does this work with FluentCart? =

Yes, this plugin is specifically designed for FluentCart and requires FluentCart to be installed and activated.

= Can I test payments before going live? =

Yes, the plugin includes a test mode with sandbox credentials. Simply set your FluentCart store to test mode.

= Which payment methods are supported? =

The plugin supports all payment channels offered by Bayarcash including FPX, DuitNow QR, Credit Card, and more. You can enable/disable specific channels in the settings.

= Is it secure? =

Yes, all transactions are secured with checksum validation and use HTTPS. Payment data is processed directly by Bayarcash's secure servers.

= What happens if a payment fails? =

Failed payments are automatically logged and the order status is updated accordingly. Customers are redirected back to your store with appropriate error messages.

= What data is sent to external services? =

This plugin sends payment transaction data to Bayarcash (https://bayarcash.com) for processing payments. This includes order details, customer information (name, email, phone), and payment amount. All data is transmitted securely via HTTPS and validated with cryptographic checksums. Please refer to Bayarcash's privacy policy for information on how they handle payment data.

= Does this plugin comply with privacy regulations? =

The plugin itself does not collect or store any personal data beyond what FluentCart already collects. Payment processing is handled by Bayarcash, a PCI-DSS compliant payment gateway. However, as the site owner, you are responsible for disclosing to your customers that their payment data will be processed by Bayarcash. Please update your privacy policy accordingly.

== Screenshots ==

1. Admin settings page with API configuration
2. Payment channel selection
3. Frontend checkout with Bayarcash payment options
4. Order details showing Bayarcash transaction information

== Changelog ==

= 1.0.0 =
* Initial release
* Support for multiple Bayarcash payment channels
* Test and live mode support
* Customizable checkout experience
* Real-time payment callbacks
* Complete transaction metadata storage
* Order status protection for paid orders

== Upgrade Notice ==

= 1.0.0 =
Initial release of Bayarcash for FluentCart.
