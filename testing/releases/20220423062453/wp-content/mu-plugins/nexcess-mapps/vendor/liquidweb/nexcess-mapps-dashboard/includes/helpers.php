<?php
/**
 * Our helper functions to use across the plugin.
 *
 * @package PlatformSelfInstall
 */

// Call our namepsace.
namespace Nexcess\MAPPS\Dashboard\Helpers;

// Set our alias items.
use Nexcess\MAPPS\Dashboard as Core;
use Nexcess\MAPPS\Dashboard\Utilities as Utilities;
use Nexcess\MAPPS\Dashboard\Datasets as Datasets;

/**
 * Get the condensed list of plugin groups from the full array.
 *
 * @param  array  $plugin_dataset  The source dataset.
 *
 * @return array
 */
function get_plugin_groups( $plugin_dataset = array() ) {

	// Get our groups first.
	$plucked_groups = wp_list_pluck( $plugin_dataset, 'group' );

	// Sort them.
	asort( $plucked_groups );

	// Parse out the values in the array.
	$group_values   = array_values( $plucked_groups );

	// Filter down to the unique.
	$group_unique   = array_unique( $group_values );

	// Return the unique list.
	return array_values( $group_unique );
}

/**
 * Get the list of the installed plugins.
 *
 * @return array|false
 */
function get_current_installed_plugins() {

	// Fetch the installed list, which has a transient on the query itself.
	$return_dataset = Datasets\build_current_installed_plugins();

	// Make sure we have some kind of dataset to use.
	return ! empty( $return_dataset ) ? $return_dataset : false;
}

/**
 * Get the list of activated plugins.
 *
 * @return array|false
 */
function get_current_activated_plugins() {

	// Fetch the activated list, which has a transient on the query itself.
	$return_dataset = Datasets\build_current_activated_plugins();

	// Make sure we have some kind of dataset to use.
	return ! empty( $return_dataset ) ? $return_dataset : false;
}

/**
 * Handle our edge-case setup for the Beaver Builder dependency.
 *
 * @param  array $active_plugins  The plugins currently installed and active.
 *
 * @return mixed
 */
function maybe_standardize_bb_plugins( $active_plugins = array() ) {

	// Set our currently installed and activated plugins.
	$active_plugins = ! empty( $active_plugins ) ? $active_plugins : get_current_activated_plugins();

	// If either of the non-standard ones are in the current, add the standard slug to the array.
	if ( in_array( 'beaver-builder-lite-version', $active_plugins ) || in_array( 'bb-plugin-agency', $active_plugins ) ) {
		$active_plugins[] = 'bb-plugin';
	}

	// Return the resulting array.
	return $active_plugins;
}

/**
 * Check the current plugin args to see if there are any missing dependcies.
 *
 * @param  string $plugin_slug  The slug of the plugin being listed.
 * @param  array  $plugin_args  The related arguments for the plugin.
 *
 * @return mixed
 */
