<?php

namespace Nexcess\MAPPS\Commands;

use Nexcess\MAPPS\Integrations\SalesPerformanceMonitor;
use Nexcess\MAPPS\Integrations\WooCommerceCartFragments;
use WP_CLI;

/**
 * WP-CLI sub-commands specific to WooCommerce sites.
 */
class WooCommerce extends Command {

	/**
	 * @var \Nexcess\MAPPS\Integrations\WooCommerceCartFragments
	 */
	private $WooCommerceCartFragments;

	/**
	 * @var \Nexcess\MAPPS\Integrations\SalesPerformanceMonitor
	 */
	private $SalesPerformanceMonitor;

	/**
	 * Create a new instance of the command.
	 *
	 * @param \Nexcess\MAPPS\Integrations\SalesPerformanceMonitor  $sales_performance_monitor  The SalesPerformanceMonitor integration.
	 * @param \Nexcess\MAPPS\Integrations\WooCommerceCartFragments $woocommerce_cart_fragments The WooCommerceCartFragments integration.
	 */
	public function __construct( SalesPerformanceMonitor $sales_performance_monitor, WooCommerceCartFragments $woocommerce_cart_fragments ) {
		$this->SalesPerformanceMonitor  = $sales_performance_monitor;
		$this->WooCommerceCartFragments = $woocommerce_cart_fragments;
	}
	/**
	 * Enable or Disable WooCommerce cart fragments.
	 *
	 * ## OPTIONS
	 *
	 * <enable|disable|status>
	 * : Enable, disable, or show the status of the cart fragments option.
	 *
	 * ## EXAMPLES
	 *
	 * $ wp nxmapps wc cart-fragments disable
	 * Success: WooCommerce Disable Cart Fragments are disabled. (good for perfomance)
	 *
	 * $ wp nxmapps wc cart-fragments enable
	 * Success: WooCommerce Cart Fragments are enabled. (not good for performance)
	 *
	 * @subcommand cart-fragments
	 *
	 * @param string[] $args Top-level arguments.
	 */
	public function cart_fragments( $args ) {
		switch ( $args[0] ) {
			case 'enable':
				$this->WooCommerceCartFragments->setCartFragmentsStatus( true );
				WP_CLI::success( 'WooCommerce Cart Fragments are enabled.' );
				break;
			case 'disable':
				$this->WooCommerceCartFragments->setCartFragmentsStatus( false );
				WP_CLI::success( 'WooCommerce Cart Fragments are disabled.' );
				break;
			case 'status':
			default:
				if ( 'disabled' === $this->WooCommerceCartFragments->getCartFragmentsSetting() ) {
					WP_CLI::log( 'WooCommerce cart fragments are currently being disabled via Nexcess MAPPS.' );
				} else {
					WP_CLI::log( 'Nexcess MAPPS is not currently preventing WooCommerce cart fragments from being used.' );
				}
				break;
		}
	}

	/**
	 * Enable or Disable WooCommerce Sales Performance Monitor.
	 *
	 * ## OPTIONS
	 *
	 * <enable|disable|status>
	 * : Enable, disable, or show the status of the Sales Performance Monitor option.
	 *
	 * ## EXAMPLES
	 *
	 * $ wp nxmapps wc spm disable
	 * Success: WooCommerce Sales Performance Monitor is disabled.
	 *
	 * $ wp nxmapps wc spm enable
	 * Success: WooCommerce Sales Performance Monitor is enabled.
	 *
	 * @subcommand spm
	 *
	 * @param string[] $args Top-level arguments.
	 */
	public function sales_performance_monitor( $args ) {
		switch ( $args[0] ) {
			case 'enable':
				$this->SalesPerformanceMonitor->enableSalesPerformanceMonitor();
				WP_CLI::success( 'WooCommerce Sales Performance Monitor is enabled.' );
				break;
			case 'disable':
				$this->SalesPerformanceMonitor->disableSalesPerformanceMonitor();
				WP_CLI::success( 'WooCommerce Sales Performance Monitor is disabled.' );
				break;
			case 'status':
			default:
				if ( $this->SalesPerformanceMonitor->getSalesPerformanceMonitorSetting() ) {
					WP_CLI::log( 'WooCommerce Sales Performance Monitor is enabled.' );
				} else {
					WP_CLI::log( 'Sales Performance Monitor is disabled' );
				}
				break;
		}
	}
}
