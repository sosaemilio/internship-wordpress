<?php

namespace Nexcess\MAPPS\Commands;

use Nexcess\MAPPS\Integrations\BrainstormForce as Integration;
use WP_CLI;

/**
 * WP-CLI sub-commands for integrating with various Brainstorm Force plugins.
 */
class BrainstormForce {

	/**
	 * @var \Nexcess\MAPPS\Integrations\BrainstormForce
	 */
	protected $integration;

	/**
	 * @param \Nexcess\MAPPS\Integrations\BrainstormForce $integration
	 */
	public function __construct( Integration $integration ) {
		$this->integration = $integration;
	}

	/**
	 * Activate one of the Brainstorm licenses.
	 *
	 * ## OPTIONS
	 *
	 * <product>
	 * : The slug of the product.
	 *
	 * <license>
	 * : License to activate
	 *
	 * ## EXAMPLES
	 *
	 * $ wp nxmapps brainstormforce activate convertpro 366c6adcaf7dd1997c6f0268ad9d22f3
	 *
	 * Success: Activated <product> license.
	 *
	 * @since  1.0
	 *
	 * @access public
	 *
	 * @param array $args Top-level arguments.
	 */
	public function activate( $args ) {
		// Pull both parts of the args.
		list( $product_name, $license_key ) = $args;

		$result = $this->integration->activate( $product_name, $license_key );

		if ( ! is_wp_error( $result ) ) {
			WP_CLI::success( __( 'Success! The license has been activated.', 'nexcess-mapps' ) );
		} elseif ( ! empty( $result->get_error_message() ) ) {
			WP_CLI::error( $result->get_error_message() );
		} else {
			WP_CLI::error( __( 'Something went wrong. Please try again.', 'nexcess-mapps' ) );
		}
	}

	// Will eventually include a deactivate call
	// once we determine what that will look like.
}
