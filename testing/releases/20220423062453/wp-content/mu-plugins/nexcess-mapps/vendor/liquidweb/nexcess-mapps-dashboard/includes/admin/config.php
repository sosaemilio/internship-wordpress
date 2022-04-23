<?php
/**
 * Some basic configuration functions for the admin side.
 *
 * @package PlatformSelfInstall
 */

// Call our namepsace.
namespace Nexcess\MAPPS\Dashboard\Admin\Config;

// Set our alias items.
use Nexcess\MAPPS\Dashboard as Core;
use Nexcess\MAPPS\Dashboard\Helpers as Helpers;
use Nexcess\MAPPS\Dashboard\Utilities as Utilities;

/**
 * Start our engines.
 */
add_filter( 'pre_update_option', __NAMESPACE__ . '\override_aggressive_plugins', 99, 3 );
add_filter( 'admin_body_class', __NAMESPACE__ . '\load_admin_body_class' );
add_filter( 'http_request_args', __NAMESPACE__ . '\disable_update_args', 20, 2 );
// add_filter( 'removable_query_args', __NAMESPACE__ . '\add_removable_args' );

/**
 * Handle some options set by aggressive plugins.
 *
 * @param mixed   $value      The new, unserialized option value.
 * @param string  $option     Name of the option.
 * @param mixed   $old_value  The old option value.
 *
 * @return void
 */
function override_aggressive_plugins( $value, $option, $old_value ) {

	// Lets set up a case switch since I'm sure
	// we will have more of these at some point.
	switch ( $option ) {

		// Handle PrettyLinks redirect.
		case 'prli_onboard' :

			// Set my new value if we are attempting the welcome.
			$value = 'welcome' === $value ? 'complete' : $value;
			break;

		// Others will go here.
	}

	// Return whatever we have set.
	return $value;
}

/**
 * Include a custom body class on our admin tab.
 *
 * @param  string $classes  The current string of body classes.
 *
 * @return string $classes  The potentially modified string of body classes.
 */
function load_admin_body_class( $classes ) {

	// Confirm we are on the settings tab.
	if ( Utilities\check_admin_screen() ) {
		$classes .= ' nexcess-selfinstall-body';
	}

	// And send back the string.
	return $classes;
}

/**
 * Disable this plugin from checking for updates.
 *
 * @param  array  $request   The setup of the existing request.
 * @param  string $endpoint  The URL being used to update.
 *
 * @return array
 */
function disable_update_args( $request, $endpoint ) {
	if ( 0 !== strpos( $endpoint, 'https://api.wordpress.org/plugins/update-check/' ) ) {
		return $request;
	}

	$dataset = json_decode( $request['body']['plugins'], true );

	// We did not receive a valid JSON array.
	if ( ! $dataset ) {
		return $request;
	}

	// Remove the plugin from the list.
	unset( $dataset['plugins'][ Core\PLUGIN ] );

	if ( ! isset( $dataset['active'] ) ) {
		$dataset['active'] = [];
	} elseif ( ! empty( $dataset['active'] ) && is_array( $dataset['active'] ) ) {
		unset( $dataset['active'][ array_search( Core\PLUGIN, $dataset['active'] ) ] );
	}

	// Reset array keys and put the request back together.
	$dataset['active']          = array_values( $dataset['active'] );
	$request['body']['plugins'] = wp_json_encode( $dataset );

	// Return our request.
	return $request;
}

/**
 * Add our custom strings to the vars.
 *
 * @param  array $args  The existing array of args.
 *
 * @return array $args  The modified array of args.
 */
function add_removable_args( $args ) {

	// Set up the default args we wanna remove.
	$set_default_args   = array( 'nexcess-selfinstall', 'nexcess-installed', 'nexcess-iconic-activate', 'nexcess-debug', 'nexcess-run-licensing', 'errcode' );

	// Set my new args.
	$set_removable_args = apply_filters( Core\HOOK_PREFIX . 'removable_args', $set_default_args );

    // Include my new args and return.
	return wp_parse_args( $set_removable_args, $args );
}

