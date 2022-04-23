<?php

namespace Nexcess\MAPPS\Commands;

use Nexcess\MAPPS\Integrations\WooCommerceCartFragments;
use WP_CLI;

/**
 * WP-CLI sub-commands specific to WooCommerce sites.
 */
class WooCommerce extends Command {

	/**
	 * @var \Nexcess\MAPPS\Integrations\WooCommerceCartFragments
	 */
	private $integration;

	/**
	 * Create a new instance of the command.
	 *
	 * @param \Nexcess\MAPPS\Integrations\WooCommerceCartFragments $integration The WooCommerceCartFragments integration.
	 */
	public function __construct( WooCommerceCartFragments $integration ) {
		$this->integration = $integration;
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
				$this->integration->enableCartFragments();
				WP_CLI::success( 'WooCommerce Cart Fragments are enabled.' );
				break;
			case 'disable':
				$this->integration->disableCartFragments();
				WP_CLI::success( 'WooCommerce Cart Fragments are disabled.' );
				break;
			case 'status':
			default:
				if ( 'disabled' === $this->integration->getCartFragmentsSetting() ) {
					WP_CLI::log( 'WooCommerce cart fragments are currently being disabled via Nexcess MAPPS.' );
				} else {
					WP_CLI::log( 'Nexcess MAPPS is not currently preventing WooCommerce cart fragments from being used.' );
				}
				break;
		}
	}
}