function check_plugin_dependencies( $plugin_slug = '', $plugin_args = array() ) {

	// Bail without args, slug, or dependencies to check.
	if ( empty( $plugin_slug ) || empty( $plugin_args ) || empty( $plugin_args['dependencies'] ) ) {
		return false;
	}

	// Set our currently installed and activated plugins.
	$active_plugins = get_current_activated_plugins();

	// If we have no activated plugins (somehow), return false.
	if ( empty( $active_plugins ) ) {
		return false;
	}

	// Set our array of dependencies.
	$setup_dependencies = (array) $plugin_args['dependencies'];

	// If Beaver Builder is a dependency, handle the edge.
	if ( in_array( 'bb-plugin', $setup_dependencies ) ) {
		$active_plugins = maybe_standardize_bb_plugins( $active_plugins );
	}

	// Now loop each dependency to compare against currents.
	foreach ( $setup_dependencies as $dependency ) {

		// If the dependency is covered, continue on.
		if ( in_array( $dependency, $active_plugins ) ) {
			continue;
		}

		// If the dependency is the Astra theme, return
		// that without doing any more checks.
		if ( 'astra-theme' === sanitize_text_field( $dependency ) ) {
			return __( 'Astra Theme', 'nexcess-mapps-dashboard' );
		}

		// First check for the dependency being one we also have.
		$maybe_dependency_name  = get_plugin_vars_by_slug( $dependency, 'identity' );

		// Return the dependency name if it exists.
		if ( ! empty( $maybe_dependency_name ) ) {
			return $maybe_dependency_name;
		}

		// Now we do a switch / case to return the rest.
		// This is somewhat manual, since we don't have a clean
		// way to get dependency names we don't also offer.
		switch ( $dependency ) {

			// Display the WooCommerce, which is most common.
			case 'woocommerce' :
				return __( 'WooCommerce', 'nexcess-mapps-dashboard' );
				break;

			// Additional dependency
			// names will go here.

			// This handles a dependency name we aren't
			// aware of, while being gramatically correct.
			default :
				return __( 'additional plugins', 'nexcess-mapps-dashboard' );
				break;
		}

		// Nothing left to check on each missing dependency.
	}

	// Nothing left, so return false.
	return false;
}

/**
 * Get the data for a single plugin, and return all of it.
 *
 * @param  integer $plugin_id  The ID of the plugin we're getting.
 *
 * @return array|false
 */
function get_single_plugin_vars( $plugin_id = 0 ) {

	// Bail without ID.
	if ( empty( $plugin_id ) ) {
		return false;
	}

	// First get my raw plugin dataset.
	$raw_plugin_dataset = Datasets\get_available_plugins_dataset( false );

	// Bail if we don't have a dataset.
	if ( empty( $raw_plugin_dataset ) ) {
		return false;
	}

	// Look for the plugin ID inside of the data array.
	$maybe_find_id_key  = array_search( absint( $plugin_id ), array_column( $raw_plugin_dataset, 'id' ) );

	// If the ID existed in the array, use that key.
	return empty( $maybe_find_id_key ) ? false : $raw_plugin_dataset[ $maybe_find_id_key ];
}

/**
 * Get the data for a single plugin, then one piece of said data.
 *
 * @param  integer $plugin_id  The ID of the plugin we're getting.
 * @param  string  $var_name   The name of the variable we want.
 *
 * @return mixed
 */
function get_single_var_for_plugin( $plugin_id = 0, $var_name = '' ) {

	// Bail without slug or arg name to check.
	if ( empty( $plugin_id ) || empty( $var_name ) ) {
		return false;
	}

	// First get my raw plugin dataset.
	$raw_plugin_dataset = Datasets\get_available_plugins_dataset( false );

	// Bail if we don't have a dataset, or the var name we want.
	if ( empty( $raw_plugin_dataset ) || ! isset( $raw_plugin_dataset[0][ $var_name ] ) ) {
		return false;
	}

	// Now pluck out the variable we want.
	$pluck_raw_dataset  = wp_list_pluck( $raw_plugin_dataset, $var_name, 'id' );

	// Return the requested key, or nothing.
	return array_key_exists( $plugin_id, $pluck_raw_dataset ) ? $pluck_raw_dataset[ $plugin_id ] : false;
}

/**
 * Attempt to the data for a single plugin using the slug, and return all of it.
 *
 * @param  string $plugin_slug  The slug of the plugin we're getting.
 * @param  string $var_name     Optional name of a single variable we want.
 *
 * @return mixed
 */
