<?php
/**
 * The functions that do the actual processing.
 *
 * @package PlatformSelfInstall
 */

// Call our namepsace.
namespace Nexcess\MAPPS\Dashboard\Routing\Process;

// Set our alias items.
use Nexcess\MAPPS\Dashboard as Core;
use Nexcess\MAPPS\Dashboard\Helpers as Helpers;
use Nexcess\MAPPS\Dashboard\Utilities as Utilities;

// And pull in any other namespaces.
use WP_Error;

/**
 * Start our engines.
 */
add_action( 'activated_plugin', __NAMESPACE__ . '\purge_cache_on_activate', 999, 2 );
add_action( 'deactivated_plugin', __NAMESPACE__ . '\purge_cache_on_deactivate', 999, 2 );
add_action( 'upgrader_process_complete', __NAMESPACE__ . '\purge_cache_on_update', 999, 2 );
add_action( 'deleted_plugin', __NAMESPACE__ . '\purge_cache_on_delete', 999, 2 );
add_action( 'switch_theme', __NAMESPACE__ . '\purge_cache_on_theme_change', 999, 3 );
add_action( Core\HOOK_PREFIX . 'after_licensing_run', __NAMESPACE__ . '\maybe_finish_iconic_activation' );

/**
 * Delete our current list after a plugin is activated.
 *
 * @param string  $plugin        Path to the plugin file relative to the plugins directory.
 * @param boolean $network_wide  Whether this is multisite and happening network wide.
 *
 * @return void
 */
function purge_cache_on_activate( $plugin, $network_wide ) {
	Utilities\purge_current_plugin_cache();
}

/**
 * Delete our current list after a plugin is deactivated.
 *
 * @param string  $plugin        Path to the plugin file relative to the plugins directory.
 * @param boolean $network_wide  Whether this is multisite and happening network wide.
 *
 * @return void
 */
function purge_cache_on_deactivate( $plugin, $network_wide ) {
	Utilities\purge_current_plugin_cache();
}

/**
 * Delete our current list after a plugin update. This also covers installs.
 *
 * @param WP_Upgrader $upgrader_object  WP_Upgrader instance. In other contexts, $this, might be a
 *                                      Theme_Upgrader, Plugin_Upgrader, Core_Upgrade, or Language_Pack_Upgrader
 * @param array       $hook_extra       An array of bulk item update data.
 *
 * @return void
 */
function purge_cache_on_update( $upgrader_object, $hook_extra ) {
	Utilities\purge_current_plugin_cache();
}

/**
 * Delete any data lists when plugins are modified.
 *
 * @param  string  $plugin_file  Path to the plugin file relative to the plugins directory.
 * @param  boolean $deleted      Whether the plugin deletion was successful.
 *
 * @return void
 */
function purge_cache_on_delete( $plugin_file, $deleted ) {
	Utilities\purge_current_plugin_cache();
}

/**
 * Delete any data lists when a new theme is activated.
 *
 * @param  string   $new_name   Name of the new theme.
 * @param  WP_Theme $new_theme  WP_Theme instance of the new theme.
 * @param  WP_Theme $old_theme  WP_Theme instance of the old theme.
 *
 * @return void
 */
function purge_cache_on_theme_change( $new_name, $new_theme, $old_theme ) {
	Utilities\purge_current_plugin_cache();
}

/**
 * Check in our instructions if we had an Iconic plugin to install.
 *
 * @param  array $license_instructions  All the instructions.
 *
 * @return void
 */
function maybe_finish_iconic_activation( $license_instructions = array() ) {

	// First check if we have anything from Iconic installed.
	$maybe_installed_iconic = Helpers\maybe_installed_iconic();

	// If we have none, bail.
	if ( false === $maybe_installed_iconic ) {
		return;
	}

	// Look for the Iconic key, which should have just been stored in the DB.
	// We can try to fish it out of the instruction if we need to.
	$maybe_has_iconic_key   = Helpers\maybe_has_iconic_key();

	// If we have none, bail.
	if ( false === $maybe_has_iconic_key ) {
		return;
	}

	// Now loop the installed Iconic items and run each activation.
	foreach ( $maybe_installed_iconic as $plugin_slug => $plugin_args ) {

		// Run the activation set.
		$run_activation = Utilities\activate_iconic_license_key( $maybe_has_iconic_key, $plugin_args );

		// If we had a good activation, continue on.
		if ( false !== $run_activation && ! is_wp_error( $run_activation ) ) {
			continue;
		}

		// Get my error code.
		$set_error_code = is_wp_error( $run_activation ) ? $run_activation->get_error_code() : 'unknown-iconic-error';

		// Set up the redirect args.
		$redirect_args  = array(
			'success'   => false,
			'error'     => true,
			'errcode'   => $set_error_code,
		);

		// And process the redirect.
		Utilities\build_admin_redirect( $redirect_args );
	}

	// Nothing left to check inside these.
}

