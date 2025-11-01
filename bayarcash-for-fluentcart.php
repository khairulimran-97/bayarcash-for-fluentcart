<?php
/**
 * Plugin Name: Bayarcash for FluentCart
 * Plugin URI: https://plugin.bayarcash.com/
 * Description: Accept payments via Bayarcash payment gateway for FluentCart
 * Version: 1.0.0
 * Author: Bayarcash (by Web Impian Sdn Bhd)
 * Author URI: https://bayarcash.com
 * Text Domain: bayarcash-for-fluentcart
 * Requires at least: 5.0
 * Requires PHP: 7.4
 * Requires Plugins: fluent-cart
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */

defined('ABSPATH') or exit;

define('BAYARCASH_FC_VERSION', '1.0.0');
define('BAYARCASH_FC_DIR', plugin_dir_path(__FILE__));
define('BAYARCASH_FC_URL', plugin_dir_url(__FILE__));

if (!file_exists(BAYARCASH_FC_DIR . 'vendor/autoload.php')) {
    add_action('admin_notices', function() {
        echo '<div class="notice notice-error"><p>';
        echo wp_kses_post(
            __('Bayarcash for FluentCart: Composer dependencies are missing. Please run <code>composer install</code> in the plugin directory.', 'bayarcash-for-fluentcart')
        );
        echo '</p></div>';
    });
    return;
}

require_once BAYARCASH_FC_DIR . 'vendor/autoload.php';

add_action('fluent_cart/register_payment_methods', function() {
    if (!function_exists('fluent_cart_api')) {
        return;
    }

    $gateway = new \BayarcashForFluentCart\BayarcashGateway();
    fluent_cart_api()->registerCustomPaymentMethod('bayarcash', $gateway);
});

add_action('template_redirect', function() {
    $settings = new \BayarcashForFluentCart\BayarcashSettings();
    $processor = new \BayarcashForFluentCart\BayarcashProcessor($settings);
    $processor->handleReturn();
}, 5);

add_filter('plugin_action_links_' . plugin_basename(__FILE__), function($links) {
    $settings_link = '<a href="' . admin_url('admin.php?page=fluent-cart#/settings/payments') . '">' . __('Settings', 'bayarcash-for-fluentcart') . '</a>';
    array_unshift($links, $settings_link);
    return $links;
});

add_action('admin_notices', function() {
    if (!defined('FLUENTCART_VERSION')) {
        echo '<div class="notice notice-error"><p>';
        echo esc_html__('Bayarcash for FluentCart requires FluentCart to be installed and activated.', 'bayarcash-for-fluentcart');
        echo '</p></div>';
    }
});
