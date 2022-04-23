<?php
/**
 * Any external API calls or other data retrievals.
 *
 * @package PlatformSelfInstall
 */

// Call our namepsace.
namespace Nexcess\MAPPS\Dashboard\External;

// Set our alias items.
use Nexcess\MAPPS\Dashboard as Core;
use Nexcess\MAPPS\Dashboard\Helpers as Helpers;
use Nexcess\MAPPS\Dashboard\Utilities as Utilities;
use Nexcess\MAPPS\Dashboard\Datasets as Datasets;

// And pull in any other namespaces.
use WP_Error;

/**
 * Go and fetch the list of available plugins.
 *
 * @return array
 */
function fetch_available_plugins_list() {

	// Set my request args.
	$request_args   = build_shared_request_args();

	// Set up the endpoint.
	$set_endpoint   = sprintf( '%1$s/api/v1/app-plugin/', NEXCESS_MAPPS_ENDPOINT );

	// Make our API request.
	$make_request   = wp_remote_get( esc_url( $set_endpoint ), $request_args );

	// Run our confirmation function and return the result.
	return confirm_api_response_args( $make_request );
}

/**
 * Get the install instructions for a single plugin based on the ID.
 *
 * @param  integer $plugin_id  Our plugin ID.
 *
 * @return array
 */
function fetch_single_plugin_install_instructions( $plugin_id = 0 ) {

	// Bail without our required plugin ID.
	if ( empty( $plugin_id ) ) {
		return new WP_Error( 'missing_required_id', __( 'The required plugin ID was not provided.', 'nexcess-mapps-dashboard' ) );
	}

	// Set my request args.
	$request_args   = build_shared_request_args();

	// Set up the endpoint.
	$set_endpoint   = sprintf( '%1$s/api/v1/app-plugin/%2$d/install', NEXCESS_MAPPS_ENDPOINT, $plugin_id );

	// Make our API request.
	$make_request   = wp_remote_get( esc_url( $set_endpoint ), $request_args );

	// Run our confirmation function and return the result.
	return confirm_api_response_args( $make_request );
}

/**
 * Get the licensing instructions for a single plugin based on the ID.
 *
 * @param  integer $plugin_id  Our plugin ID.
 *
 * @return array
 */
function fetch_single_plugin_license_instructions( $plugin_id = 0 ) {

	// Bail without our required plugin ID.
	if ( empty( $plugin_id ) ) {
		return new WP_Error( 'missing_required_id', __( 'The required plugin ID was not provided.', 'nexcess-mapps-dashboard' ) );
	}

	// Set my request args.
	$request_args   = build_shared_request_args();

	// Set up the endpoint.
	$set_endpoint   = sprintf( '%1$s/api/v1/app-plugin/%2$d/license', NEXCESS_MAPPS_ENDPOINT, $plugin_id );

	// Make our API request.
	$make_request   = wp_remote_get( esc_url( $set_endpoint ), $request_args );

	// Run our confirmation function and return the result.
	return confirm_api_response_args( $make_request );
}

/**
 * Set up the request args we use everywhere.
 *
 * @return array
 */
function build_shared_request_args() {

	// Set my header request.
	$headers_build  = array(
		'Accept'        => 'application/json',
		'X-MAAPI-TOKEN' => NEXCESS_MAPPS_TOKEN,
	);

	// Set an empty body.
	$content_build  = array();

	// Set my request args and return.
	return array(
		'user-agent'  => 'WordPress/' . get_option( 'db_version' ) . '; ' . home_url(),
		'headers'     => $headers_build,
		'body'        => $content_build,
		'httpversion' => '1.1',
		'timeout'     => 30,
	);
}

/**
 * The checks we do for every API call.
 *
 * @param  mixed $make_request  The result of the request we just made.
 *
 * @return mixed
 */
function confirm_api_response_args( $make_request ) {

	// Return if we have no return.
	if ( empty( $make_request ) ) {
		return new WP_Error( 'no_api_response', __( 'The API returned a null response.', 'nexcess-mapps-dashboard' ) );
	}

	// Return if we have a WP_Error object.
	if ( is_wp_error( $make_request ) ) {
		return new WP_Error( 'wp_error_response', esc_html( $make_request->get_error_message() ) );
	}

	// Check our response code.
	$response_code  = wp_remote_retrieve_response_code( $make_request );

	// Bail without a 200 response code.
	if ( 200 !== absint( $response_code ) ) {
		return new WP_Error( 'bad_http_response', sprintf( __( 'The API returned a %d response code.', 'nexcess-mapps-dashboard' ), $response_code ) );
	}

	// Check for the body.
	$request_body   = wp_remote_retrieve_body( $make_request );

	// Make sure we have the body.
	if ( empty( $request_body ) ) {
		return new WP_Error( 'empty_body_return', __( 'The API response body was empty.', 'nexcess-mapps-dashboard' ) );
	}

	// Pull the guts.
	$request_guts   = json_decode( $request_body, true );

	// Make sure we have the guts.
	if ( empty( $request_guts ) ) {
		return new WP_Error( 'no_decoded_return', __( 'The API response could not properly be decoded.', 'nexcess-mapps-dashboard' ) );
	}

	// Return our guts.
	return $request_guts;
}
