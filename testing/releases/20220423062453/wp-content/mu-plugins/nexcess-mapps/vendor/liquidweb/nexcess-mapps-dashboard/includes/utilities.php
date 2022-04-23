<?php
/**
 * Our utility functions to use across the plugin.
 *
 * @package PlatformSelfInstall
 */

// Call our namepsace.
namespace Nexcess\MAPPS\Dashboard\Utilities;

// Set our alias items.
use Nexcess\MAPPS\Dashboard as Core;
use Nexcess\MAPPS\Dashboard\Helpers as Helpers;
use WP_Screen;

/**
 * Return our base link, with function fallbacks.
 *
 * @return string
 */
function get_installer_menu_link() {

	// Bail if we aren't on the admin side.
	if ( ! is_admin() ) {
		return false;
	}

	// Set my menu page.
	$menu_root  = trim( Core\MENU_ROOT );

	// If we're doing Ajax, build it manually.
	if ( wp_doing_ajax() ) {
		return add_query_arg( array( 'page' => $menu_root ), admin_url( '/' ) );
	}

	// Build out the link if we don't have our function.
	if ( function_exists( 'menu_page_url' ) ) {

		// Return using the function.
		return menu_page_url( $menu_root, false );
	}

	// Build out the link if we don't have our function.
	return add_query_arg( array( 'page' => $menu_root ), admin_url( '/' ) );
}

/**
 * Set up and process our redirect.
 *
 * @param  array   $args       The args passed to build the query string.
 * @param  boolean $completed  Whether to include the 'completed' flag.
 * @param  boolean $redirect   Whether to actually redirect or just return the URL. *
 *
 * @return void
 */
function build_admin_redirect( $args = array(), $completed = true, $redirect = true ) {

	// Set my base URL.
	$base   = get_installer_menu_link();

	// Build my return.
	$return = ! empty( $args ) ? add_query_arg( $args, esc_url( $base ) ) : esc_url( $base );

	// Now add our one key we always need.
	$return = ! empty( $completed ) ? add_query_arg( array( 'nexcess-selfinstall' => 'completed' ), $return ) : $return;

	// Return the URL if requested.
	if ( empty( $redirect ) ) {
		return $return;
	}

	// And process the URL redirect.
	wp_redirect( esc_url_raw( $return ), 302 );
	exit();
}

/**
 * Do the whole 'check current screen' progressions.
 *
 * @param  boolean $ajax    Whether to also bail on an Ajax call.
 * @param  string  $return  How to return the result. Usually boolean.
 *
 * @return boolean|object   Whether or not we are.
 */
function check_admin_screen( $ajax = false, $return = 'boolean' ) {

	// Do the Ajax check first.
	if ( ! empty( $ajax ) && wp_doing_ajax() ) {
		return false;
	}

	// Bail if not on admin or our function doesnt exist.
	if ( ! is_admin() || ! function_exists( 'get_current_screen' ) ) {
		return false;
	}

	// Get my current screen.
	$screen = get_current_screen();

	// Bail without.
	if ( ! $screen instanceof WP_Screen ) {
		return false;
	}

	// Don't load if we're not on the dashboard screen.
	if ( false === strpos( $screen->id, 'nexcess-mapps-dashboard' ) ) {
		return false;
	}

	// Nothing left. We passed.
	return 'screen' === $return ? $screen : true;
}

/**
 * Delete a single option.
 *
 * @param  string  $option_key  The name of the option we wanna delete.
 * @param  boolean $use_prefix  If we need to include the namespaced prefix.
 *
 * @return void
 */
function purge_single_option( $option_key = '', $use_prefix = true ) {

	// Bail without an option key.
	if ( empty( $option_key ) ) {
		return;
	}

	// Set the option name based on the prefix argument.
	$set_delete_key = false !== $use_prefix ? Core\OPTION_PREFIX . $option_key : $option_key;

	// Delete the option name.
	delete_option( $set_delete_key );
}

/**
 * Delete the transients for our installed and active plugins.
 *
 * @return void
 */
function purge_current_plugin_cache() {
	delete_transient( Core\HOOK_PREFIX . 'installed_plugins' );
	delete_transient( Core\HOOK_PREFIX . 'active_plugins' );
}

/**
 * Purge any data we may have left from previous installs.
 *
 * @return void
 */
function purge_data_before_install() {

	// Set an array.
	$keys_to_delete = array(
		Core\OPTION_PREFIX . 'failed_installs',
	);

	// Just loop and start deleting.
	foreach ( $keys_to_delete as $option_key ) {
		delete_option( $option_key );
	}
}

/**
 * Purge any data we may have left from the just-run installs.
 *
 * @return void
 */
function purge_data_after_install() {

	// Set an array.
	$keys_to_delete = array(
		Core\OPTION_PREFIX . 'pluginlist',
		Core\OPTION_PREFIX . 'activelist',
	);

	// Just loop and start deleting.
	foreach ( $keys_to_delete as $option_key ) {
		delete_option( $option_key );
	}
}

/**
 * Run any pre-install cleanup functions.
 *
 * @return void
 */
function cleanup_data_before_install() {

	// We may have some
	// pre-install cleanup
	// once each plugin is tested.
}

/**
 * Run any post-install cleanup functions.
 *
 * @return void
 */
