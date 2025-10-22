<?php
/**
 * Plugin Name: Bayarcash for FluentCart
 * Plugin URI: https://github.com/webimpian/bayarcash-php-sdk
 * Description: Accept payments via Bayarcash payment gateway for FluentCart
 * Version: 1.0.0
 * Author: Webimpian
 * Author URI: https://webimpian.com
 * Text Domain: bayarcash-for-fluentcart
 * Domain Path: /languages
 * Requires at least: 5.0
 * Requires PHP: 7.4
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */

defined('ABSPATH') or exit;

define('BAYARCASH_FC_VERSION', '1.0.0');
define('BAYARCASH_FC_DIR', plugin_dir_path(__FILE__));
define('BAYARCASH_FC_URL', plugin_dir_url(__FILE__));

/**
 * Load Composer autoloader
 */
if (!file_exists(BAYARCASH_FC_DIR . 'vendor/autoload.php')) {
    add_action('admin_notices', function() {
        echo '<div class="notice notice-error"><p>';
        printf(
            __('Bayarcash for FluentCart: Composer dependencies are missing. Please run <code>composer install</code> in the plugin directory.', 'bayarcash-for-fluentcart')
        );
        echo '</p></div>';
    });
    return; // Stop plugin execution if autoloader is missing
}

require_once BAYARCASH_FC_DIR . 'vendor/autoload.php';

/**
 * Initialize the Bayarcash Payment Gateway
 */
add_action('fluent_cart/register_payment_methods', function() {
    // Check if FluentCart is active
    if (!function_exists('fluent_cart_api')) {
        return;
    }

    // Register the gateway - Composer autoloader will handle class loading
    fluent_cart_api()->registerCustomPaymentMethod(
        'bayarcash',
        new \BayarcashForFluentCart\BayarcashGateway()
    );
});

/**
 * Add settings link on plugins page
 */
add_filter('plugin_action_links_' . plugin_basename(__FILE__), function($links) {
    $settings_link = '<a href="' . admin_url('admin.php?page=fluent-cart#/settings/payment-gateways') . '">' . __('Settings', 'bayarcash-for-fluentcart') . '</a>';
    array_unshift($links, $settings_link);
    return $links;
});

/**
 * Check if FluentCart is installed and active
 */
add_action('admin_notices', function() {
    if (!defined('FLUENT_CART_VERSION')) {
        echo '<div class="notice notice-error"><p>';
        echo __('Bayarcash for FluentCart requires FluentCart to be installed and activated.', 'bayarcash-for-fluentcart');
        echo '</p></div>';
    }
});
