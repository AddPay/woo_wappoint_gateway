<?php
/**
 * Plugin Name: WooCommerce WapPoint Gateway
 * Plugin URI: http://github.com/addpay/woo_wappoint_gateway
 * Description: Receive payments using the WapPoint payments provider.
 * Author: WapPoint
 * Author URI: https://www.wappoint.co.za/
 * Developer: Richard Slabbert/Stephen Lake
 * Developer URI: https://www.wappoint.co.za/
 * Version: 2.5.14
 */

if (! defined('ABSPATH')) {
    exit;
}

// Test to see if WooCommerce is active (including network activated).
$plugin_path = trailingslashit( WP_PLUGIN_DIR ) . 'woocommerce/woocommerce.php';

if (
    in_array( $plugin_path, wp_get_active_and_valid_plugins() )
    || in_array( $plugin_path, wp_get_active_network_plugins() )
) {
    // woocommerce is active
    add_action('plugins_loaded', 'wcagw_init', 0);
    add_filter('plugin_action_links_' . plugin_basename(__FILE__), 'wcagw_plugin_links');

}

/**
 * Initialize the gateway.
 * @since 1.0.0
 */
function wcagw_init()
{
    if (! class_exists('WC_Payment_Gateway')) {
        return;
    }
    require_once(plugin_basename('includes/class-wc-gateway-wappoint.php'));
    load_plugin_textdomain('wcagw-payment-gateway', false, trailingslashit(dirname(plugin_basename(__FILE__))));
    add_filter('woocommerce_payment_gateways', 'wcagw_add_gateway');
}

function wcagw_plugin_links($links)
{
    $settings_url = add_query_arg(
        array(
            'page' => 'wc-settings',
            'tab' => 'checkout',
            'section' => 'wc_gateway_wappoint',
        ),
        admin_url('admin.php')
    );

    $plugin_links = array(
        '<a href="' . esc_url($settings_url) . '">' . __('Settings', 'wcagw-payment-gateway') . '</a>',
        '<a href="https://support.woothemes.com/">' . __('Support', 'wcagw-payment-gateway') . '</a>',
        '<a href="https://docs.woothemes.com/document/addpay-payment-gateway/">' . __('Docs', 'wcagw-payment-gateway') . '</a>',
    );

    return array_merge($plugin_links, $links);
}
add_filter('plugin_action_links_' . plugin_basename(__FILE__), 'wcagw_plugin_links');


/**
 * Add the gateway to WooCommerce
 * @since 1.0.0
 */
function wcagw_add_gateway($methods)
{
    $methods[] = 'WCAGW_Gateway';
    return $methods;
}
