<?php
/**
 * Define the WooCommerce integration.
 *
 * @link https://docs.woocommerce.com/document/implementing-wc-integration/
 *
 * @package LiquidWeb/WooCommerceUpperLimits
 */

namespace LiquidWeb\WooCommerceUpperLimits;

use LiquidWeb\WooCommerceUpperLimits\Constraints\OrderConstraint;
use LiquidWeb\WooCommerceUpperLimits\Constraints\ProductConstraint;
use WC_Integration;
use const LiquidWeb\WooCommerceUpperLimits\OPTION_KEY;

class Integration extends WC_Integration {

	/**
	 * The key used to prevent changes to the option.
	 */
	const LOCK_KEY = '_wc_constraint_lock';

	/**
	 * Define the integration and hook into WooCommerce.
	 */
	public function __construct() {
		$this->id                 = 'woocommerce-upper-limits';
		$this->method_title       = __( 'Liquid Web Managed WooCommerce Hosting Beginner Plan', 'woocommerce-upper-limits' );
		$this->method_description = __( 'Here is the "plan type" you\'re on. If you need to change it, you can change it one time.', 'woocommerce-upper-limits' );

		// Load the settings.
		$this->init_form_fields();
		$this->init_settings();

		// Ensure our custom fields will be saved.
		add_action( 'woocommerce_update_options_integration_' . $this->id, [ $this, 'process_admin_options' ] );
		add_filter( 'woocommerce_settings_api_sanitized_fields_' . $this->id, 'current' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function init_form_fields() {
		$product  = new ProductConstraint();
		$order    = new OrderConstraint();
		$post_key = sprintf( 'woocommerce_%s_%s', $this->id, OPTION_KEY );

		// phpcs:disable WordPress.Security.NonceVerification.NoNonceVerification
		$this->form_fields = [
			OPTION_KEY => [
				'title'             => _x( 'Plan Type', 'section title', 'woocommerce-upper-limits' ),
				'label'             => _x( 'Plan Type', 'form label', 'woocommerce-upper-limits' ),
				'type'              => 'select',
				'options'           => Constraints\get_available_constraints(),
				'default'           => Constraints\get_constraint(),
				'disabled'          => (bool) get_option( self::LOCK_KEY, false ) || isset( $_POST[ $post_key ] ),
				'sanitize_callback' => [ $this, 'sanitize_selection' ],
				'description'       => sprintf(
					/* Translators: %1$d is the product limit, %2$d is the order limit. */
					__( 'On the beginner plan, your site may either have unlimited orders and up to %1$d products in your catalog <em>or</em> unlimited products with up to %2$d orders per month.', 'woocommerce-upper-limits' ),
					$product->get_limit(),
					$order->get_limit()
				),
			],
		];
		// phpcs:enable WordPress.Security.NonceVerification.NoNonceVerification
	}

	/**
	 * {@inheritDoc}
	 */
	public function process_admin_options() {
		$result = parent::process_admin_options();

		// Lock the option from future changes.
		update_option( self::LOCK_KEY, time(), false );

		return $result;
	}

	/**
	 * Sanitize the value before storing it.
	 *
	 * @param string $value The field value.
	 *
	 * @return string The potentially-filtered value.
	 */
	public function sanitize_selection( $value ) {
		$locked = (bool) get_option( self::LOCK_KEY, false );

		// If the option already exists, it cannot be changed.
		if ( $locked ) {
			return get_option( $this->get_option_key(), false );
		}

		// Check against the whitelisted constraints.
		$permitted = array_keys( Constraints\get_available_constraints() );

		return in_array( $value, $permitted, true ) ? sanitize_text_field( $value ) : '';
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_option_key() {
		return OPTION_KEY;
	}

	/**
	 * Register integration within WooCommerce.
	 *
	 * @param array $integrations Existing WooCommerce integrations.
	 *
	 * @return array The filtered $integrations array.
	 */
	public static function register_integration( $integrations ) {
		$integrations[] = __CLASS__;

		return $integrations;
	}
}
