<?php

/**
 * General integration for Managed WooCommerce sites.
 */

namespace Nexcess\MAPPS\Integrations;

use Nexcess\MAPPS\Concerns\HasHooks;
use Nexcess\MAPPS\Settings;
use WC_Install;
use WC_Shipping_Zone;
use WC_Shipping_Zones;
use WP_Post;

class WooCommerce extends Integration {
	use HasHooks;

	/**
	 * @var \Nexcess\MAPPS\Settings
	 */
	protected $settings;

	/**
	 * The cache key used for determining whether or not a store has orders.
	 */
	const HAS_ORDERS_KEY = 'store_has_orders';

	/**
	 * @param \Nexcess\MAPPS\Settings $settings
	 */
	public function __construct( Settings $settings ) {
		$this->settings = $settings;
	}

	/**
	 * Determine whether or not this integration should be loaded.
	 *
	 * @return bool Whether or not this integration be loaded in this environment.
	 */
	public function shouldLoadIntegration() {
		return $this->settings->is_mwch_site
			|| $this->settings->is_storebuilder;
	}

	/**
	 * Retrieve all actions for the integration.
	 *
	 * @return array[]
	 */
	protected function getActions() {
		return [
			[ 'load-woocommerce_page_wc-admin', [ $this, 'enqueueAdminScript' ] ],
		];
	}

	/**
	 * Retrieve all filters for the integration.
	 *
	 * @return array[]
	 */
	protected function getFilters() {
		return [
			[ 'woocommerce_background_image_regeneration', '__return_false' ],

			// Clear caches when orders are created or updated.
			[ 'save_post_shop_order', [ $this, 'saveShopOrder' ], 10, 2 ],
		];
	}

	/**
	 * Create the default WooCommerce pages.
	 */
	public function createDefaultPages() {
		WC_Install::create_pages();
	}

	/**
	 * Create default shipping zones.
	 *
	 * If shipping zones are already defined, this method will not create new ones.
	 */
	public function createDefaultShippingZones() {
		if ( ! empty( WC_Shipping_Zones::get_zones() ) ) {
			return;
		}

		$domestic = new WC_Shipping_Zone( null );
		$domestic->set_zone_order( 0 );
		$domestic->add_location( WC()->countries->get_base_country(), 'country' );
		$domestic->add_shipping_method( 'free_shipping' );

		// Set a default shipping method for zone 0 (e.g. everywhere not otherwise covered).
		$zone_zero = new WC_Shipping_Zone( 0 );
		$zone_zero->add_shipping_method( 'free_shipping' );
	}

	/**
	 * Enqueue the Nexcess admin script after its defined.
	 */
	public function enqueueAdminScript() {
		add_action( 'admin_enqueue_scripts', function () {
			wp_enqueue_script( 'nexcess-mapps-admin' );
		} );
	}

	/**
	 * Get the default options to set for new stores.
	 *
	 * @return mixed[]
	 */
	public function getDefaultOptions() {
		return [
			'woocommerce_default_country'              => 'US:AL',
			'woocommerce_currency'                     => 'USD',
			'woocommerce_weight_unit'                  => 'lbs',
			'woocommerce_dimension_unit'               => 'in',
			'woocommerce_allow_tracking'               => 'no',
			'woocommerce_admin_notices'                => [],
			'woocommerce_product_type'                 => 'physical',
			'woocommerce_show_marketplace_suggestions' => 'no',
			'woocommerce_api_enabled'                  => 'yes',
		];
	}

	/**
	 * Trigger a refresh of the cached value of storeHasOrders().
	 *
	 * @param int      $id   The order ID.
	 * @param \WP_Post $post The order object.
	 */
	public function saveShopOrder( $id, WP_Post $post ) {
		$has_orders = wp_cache_get( self::HAS_ORDERS_KEY, 'nexcess-mapps', false, $is_cached );

		// When trashing a post, clear the cache value (if it exists) so it can be re-calculated.
		if ( 'trash' === $post->post_status ) {
			if ( $is_cached ) {
				wp_cache_delete( self::HAS_ORDERS_KEY, 'nexcess-mapps' );
			}

			return;
		}

		// If we've made it this far, the shop has orders.
		if ( ! $has_orders ) {
			wp_cache_set( self::HAS_ORDERS_KEY, true, 'nexcess-mapps' );
		}
	}
}
