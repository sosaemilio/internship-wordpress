<?php

namespace Nexcess\MAPPS\Commands;

use WP_CLI;

/**
 * WP-CLI sub-commands for integrating with various Brainstorm Force plugins.
 */
class BrainstormForce {

	/**
	 * The option key that stores Brainstorm products.
	 *
	 * Please note that the option key is intentionally misspelled to match the typo
	 * in Brainstorm's framework.
	 */
	const OPTION_NAME = 'brainstrom_products';

	/**
	 * Get the URL for the Brainstorm stuff.
	 *
	 * @param string $product_name Which plugin is being worked with.
	 *
	 * @return string
	 */
	protected function get_brainstorm_api_url( $product_name = '' ) {

		// Set the API endpoint base.
		$set_base_endpoint = defined( 'BSF_API_URL' ) && BSF_API_URL ? BSF_API_URL : 'https://support.brainstormforce.com/';

		// Now set the base API route.
		$set_base_api_route = trailingslashit( $set_base_endpoint ) . 'wp-admin/admin-ajax.php';

		// Now set the license activation route and return it.
		return ! empty( $product_name )
			? add_query_arg( [
				'referer' => 'activate-' . sanitize_title( $product_name ),
			], $set_base_api_route )
			: $set_base_api_route;
	}

	/**
	 * Update all the data setup that Brainstorm uses in their plugins.
	 * This is very convoluted, but it's how Brainstorm handles it.
	 *
	 * @param string $active_product_slug The product slug used by Brainstorm.
	 * @param array  $api_response_args   The individual args we got from the API.
	 * @param string $license_key         The license key for the product we're doing.
	 */
	protected function update_brainstorm_product_info( $active_product_slug, $api_response_args, $license_key = '' ) {

		// Bail without the required arguments.
		if ( empty( $active_product_slug ) || empty( $api_response_args ) ) {
			return;
		}

		// Get the existing products we may have.
		$set_existing_products = get_option( self::OPTION_NAME, [] );

		// If we have no products, just return becase this won't work.
		if ( empty( $set_existing_products ) ) {
			return;
		}

		// Loop the existing products and parse out the items we want to handle.
		foreach ( $set_existing_products as $existing_product_type => $existing_products_of_type ) {

			// Skip this if we have no products of that type.
			if ( empty( $existing_products_of_type ) ) {
				continue;
			}

			// Now loop the existing products.
			foreach ( $existing_products_of_type as $existing_product_slug => $existing_product_args ) {

				// If they don't match, skip it.
				if ( $active_product_slug !== $existing_product_slug ) {
					continue;
				}

				// Add the license key back into the array.
				if ( ! isset( $api_response_args['purchase_key'] ) ) {
					$api_response_args['purchase_key'] = $license_key;
				}

				// Now loop the response args we had to map them into the data.
				foreach ( $api_response_args as $response_key => $response_value ) {

					// Add this long drawn out nested array.
					$set_existing_products[ $existing_product_type ][ $existing_product_slug ][ $response_key ] = $response_value;

					// Do the thing tied to the product update.
					do_action( "bsf_product_update_{$response_value}", $active_product_slug, $response_value );
				}

				// Nothing remaining inside the existing products of a type.
			}

			// Nothing remaining inside the existing products.
		}

		// Now update with our new array.
		update_option( self::OPTION_NAME, $set_existing_products );
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
	 * @access public
	 *
	 * @param array $args Top-level arguments.
	 */
	public function activate( $args ) {

		// Pull both parts of the args.
		list( $product_name, $license_key ) = $args;

		// Error if we don't have the name.
		if ( empty( $product_name ) ) {
			WP_CLI::error( __( 'No product name was provided.', 'nexcess-mapps' ) );
		}

		// Error if we don't have the license key.
		if ( empty( $license_key ) ) {
			WP_CLI::error( __( 'No license key was provided.', 'nexcess-mapps' ) );
		}

		// Get our API url endpoint.
		$set_license_route = $this->get_brainstorm_api_url( $product_name );

		// Purchase key length for EDD is 32 characters. This matters for some reason.
		$maybe_license_edd = 32 === strlen( $license_key );

		// Using Brainstorm API v2.
		// Removed the user name and email values, as they aren't required.
		$set_api_body_args = [
			'action'                   => 'bsf_activate_license',
			'purchase_key'             => $license_key,
			'product_id'               => sanitize_title( $product_name ),
			'user_name'                => '',
			'user_email'               => '',
			'privacy_consent'          => true,
			'terms_conditions_consent' => true,
			'site_url'                 => get_site_url(),
			'is_edd'                   => $maybe_license_edd,
			'referer'                  => 'customer',
		];

		// Set the post args.
		$set_api_post_args = [
			'body'    => $set_api_body_args,
			'timeout' => 30,
		];

		// Then make the actual call.
		$attempt_licensing = wp_remote_post( $set_license_route, $set_api_post_args );

		// Return if we have no return at all.
		if ( empty( $attempt_licensing ) ) {
			WP_CLI::error( __( 'The API returned a null response.', 'nexcess-mapps' ) );
		}

		// Return if we have a WP_Error object.
		if ( is_wp_error( $attempt_licensing ) ) {
			/* Translators: %1$s is the error message. */
			WP_CLI::error( sprintf( __( 'License could not be activated - Error: %1$s', 'nexcess-mapps' ), $attempt_licensing->get_error_message() ) );
		}

		// Get my response code.
		$get_response_code = wp_remote_retrieve_response_code( $attempt_licensing );

		// Filter the code against our min/max range.
		$maybe_valid_code = filter_var( $get_response_code, FILTER_VALIDATE_INT, [
			'options' => [
				'min_range' => 200,
				'max_range' => 299,
			],
		] );

		// Bail if the response code isn't what we want.
		if ( ! $maybe_valid_code ) {

			/* Translators: %1$s is the response code. */
			WP_CLI::error( sprintf( __( 'API Error - Code: %1$s', 'nexcess-mapps' ), $get_response_code ) );
		}

		// Parse out my body JSON.
		$parse_json_results = json_decode( wp_remote_retrieve_body( $attempt_licensing ), true );

		// If we don't have success, return that.
		if ( empty( $parse_json_results['success'] ) || true !== $parse_json_results['success'] ) {

			// Get my possible error message.
			$error_text = ! empty( $parse_json_results['message'] ) ? $parse_json_results['message'] : __( 'The API returned an error.', 'nexcess-mapps' );

			/* Translators: %1$s is the error message. */
			WP_CLI::error( $error_text );
		}

		// Remove the initial 'success'.
		unset( $parse_json_results['success'] );

		// Run the updates for product info.
		$this->update_brainstorm_product_info( $product_name, $parse_json_results, $license_key );

		// Now return our success message.
		WP_CLI::success( __( 'Success! The license has been activated.', 'nexcess-mapps' ) );
	}

	// Will eventually include a deactivate call
	// once we determine what that will look like.
}
