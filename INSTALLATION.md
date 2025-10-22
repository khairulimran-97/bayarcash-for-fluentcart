# Installation & Setup Guide - Bayarcash for FluentCart

## Prerequisites

Before installing this plugin, ensure you have:

1. ✅ WordPress 5.0 or higher
2. ✅ PHP 7.4 or higher
3. ✅ FluentCart plugin installed and activated
4. ✅ Composer installed on your server
5. ✅ A Bayarcash account with API credentials

## Installation Steps

### Step 1: Install the Plugin

There are two ways to install the plugin:

#### Option A: Manual Installation (Recommended)

1. Download or clone this repository into your WordPress plugins directory:
   ```bash
   cd /path/to/wordpress/wp-content/plugins/
   git clone https://github.com/webimpian/bayarcash-for-fluentcart.git
   ```

2. Navigate to the plugin directory:
   ```bash
   cd bayarcash-for-fluentcart
   ```

3. Install Composer dependencies:
   ```bash
   composer install --no-dev --optimize-autoloader
   ```

#### Option B: Upload ZIP File

1. Download the plugin as a ZIP file
2. Extract and run `composer install` in the extracted folder
3. Re-zip the folder including the `vendor` directory
4. Upload via WordPress Admin → Plugins → Add New → Upload Plugin

### Step 2: Activate the Plugin

1. Go to WordPress Admin → Plugins
2. Find "Bayarcash for FluentCart"
3. Click "Activate"

### Step 3: Get Bayarcash API Credentials

1. Log in to your Bayarcash account at https://bayarcash.com
2. Navigate to Settings → API Credentials
3. Copy the following credentials:
   - **API Token** (for both test and live environments)
   - **API Secret Key** (for both test and live environments)
   - **Portal Key**

### Step 4: Configure the Payment Gateway

1. In WordPress Admin, go to **FluentCart → Settings → Payment Gateways**
2. Find **Bayarcash** in the list of payment methods
3. Click to open the settings panel
4. Configure the following settings:

#### Basic Settings

- **Payment Mode**: Select "Test Mode" for testing, "Live Mode" for production
- **Test API Token**: Enter your Bayarcash test API token
- **Test API Secret Key**: Enter your Bayarcash test API secret key
- **Live API Token**: Enter your Bayarcash live API token (when ready for production)
- **Live API Secret Key**: Enter your Bayarcash live API secret key (when ready for production)
- **Portal Key**: Enter your Bayarcash portal key

#### Display Settings

- **Payment Description**: Customize the description shown to customers (default: "Payment for Order #{{order_id}}")
- **Payment Button Label**: Customize the button text (default: "Pay with Bayarcash")

5. Copy the **Webhook URL** displayed at the bottom of the settings
6. Click **Save Settings**

### Step 5: Configure Webhook in Bayarcash Dashboard

1. Log in to your Bayarcash dashboard
2. Navigate to Settings → Webhooks
3. Add a new webhook with the URL copied from FluentCart settings
4. The webhook URL format will be:
   ```
   https://yoursite.com/?fluent_cart_payment_api=1&payment_method=bayarcash&action=callback
   ```
5. Save the webhook configuration

### Step 6: Test the Integration

#### Test Mode Testing

1. Create a test product in FluentCart
2. Add it to cart and proceed to checkout
3. Select "Bayarcash" as the payment method
4. Click "Pay with Bayarcash"
5. You should be redirected to Bayarcash's test payment page
6. Complete a test payment
7. Verify that you're redirected back to your site
8. Check that the order status is updated correctly in FluentCart

#### Production Checklist

Before going live, ensure:

- [ ] All test payments completed successfully
- [ ] Webhook is receiving and processing callbacks correctly
- [ ] Order statuses are updating properly
- [ ] Customer emails are being sent
- [ ] Payment mode is set to "Live Mode"
- [ ] Live API credentials are entered correctly
- [ ] SSL certificate is installed and working on your site

## Supported Payment Methods

Bayarcash for FluentCart supports all payment channels available in your Bayarcash account, including:

- ✅ FPX (Financial Process Exchange) - Online Banking
- ✅ DuitNow QR - Scan and Pay
- ✅ Other Malaysian payment methods

The available payment channels will be displayed on the Bayarcash payment page based on your account configuration.

## Troubleshooting

### Plugin Not Activating

**Issue**: Error message about missing dependencies

**Solution**: Make sure you've run `composer install` in the plugin directory

```bash
cd wp-content/plugins/bayarcash-for-fluentcart
composer install
```

### Payment Gateway Not Appearing

**Issue**: Bayarcash doesn't show in FluentCart payment gateways

**Solution**:
1. Ensure FluentCart is installed and activated
2. Deactivate and reactivate the Bayarcash for FluentCart plugin
3. Clear WordPress cache

### Payments Not Processing

**Issue**: Redirects to Bayarcash but payment fails

**Solution**:
1. Verify API credentials are correct
2. Check that you're using the correct mode (test/live) credentials
3. Ensure Portal Key is entered correctly
4. Check WordPress debug log for error messages

### Webhook Not Working

**Issue**: Payments succeed but order status doesn't update

**Solution**:
1. Verify webhook URL is correctly configured in Bayarcash dashboard
2. Check that your server accepts POST requests to the webhook URL
3. Ensure no firewall is blocking the webhook requests
4. Test webhook using Bayarcash's webhook test feature
5. Check WordPress error logs for webhook processing errors

### SSL Certificate Errors

**Issue**: Webhook failing with SSL errors

**Solution**:
1. Ensure your site has a valid SSL certificate
2. Test SSL using https://www.ssllabs.com/ssltest/
3. Contact your hosting provider if SSL issues persist

## Support & Documentation

- **Plugin Repository**: https://github.com/webimpian/bayarcash-for-fluentcart
- **Bayarcash SDK**: https://github.com/webimpian/bayarcash-php-sdk
- **FluentCart Documentation**: https://dev.fluentcart.com
- **Bayarcash Support**: Contact via your Bayarcash dashboard

## Updating the Plugin

### Via Git

```bash
cd wp-content/plugins/bayarcash-for-fluentcart
git pull origin main
composer install --no-dev --optimize-autoloader
```

### Manual Update

1. Deactivate the plugin
2. Delete the old plugin folder
3. Upload the new version
4. Run `composer install`
5. Reactivate the plugin

## Uninstallation

To completely remove the plugin:

1. Deactivate the plugin from WordPress Admin → Plugins
2. Click "Delete" on the plugin
3. Alternatively, manually delete the plugin folder from `wp-content/plugins/`

## Security Best Practices

1. **Never commit credentials to version control**
2. **Use strong, unique API credentials**
3. **Keep WordPress and FluentCart updated**
4. **Use HTTPS for your entire site**
5. **Regularly review order logs**
6. **Test in sandbox mode before going live**

## Need Help?

If you encounter any issues or need assistance:

1. Check this installation guide
2. Review the troubleshooting section
3. Check WordPress error logs
4. Open an issue on GitHub: https://github.com/webimpian/bayarcash-for-fluentcart/issues
