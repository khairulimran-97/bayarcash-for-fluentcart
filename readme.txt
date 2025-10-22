=== Bayarcash for FluentCart ===
Contributors: webimpian
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