function get_plugin_vars_by_slug( $plugin_slug = '', $var_name = '' ) {

	// Bail without slug.
	if ( empty( $plugin_slug ) ) {
		return false;
	}

	// First get my raw plugin dataset.
	$raw_plugin_dataset = Datasets\get_available_plugins_dataset( false );

	// Bail if we don't have a dataset.
	if ( empty( $raw_plugin_dataset ) ) {
		return false;
	}

	// Look for the plugin slug inside of the data array.
	$maybe_find_plugin  = array_search( esc_attr( $plugin_slug ), array_column( $raw_plugin_dataset, 'name' ) );

	// If the slug didn't exist, return false.
	if ( empty( $maybe_find_plugin ) ) {
		return false;
	}

	// Set our single dataset.
	$single_plugin_args = $raw_plugin_dataset[ $maybe_find_plugin ];

	// If we did not request a single variable, return the whole set.
	if ( empty( $var_name ) ) {
		return $single_plugin_args;
	}

	// If the requested variable doesn't exist, return false. Otherwise return it.
	return ! isset( $var_name ) ? false : $single_plugin_args[ $var_name ];
}

/**
 * Check an error code and return the appropriate text.
 *
 * @param  string $error_code  The error code provided.
 *
 * @return string
 */
function get_admin_error_text( $error_code = '' ) {

	// Return if we don't have an error code.
	if ( empty( $error_code ) ) {
		return __( 'There was an error with your request.', 'nexcess-mapps-dashboard' );
	}

	// Handle my different error codes.
	switch ( esc_attr( strtolower( $error_code ) ) ) {

		case 'no_plugins_selected' :
			return __( 'You did not select any plugins to install.', 'nexcess-mapps-dashboard' );
			break;

		case 'install_error' :
			return __( 'There was an error attempting to install one or more plugins. See the list below.', 'nexcess-mapps-dashboard' );
			break;

		case 'missing_nonce' :
			return __( 'The required nonce was missing.', 'nexcess-mapps-dashboard' );
			break;

		case 'bad_nonce' :
			return __( 'The required nonce was invalid.', 'nexcess-mapps-dashboard' );
			break;

		case 'invalid_nonce' :
			return __( 'The required nonce was missing or invalid.', 'nexcess-mapps-dashboard' );
			break;

		case 'unknown' :
		case 'unknown_error' :
			return __( 'There was an unknown error with your request.', 'nexcess-mapps-dashboard' );
			break;

		default :
			return __( 'There was an error with your request.', 'nexcess-mapps-dashboard' );

		// End all case breaks.
	}
}

/**
 * Check to see if we have any pending items for licensing.
 *
 * @param  string $return_type  How to return the data. Either a boolean or the array of IDs.
 *
 * @return mixed
 */
function maybe_has_pending_licensing( $return_type = 'array' ) {

	// Check for the pending.
	$maybe_pending  = get_option( Core\OPTION_PREFIX . 'pending_licensing', array() );

	// If we have none, it's false.
	if ( empty( $maybe_pending ) ) {
		return false;
	}

	// Now return based on the type requested.
	return 'boolean' === $return_type ? true : $maybe_pending;
}

/**
 * Take any pending licensing from an install and add it if we already have some.
 *
 * @param  array $pending_licensing  The newest data.
 *
 * @return void
 */
function update_pending_licensing( $pending_licensing = array() ) {

	// Get any current pending.
	$maybe_current_data = maybe_has_pending_licensing();

	// Merge the existing data if it exists.
	$maybe_merged_data  = false !== $maybe_current_data ? $maybe_current_data + $pending_licensing : $pending_licensing;

	// And update the option.
	update_option( Core\OPTION_PREFIX . 'pending_licensing', $maybe_merged_data, 'no' );
}

/**
 * Remove any completed licensing from the array.
 *
 * @param  array $completed_plugins  The ID's of all the plugins finished.
 *
 * @return void
 */
function remove_completed_licensing( $completed_plugins = array() ) {

	// Get any current pending.
	$maybe_current_data = maybe_has_pending_licensing();

	// If we don't have pending (for some reason) bail.
	if ( empty( $maybe_current_data ) ) {
		return;
	}

	// Remove all the plugin IDs we passed.
	$maybe_data_remains = array_diff_key( $maybe_current_data, array_flip( $completed_plugins ) );

	// And update the option.
	update_option( Core\OPTION_PREFIX . 'pending_licensing', $maybe_data_remains, 'no' );
}