/**
 * Install a single plugin from WP repo.
 *
 * @param  string  $plugin_slug  The plugin slug for calling the install.
 * @param  boolean $run_cli      Whether to run the CLI version.
 *
 * @return boolean
 */
function install_single_plugin_via_repo( $plugin_slug = '', $run_cli = false ) {

	// Bail without the slug to check.
	if ( empty( $plugin_slug ) ) {
		return new WP_Error( 'missing_plugin_slug', __( 'The required plugin slug was not provided.', 'nexcess-mapps-dashboard' ) );
	}

	// Run our shell install if we are allowed.
	if ( false !== $run_cli ) {
		return attempt_single_plugin_install_via_shell( $plugin_slug );
	}

	// This will eventually have the code for doing a non-CLI install
	// if we find that we have to add that back in. But it's messy code
	// that would be better served not being used if possible.
	return new WP_Error( 'unavailable_install_method', __( 'The manual installation function is not available.', 'nexcess-mapps-dashboard' ) );
}

/**
 * Install a theme from the WP repo.
 *
 * @param  string  $theme_slug  The theme slug for calling the install.
 * @param  boolean $run_cli     Whether to run the CLI version.
 *
 * @return boolean
 */
function install_single_theme_via_repo( $theme_slug = '', $run_cli = false ) {

	// Bail without the slug to check.
	if ( empty( $theme_slug ) ) {
		return new WP_Error( 'missing_theme_slug', __( 'The required theme slug was not provided.', 'nexcess-mapps-dashboard' ) );
	}

	// Run our shell install if we are allowed.
	if ( false !== $run_cli ) {
		return attempt_single_theme_install_via_shell( $theme_slug );
	}

	// This will eventually have the code for doing a non-CLI install
	// if we find that we have to add that back in. But it's messy code
	// that would be better served not being used if possible.
	return new WP_Error( 'unavailable_install_method', __( 'The manual installation function is not available.', 'nexcess-mapps-dashboard' ) );
}

/**
 * Install a single plugin from a zip file source.
 *
 * @param  string  $plugin_source  The source to install. May be a local file.
 * @param  boolean $run_cli        Whether to run the CLI version.
 *
 * @return boolean
 */
function install_single_plugin_via_source( $plugin_source = '', $run_cli = false ) {

	// Bail without the source to check.
	if ( empty( $plugin_source ) ) {
		return new WP_Error( 'missing_plugin_source', __( 'The required plugin source was not provided.', 'nexcess-mapps-dashboard' ) );
	}

	// Confirm the string matches a file setup.
	$check_path_suffix  = substr( $plugin_source, -4 );

	// If we don't end in a zip, bail.
	if ( '.zip' !== $check_path_suffix ) {
		return new WP_Error( 'invalid_source_name', __( 'The name of the source file appears to be invalid.', 'nexcess-mapps-dashboard' ) );
	}

	// Run our shell install if we are allowed.
	if ( false !== $run_cli ) {
		return attempt_single_plugin_install_via_shell( $plugin_source );
	}

	// This will eventually have the code for doing a non-CLI install
	// if we find that we have to add that back in. But it's messy code
	// that would be better served not being used if possible.
	return new WP_Error( 'unavailable_install_method', __( 'The manual installation function is not available.', 'nexcess-mapps-dashboard' ) );
}

/**
 * Run the installation using our CLI shell command.
 *
 * @param  string  $plugin_source  The required source.
 *
 * @return boolean
 */
function attempt_single_plugin_install_via_shell( $plugin_source = '' ) {

	// Bail without the required plugin args.
	if ( empty( $plugin_source ) ) {
		return new WP_Error( 'missing_plugin_source', __( 'The required plugin source was not provided.', 'nexcess-mapps-dashboard' ) );
	}

	// Construct and execute the WP-CLI command.
	$command = sprintf(
		'%1$s plugin install %2$s --user=%3$d --activate',
		get_wp_cli_binary(), // Intentionally not escaped.
		escapeshellarg( $plugin_source ),
		absint( get_current_user_id() )
	);

	exec( escapeshellcmd( $command ), $output, $exit_code );

	// A 0 exit code means the plugin was installed successfully.
	return 0 === $exit_code;
}

/**
 * Install and activate a theme using our CLI shell command.
 *
 * @param  string  $theme_source  The required source.
 *
 * @return boolean
 */
