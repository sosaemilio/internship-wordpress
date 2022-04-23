<?php
/**
 * Plugin Name: WooCommerce Upper Limits
 * Plugin URI:  https://www.liquidweb.com/products/managed-woocommerce-hosting/
 * Description: Create upper limits for store owners, limiting either the amount of products they can create, or orders they can take in a given month.
 * Version:     1.0.3
 * Author:      Liquid Web
 * Author URI:  https://www.liquidweb.com
 * License:     MIT
 * Text Domain: woocommerce-upper-limits
 * Domain Path: /languages
 *
 * WC requires at least: 3.4.4
 * WC tested up to:      3.6
 *
 * @package LiquidWeb/WooCommerceUpperLimits
 */

namespace LiquidWeb\WooCommerceUpperLimits;

/**
 * Define plugin constants.
 */
define( __NAMESPACE__ . '\OPTION_KEY', 'wc_constraint_type' );

/**
 * Load additional files.
 */
require_once __DIR__ . '/includes/constraints/class-abstract-constraint.php';
require_once __DIR__ . '/includes/constraints/class-order-constraint.php';
require_once __DIR__ . '/includes/constraints/class-product-constraint.php';
require_once __DIR__ . '/includes/constraints.php';
require_once __DIR__ . '/includes/exceptions/class-missing-constraint-exception.php';

/**
 * Initialize the plugin.
 *
 * Since we depend on WooCommerce, ensure the plugin files are not loaded until we reach the
 * plugins_loaded action.
 */
function bootstrap_plugin() {
	require_once __DIR__ . '/includes/class-integration.php';
	require_once __DIR__ . '/includes/dashboard.php';
	require_once __DIR__ . '/includes/helpers.php';

	// Load translated strings for plugin.
	load_plugin_textdomain(
		'woocommerce-upper-limits',
		false,
		plugin_basename( __FILE__ ) . '/languages/'
	);

	// Register the WooCommerce integration.
	add_filter( 'woocommerce_integrations', __NAMESPACE__ . '\Integration::register_integration' );
}
add_action( 'woocommerce_loaded', __NAMESPACE__ . '\bootstrap_plugin' );