/**
 * Check to see if we have any failed items for licensing.
 *
 * @param  string $return_type  How to return the data. Either a boolean or the array of IDs.
 *
 * @return mixed
 */
function maybe_has_failed_licensing( $return_type = 'array' ) {

	// Check for the pending.
	$maybe_failed   = get_option( Core\OPTION_PREFIX . 'failed_licensing', array() );

	// If we have none, it's false.
	if ( empty( $maybe_failed ) ) {
		return false;
	}

	// Now return based on the type requested.
	return 'boolean' === $return_type ? true : $maybe_failed;
}

/**
 * Take any failed licensing from an attempt and add it if we already have some.
 *
 * @param  array $failed_licensing  The newest data.
 *
 * @return void
 */
function update_failed_licensing( $failed_licensing = array() ) {

	// Get any current pending.
	$maybe_current_data = maybe_has_failed_licensing();

	// Merge the existing data if it exists.
	$maybe_merged_data  = false !== $maybe_current_data ? $maybe_current_data + $failed_licensing : $failed_licensing;

	// And update the option.
	update_option( Core\OPTION_PREFIX . 'failed_licensing', $maybe_merged_data, 'no' );
}

/**
 * See if the currently installed plugins include one from Iconic.
 *
 * @param  boolean $trimmed  Whether to trim the return for just documents.
 *
 * @return boolean|false
 */
function maybe_installed_iconic( $trimmed = false ) {

	// Fetch the currently installed plugins.
	$installed_list = get_current_installed_plugins( false );

	// Bail without anything currently installed.
	if ( empty( $installed_list ) ) {
		return false;
	}

	// Get all my Iconic data.
	$iconic_dataset = Datasets\iconic_activation_dataset();

	// Bail without any Iconic data to work with.
	if ( empty( $iconic_dataset ) ) {
		return false;
	}

	// Set my empty array.
	$iconic = array();

	// Now loop and check.
	foreach ( $installed_list as $installed ) {

		// Skip ones that aren't there.
		if ( ! array_key_exists( $installed, $iconic_dataset ) ) {
			continue;
		}

		// Set our single Iconic plugin.
		$single_iconic  = $iconic_dataset[ $installed ];

		// Remove some bits.
		if ( false !== $trimmed ) {
			unset( $single_iconic['file'] );
			unset( $single_iconic['class'] );
			unset( $single_iconic['dismiss'] );
			unset( $single_iconic['menu'] );
		}

		// Now add it to the new array.
		$iconic[ $installed ] = $single_iconic;
	}

	// Return the resulting array, or none.
	return ! empty( $iconic ) ? $iconic : false;
}

/**
 * Check to see if we can find the Iconic license key.
 *
 * @param  array $licensing_script  The possible licensing script from instructions.
 *
 * @return string|false
 */
function maybe_has_iconic_key( $licensing_script = array() ) {

	// Set the name of our key.
	$option_key = Core\OPTION_PREFIX . 'iconic_key';

	// First check for a stored license key.
	$stored_key = get_option( $option_key, false );

	// If we have one of those, return it.
	if ( false !== $stored_key ) {
		return $stored_key;
	}

	// If no licensing script was provided, we are false.
	if ( empty( $licensing_script ) ) {
		return false;
	}

	// If we sent the whole script, pull out the licensing part.
	$licensing_script   = array_key_exists( 'licensing_script', $licensing_script ) ? $licensing_script['licensing_script'] : $licensing_script;

	// If the wp_option part isn't there, bail.
	if ( empty( $licensing_script['wp_option'] ) ) {
		return false;
	}

	// Set our option arg array.
	$script_arg = (array) $licensing_script['wp_option'];

	// If the option key and our passed array key match, return the value.
	if ( key( $script_arg ) === $option_key ) {
		return current( $script_arg );
	}

	// Maybe we can check something else later, but for now, false.
	return false;
}