function attempt_single_theme_install_via_shell( $theme_source = '' ) {

	// Bail without the required plugin args.
	if ( empty( $theme_source ) ) {
		return new WP_Error( 'missing_theme_source', __( 'The required theme source was not provided.', 'nexcess-mapps-dashboard' ) );
	}

	// Construct and execute the WP-CLI command.
	$command = sprintf(
		'%1$s theme install %2$s --user=%3$d --activate',
		get_wp_cli_binary(), // Intentionally not escaped.
		escapeshellarg( $theme_source ),
		absint( get_current_user_id() )
	);

	exec( escapeshellcmd( $command ), $output, $exit_code );

	// A 0 exit code means the plugin was installed successfully.
	return 0 === $exit_code;
}

/**
 * Run the licensing using a standard wp_option entry.
 *
 * @param  array $license_setup  The option key and license value.
 *
 * @return boolean
 */
function attempt_single_plugin_licensing_via_option( $license_setup = array() ) {

	// Bail without the required license args.
	if ( empty( $license_setup ) ) {
		return new WP_Error( 'missing_license_setup', __( 'The required licensing setup was not provided.', 'nexcess-mapps-dashboard' ) );
	}

	// Bail if the setup isn't constructed properly.
	if ( ! is_array( $license_setup ) ) {
		return new WP_Error( 'invalid_license_setup', __( 'The required licensing setup was invalid.', 'nexcess-mapps-dashboard' ) );
	}

	// Pull out the option key and value.
	$option_key = key( $license_setup );
	$option_val = current( $license_setup );

	// Bail without the required license args.
	if ( empty( $option_key ) || empty( $option_val ) ) {
		return new WP_Error( 'missing_license_args', __( 'The arguments required to set the license key could not be determined.', 'nexcess-mapps-dashboard' ) );
	}

	// Update our option.
	update_option( $option_key, $option_val );

	// And return true.
	return true;
}

/**
 * Run the licensing using a CLI command.
 *
 * @param  string $license_setup  The CLI string we need to run.
 *
 * @return boolean
 */
function attempt_single_plugin_licensing_via_shell( $license_setup = '' ) {

	// Bail without the required license args.
	if ( empty( $license_setup ) ) {
		return new WP_Error( 'missing_license_setup', __( 'The required licensing setup was not provided.', 'nexcess-mapps-dashboard' ) );
	}

	// Bail if the setup isn't constructed properly.
	if ( ! is_string( $license_setup ) ) {
		return new WP_Error( 'invalid_license_setup', __( 'The required licensing setup was invalid.', 'nexcess-mapps-dashboard' ) );
	}

	// Construct and execute the WP-CLI command.
	$command = sprintf(
		'%1$s %2$s --user=%3$d',
		get_wp_cli_binary(), // Intentionally not escaped.
		$license_setup, // Intentionally not escaped.
		absint( get_current_user_id() )
	);

	exec( escapeshellcmd( $command ), $output, $exit_code );

	// A 0 exit code means the plugin was licensed successfully.
	return 0 === $exit_code;
}

/**
 * Check the API response on a licensing setup.
 *
 * @param  mixed $license_response  Whatever the return was.
 *
 * @return boolean
 */
function confirm_single_plugin_licensing_via_response( $license_response ) {

	// Bail without the required license response.
	if ( empty( $license_response ) ) {
		return new WP_Error( 'missing_license_response', __( 'The licensing API response was not provided.', 'nexcess-mapps-dashboard' ) );
	}

	// Parse out the response.
	$formatted_response = json_decode( $license_response, true );

	// If it isn't valid JSON, it likely wasn't a valid reply.
	if ( empty( $formatted_response ) ) {
		return false;
	}

	// If we have the success flag, return true.
	// The plugin should handle any remaining steps
	// on its own without us doing anything.
	if ( ! empty( $formatted_response['success'] ) ) {
		return true;
	}

	// For now, return false until we
	// determine how to check errors.
	return false;
}

/**
 * Retrieve the PHP binary we want to use while running WP-CLI.
 *
 * This will use Siteworx to determine the site's current PHP version. If a version cannot be
 * determined, an empty string will be returned (indicating the default system PHP).
 *
 * @return string The system path to a PHP binary.
 */
function get_wp_cli_binary() {
	static $binary;

	// Only calculate this once, then store it in the static $binary variable.
	if ( ! $binary ) {
		/*
		 * Construct an escaped string that expands the current PHP and WP-CLI binary paths.
		 *
		 * Note that we're using the PHP_BINDIR constant and adding "/php" instead of PHP_BINARY,
		 * as the latter will point to PHP-FPM.
		 *
		 * The expected output of this will look something like:
		 *
		 *     /opt/remi/php73/root/usr/bin/php /usr/local/bin/wp
		 */
		$binary = sprintf(
			'%1$s %2$s',
			escapeshellarg( PHP_BINDIR . '/php' ),
			escapeshellarg( trim( shell_exec( 'command -v wp' ) ) )
		);
	}

	return $binary;
}