function cleanup_data_after_install() {

	// Kill the ConvertPro redirect and update check.
	update_option( 'convert_pro_redirect', false );

	// Handle setting our Brainstorm transient to bypass the update check.
	update_option( 'bsf_local_transient', (string) current_time( 'timestamp' ) );
	set_transient( 'bsf_check_product_updates', true, DAY_IN_SECONDS );

	// Delete the transient tied to the activation redirect.
	delete_transient( 'wpforms_activation_redirect' );

	// Disable usage stats and delete the admin redirect for Beaver.
	update_option( 'fl_builder_usage_enabled', 0 );
	delete_transient( '_fl_builder_activation_admin_notice' );

	// Disable the Elementor tracking and redirect.
	update_option( 'elementor_allow_tracking', 'no' );
	update_option( 'elementor_tracker_notice', '1' );
	delete_transient( 'elementor_activation_redirect' );

	// Set a REALLY long transient for Astra.
	set_transient( 'astra-theme-first-rating', true, YEAR_IN_SECONDS );
}

/**
 * Purge any data we may have left from previous licensing.
 *
 * @return void
 */
function purge_data_before_licensing() {

	// Set an array.
	$keys_to_delete = array(
		Core\OPTION_PREFIX . 'failed_licensing',
	);

	// Just loop and start deleting.
	foreach ( $keys_to_delete as $option_key ) {
		delete_option( $option_key );
	}
}

/**
 * Purge any data we may have left from the just-run licensing.
 *
 * @return void
 */
function purge_data_after_licensing() {

	// Set an array.
	$keys_to_delete = array(
		Core\OPTION_PREFIX . 'pending_licensing',
	);

	// Just loop and start deleting.
	foreach ( $keys_to_delete as $option_key ) {
		delete_option( $option_key );
	}
}

/**
 * Take the plugin file path and strip it down to a slug.
 *
 * @param  string $plugin_file_path  The single plugin's file path.
 *
 * @return string
 */
function format_plugin_file_path( $plugin_file_path = '' ) {

	// See if this is a file path or a non-folder one.
	$maybe_path = stripos( $plugin_file_path, '/' );

	// If we have a file path, do that.
	if ( false !== $maybe_path ) {

		// Split up the filepath.
		$split_path = explode( '/', $plugin_file_path, 2 );

		// Set up the slug.
		$setup_single_slug  = $split_path[0];

	} else {

		// These are single files, so do that.
		$setup_single_slug  = str_replace( '.php', '', $plugin_file_path );
	}

	// Remove any of the "premium" from the plugin folders.
	$setup_single_slug  = str_replace( '-premium', '', $setup_single_slug );

	// Return the resulting slug, trimmed of any whitespace.
	return trim( $setup_single_slug );
}

/**
 * Check to see if this is an MWCH site.
 *
 * @return boolean
 */
function maybe_is_mwch() {
	return defined( 'NEXCESS_MAPPS_MWCH_SITE' ) && NEXCESS_MAPPS_MWCH_SITE ? true : false;
}

/**
 * Check to see if we can use our CLI functions.
 *
 * @return boolean
 */
function maybe_cli_allowed() {
	return is_callable( 'shell_exec' ) && false === stripos( ini_get( 'disable_functions' ), 'shell_exec' );
}

/**
 * Handle setting up the first parts of Dokan.
 *
 * @return void
 */
function set_initial_dokan_settings() {

	// Handle the opt-out of Dokan tracking.
	update_option( 'dokan-lite_allow_tracking', 'no' );
	update_option( 'dokan-lite_tracking_notice', 'hide' );

	// Set the modules for Dokan blank.
	update_option( 'dokan_pro_active_modules', array() );

	// Get the current versions stored (which is probably none).
	// dokan_whats_new_versions  DOKAN_PRO_PLUGIN_VERSION
	// @todo this isn't working yet because the Dokan plugin headers are incorrect.
	// $current_dokans = get_option( 'dokan_whats_new_versions', array() );

	// Nothing left inside Dokan.
}

/**
 * Activate our Iconic plugin(s) with the recently generated license.
 *
 * @param  string $license_key  The license key.
 * @param  array  $plugin_data  THe data related to the plugin we're activating.
 *
 * @return boolean|false
 */
function activate_iconic_license_key( $license_key = '', $plugin_data = array() ) {

	// Bail if no license key was provided.
	if ( empty( $license_key ) ) {
		return new WP_Error( 'missing_license_key', __( 'The required license key was not provided.', 'nexcess-mapps-dashboard' ) );
	}

	// Confirm we have the Iconic data to use.
	if ( empty( $plugin_data ) || empty( $plugin_data['class'] ) ) {
		return new WP_Error( 'missing_iconic_class', __( 'The data related to the Iconic plugin could not be found.', 'nexcess-mapps-dashboard' ) );
	}

	// Run the activation.
	$run_opt_in = $plugin_data['class']::$freemius->opt_in( false, false, false, $license_key );

	// If we have an error object, return that.
	if ( is_object( $run_opt_in ) && ! empty( $run_opt_in->error ) ) {
		return new WP_Error( 'activation_error', esc_html( $run_opt_in->error ) );
	}

	// And return a true.
	return true;
}
