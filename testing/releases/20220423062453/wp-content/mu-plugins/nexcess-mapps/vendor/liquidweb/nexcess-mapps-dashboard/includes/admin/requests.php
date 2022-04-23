<?php
/**
 * The actual processing functions tied to the wp-admin area.
 *
 * @package PlatformSelfInstall
 */

// Call our namepsace.
namespace Nexcess\MAPPS\Dashboard\Admin\Requests;

// Set our alias items.
use Nexcess\MAPPS\Dashboard as Core;
use Nexcess\MAPPS\Dashboard\Helpers as Helpers;
use Nexcess\MAPPS\Dashboard\Utilities as Utilities;
use Nexcess\MAPPS\Dashboard\Datasets as Datasets;
use Nexcess\MAPPS\Dashboard\Routing\Process as Process;

// And pull in any other namespaces.
use WP_Error;

/**
 * Start our engines.
 */
add_action( 'admin_init', __NAMESPACE__ . '\manage_user_install_request', 2 );
add_action( 'admin_init', __NAMESPACE__ . '\manage_licensing_activation_request', 45 );

/**
 * Look for the form $_POST with the selected plugins.
 *
 * @return void
 */
function manage_user_install_request() {

	// Make sure we have the $_POST request and it isn't Ajax.
	if ( ! is_admin() || wp_doing_ajax() || ! isset( $_POST['nexcess-mapps-dashboard-submit'] ) ) {
		return;
	}

	// Make sure we aren't on a license run.
	if ( ! empty( $_GET['nexcess-run-licensing'] ) ) {
		return;
	}

	// Bail on a non authorized user.
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( __( 'You are not authorized to perform this function.', 'nexcess-mapps-dashboard' ), __( 'Nexcess Managed Apps Install', 'nexcess-mapps-dashboard' ) );
	}

	// Check nonce and bail if missing or not valid.
	if ( empty( $_POST['nexcess-selfinstall-nonce'] ) || ! wp_verify_nonce( $_POST['nexcess-selfinstall-nonce'], 'nexcess-selfinstall-action' ) ) {
		wp_die( __( 'Your nonce failed. Please try again.', 'nexcess-mapps-dashboard' ), __( 'Nexcess Managed Apps Install', 'nexcess-mapps-dashboard' ) );
	}

	// If we don't have any items selected, redirect.
	if ( empty( $_POST['nexcess-mapps-plugin'] ) ) {

		// Set up the redirect args.
		$redirect_args  = array(
			'success' => false,
			'error'   => true,
			'errcode' => 'no_plugins_selected',
		);

		// And process the redirect.
		Utilities\build_admin_redirect( $redirect_args );
	}

	// Sanitize each requested plugin.
	$requested_plugins  = array_map( 'absint', $_POST['nexcess-mapps-plugin'] );

	// If the resulting sanitation makes it empty, bail.
	if ( empty( $requested_plugins ) ) {

		// Set up the redirect args.
		$redirect_args  = array(
			'success' => false,
			'error'   => true,
			'errcode' => 'no_plugins_selected',
		);

		// And process the redirect.
		Utilities\build_admin_redirect( $redirect_args );
	}

	// Run our pre-install data purge.
	Utilities\purge_data_before_install();

	// Fetch the complete set of installation instructions.
	$full_instructions  = Datasets\get_plugin_install_instructions( $requested_plugins );

	// Kill out and return the error.
	if ( is_wp_error( $full_instructions ) ) {

		// Set up the redirect args.
		$redirect_args  = array(
			'success'   => false,
			'error'     => true,
			'errcode'   => $full_instructions->get_error_code(),
		);

		// And process the redirect.
		Utilities\build_admin_redirect( $redirect_args );
	}

	// Run the potential pre-install scripts.
	$run_pre_installs   = maybe_run_pre_post_install_scripts( $full_instructions, 'pre_install_script' );

	// Kill out and return the error.
	if ( is_wp_error( $run_pre_installs ) ) {

		// Set up the redirect args.
		$redirect_args  = array(
			'success'   => false,
			'error'     => true,
			'errcode'   => $run_pre_installs->get_error_code(),
		);

		// And process the redirect.
		Utilities\build_admin_redirect( $redirect_args );
	}

	// Bail if we return an actual false for the pre-installs.
	if ( false === $run_pre_installs ) {

		// Set up the redirect args.
		$redirect_args  = array(
			'success'   => false,
			'error'     => true,
			'errcode'   => 'pre_install_error',
		);

		// And process the redirect.
		Utilities\build_admin_redirect( $redirect_args );
	}

	// Pull out the install args.
	$pluck_install_args = wp_list_pluck( $full_instructions, 'install', null );

	// Run our installation process.
	$get_install_result = install_requested_plugins( $pluck_install_args );

	// Kill out and return the error.
	if ( is_wp_error( $get_install_result ) ) {

		// Set up the redirect args.
		$redirect_args  = array(
			'success'   => false,
			'error'     => true,
			'errcode'   => $get_install_result->get_error_code(),
		);

		// And process the redirect.
		Utilities\build_admin_redirect( $redirect_args );
	}

	// If any came back failed, return that.
	if ( ! empty( $get_install_result['failed'] ) ) {

		// Set up the redirect args.
		$redirect_args  = array(
			'success'        => false,
			'error'          => true,
			'errcode'        => 'install_error',
			'nexcess-failed' => absint( $get_install_result['failed'] ),
		);

		// And process the redirect.
		Utilities\build_admin_redirect( $redirect_args );
	}

	// Pull out the slugs we just installed.
	$pluck_plugin_slugs = wp_list_pluck( $full_instructions, 'name', null );

	// Run our Dokan function.
	// @todo this needs to be abstracted to handle all the various
	// plugins we have to modify settings for.
	if ( in_array( 'dokan-pro', $pluck_plugin_slugs ) ) {
		Utilities\set_initial_dokan_settings();
	}

	// Run the potential post-install scripts.
	$run_post_installs  = maybe_run_pre_post_install_scripts( $full_instructions, 'post_install_script' );

	// Kill out and return the error.
	if ( is_wp_error( $run_post_installs ) ) {

		// Set up the redirect args.
		$redirect_args  = array(
			'success'   => false,
			'error'     => true,
			'errcode'   => $run_post_installs->get_error_code(),
		);

		// And process the redirect.
		Utilities\build_admin_redirect( $redirect_args );
	}

	// Bail if we return an actual false for the post-installs.
	if ( false === $run_post_installs ) {

		// Set up the redirect args.
		$redirect_args  = array(
			'success'   => false,
			'error'     => true,
			'errcode'   => 'post_install_error',
		);

		// And process the redirect.
		Utilities\build_admin_redirect( $redirect_args );
	}

	// Set my installed count.
	$get_install_count  = ! empty( $get_install_result['installed'] ) ? absint( $get_install_result['installed'] ) : 0;

	// Set up the redirect args.
	$redirect_args  = array(
		'success'           => true,
		'error'             => null,
		'nexcess-installed' => absint( $get_install_count ),
	);

	// Run our post-install data purge.
	Utilities\purge_data_after_install();

	// Check for the Brainstorm setup function.
	if ( function_exists( 'init_bsf_core' ) ) {
		init_bsf_core();
	}

	// And process the redirect.
	Utilities\build_admin_redirect( $redirect_args );
}

/**
 * Look for the specific Iconic flag to run the activation.
 *
 * @return void
 */
function manage_licensing_activation_request() {

	// Make sure we have admin and it isn't Ajax.
	if ( ! is_admin() || wp_doing_ajax() ) {
		return;
	}

	// Make sure we're on the correct page.
	if ( empty( $_GET['page'] ) || 'nexcess-mapps-dashboard' !== sanitize_text_field( $_GET['page'] ) ) {
		return;
	}

	// Make sure we aren't doing an install request.
	if ( isset( $_POST['nexcess-mapps-dashboard-submit'] ) ) {
		return;
	}

	// Make sure we're on the correct query arg.
	if ( empty( $_GET['nexcess-run-licensing'] ) || 'run' !== sanitize_text_field( $_GET['nexcess-run-licensing'] ) ) {
		return;
	}

	// Bail on a non authorized user.
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( __( 'You are not authorized to perform this function.', 'nexcess-mapps-dashboard' ), __( 'Nexcess Managed Apps Install', 'nexcess-mapps-dashboard' ) );
	}

	// Check nonce and bail if missing or not valid.
	if ( empty( $_GET['nexcess-licensing-nonce'] ) || ! wp_verify_nonce( $_GET['nexcess-licensing-nonce'], 'nexcess-do-licensing' ) ) {
		wp_die( __( 'Your nonce failed. Please try again.', 'nexcess-mapps-dashboard' ), __( 'Nexcess Managed Apps Install', 'nexcess-mapps-dashboard' ) );
	}

	// Run our pre-install data purge.
	Utilities\purge_data_before_licensing();

	// Check for the licensing flags.
	$get_pending_licensing  = Helpers\maybe_has_pending_licensing();

	// If we don't have a license data to work with, bail.
	if ( empty( $get_pending_licensing ) ) {

		// Set up the redirect args.
		$redirect_args  = array(
			'success'               => false,
			'error'                 => true,
			'errcode'               => 'missing_licensing_data',
			'nexcess-run-licensing' => 'completed',
		);

		// And process the redirect.
		Utilities\build_admin_redirect( $redirect_args, false );
	}

	// Pull out the requested IDs.
	$requested_plugins  = array_keys( $get_pending_licensing );

	// If the resulting list plucking makes it empty, bail.
	if ( empty( $requested_plugins ) ) {

		// Set up the redirect args.
		$redirect_args  = array(
			'success' => false,
			'error'   => true,
			'errcode' => 'no_plugins_selected',
		);

		// And process the redirect.
		Utilities\build_admin_redirect( $redirect_args );
	}

	// Fetch the complete installation instructions.
	$get_instructions   = Datasets\get_plugin_licensing_instructions( $requested_plugins );

	// Kill out and return the error.
	if ( is_wp_error( $get_instructions ) ) {

		// Set up the redirect args.
		$redirect_args  = array(
			'success'               => false,
			'error'                 => true,
			'errcode'               => $get_instructions->get_error_code(),
			'nexcess-run-licensing' => 'completed',
		);

		// And process the redirect.
		Utilities\build_admin_redirect( $redirect_args, false );
	}

	// Add an action to do something with the dataset first.
	do_action( Core\HOOK_PREFIX . 'before_licensing_run', $get_instructions );

	// Run the licensing functions.
	$run_licensing_list = license_requested_plugins( $get_instructions );

	// If we didn't get the activated data back, show that.
	if ( empty( $run_licensing_list ) ) {

		// Set up the redirect args.
		$redirect_args  = array(
			'success'               => false,
			'error'                 => true,
			'errcode'               => 'licensing_run_error',
			'nexcess-run-licensing' => 'completed',
		);

		// And process the redirect.
		Utilities\build_admin_redirect( $redirect_args, false );
	}

	// @todo more details in comparing fails and success.

	// Set my licensed count.
	$get_licensed_count = ! empty( $run_licensing_list['licensed'] ) ? absint( $run_licensing_list['licensed'] ) : 0;

	// Run our post-install data purge.
	Utilities\purge_data_after_licensing();

	// Add an action to do something with the dataset now that we are done.
	do_action( Core\HOOK_PREFIX . 'after_licensing_run', $get_instructions );

	// Set up the redirect args.
	$redirect_args  = array(
		'success'               => true,
		'error'                 => null,
		'nexcess-licensed'      => absint( $get_licensed_count ),
		'nexcess-run-licensing' => 'completed',
	);

	// And process the redirect.
	Utilities\build_admin_redirect( $redirect_args, false );
}

/**
 * Run the pre or post install scripts (if we have any.)
 *
 * @param  array  $install_instructions  The instructions for each plugin to install.
 * @param  string $pre_post_arg          Whether this is a pre or post install.
 *
 * @return mixed
 */
function maybe_run_pre_post_install_scripts( $install_instructions, $pre_post_arg = '' ) {

	// Bail if our array isn't what we want.
	if ( empty( $install_instructions ) || ! is_array( $install_instructions ) ) {
		return new WP_Error( 'missing_install_instructions', __( 'The requested installation instructions were not provided.', 'nexcess-mapps-dashboard' ) );
	}

	// If we didn't pass an install arg, error that.
	if ( empty( $pre_post_arg ) || ! in_array( $pre_post_arg, array( 'pre_install_script', 'post_install_script' ) ) ) {
		return new WP_Error( 'invalid_install_arg', __( 'The correct pre or post install argument was not passed.', 'nexcess-mapps-dashboard' ) );
	}

	// Check to see if we have any install scripts.
	$pluck_script_args  = wp_list_pluck( $install_instructions, sanitize_text_field( $pre_post_arg ), null );

	// If we have none, return true since we didn't fail.
	if ( empty( $pluck_script_args ) ) {
		return true;
	}

	// Filter down the array to make sure there is actually things there.
	$maybe_has_scripts  = array_filter( $pluck_script_args );

	// If we have none, return true since we didn't fail.
	if ( empty( $maybe_has_scripts ) ) {
		return true;
	}

	// Check the availablity of using CLI.
	$maybe_cli  = Utilities\maybe_cli_allowed();

	// Loop each requested item and grab their args.
	foreach ( $maybe_has_scripts as $plugin_id => $install_script_arrays ) {

		// Loop the install scripts, which is always an array even if it's 1 item.
		foreach ( $install_script_arrays as $script_index => $plugin_args ) {

			if ( ! is_array( $plugin_args ) ) {
				return false;
			}

			// Determine the installation method and source.
			$install_method = key( $plugin_args );
			$install_source = current( $plugin_args );

			// Bail with no return at all.
			if ( empty( $install_method ) || empty( $install_source ) ) {
				return false;
			}

			// Now handle each install method.
			// We currently have 2 but that could change.
			switch ( $install_method ) {

				// If this is a WP repo hosted, we can pull from there.
				case 'wp_package' :

					// Run the single install.
					$maybe_install  = Process\install_single_plugin_via_repo( $install_source, $maybe_cli );
					break;

				case 'wp_theme' :
					$maybe_install = Process\install_single_theme_via_repo( $install_source, $maybe_cli );
					break;

				case 'source' :

					// Pass the sourced file to the install function.
					$maybe_install  = Process\install_single_plugin_via_source( $install_source, $maybe_cli );
					break;

				// Our default is gonna be "true" for now.
				default :

					$maybe_install  = true;
					break;
			}

			// First handle if the install failed.
			if ( false === $maybe_install || is_wp_error( $maybe_install ) ) {
				return false;
			}

			// Finish inside of the pre-install item loops.
		}

		// That's it for each plugin. For now.
	}

	// We are done, so return "true".
	return true;
}

/**
 * Install each plugin as requested.
 *
 * @param  array $install_instructions  The instructions for each plugin to install.
 *
 * @return mixed
 */
function install_requested_plugins( $install_instructions ) {

	// Bail if our array isn't what we want.
	if ( empty( $install_instructions ) || ! is_array( $install_instructions ) ) {
		return new WP_Error( 'missing_install_instructions', __( 'The requested installation instructions were not provided.', 'nexcess-mapps-dashboard' ) );
	}

	// Run our pre-install cleanup.
	Utilities\cleanup_data_before_install();

	// Check the availablity of using CLI.
	$maybe_cli  = Utilities\maybe_cli_allowed();

	// Set a few variables for use in the return.
	$install_count  = 0;
	$failed_count   = 0;
	$failed_items   = array();

	// Loop each requested item and grab their args.
	foreach ( $install_instructions as $plugin_id => $plugin_args ) {

		if ( ! is_array( $plugin_args ) ) {
			return false;
		}

		// Determine the installation method and source.
		$install_method = key( $plugin_args );
		$install_source = current( $plugin_args );

		// Bail with no return at all.
		if ( empty( $install_method ) || empty( $install_source ) ) {

			// Bump the failed count.
			$failed_count++;

			// And add to the array.
			$failed_items[] = $plugin_id;

			// Then go on to the next one.
			continue;
		}

		// Now handle each install method.
		// We currently have 2 but that could change.
		switch ( $install_method ) {

			// If this is a WP repo hosted, we can pull from there.
			case 'wp_package' :

				// Run the single install.
				$maybe_install  = Process\install_single_plugin_via_repo( $install_source, $maybe_cli );
				break;

			case 'wp_theme' :
				$maybe_install = Process\install_single_theme_via_repo( $install_source, $maybe_cli );
				break;

			case 'source' :

				// Pass the sourced file to the install function.
				$maybe_install  = Process\install_single_plugin_via_source( $install_source, $maybe_cli );
				break;

			// Our default is gonna be "false" for now.
			default :

				$maybe_install  = false;
				break;
		}

		// First handle if the install failed.
		if ( false === $maybe_install || is_wp_error( $maybe_install ) ) {

			// Bump the failed count.
			$failed_count++;

			// And add to the array.
			$failed_items[] = $plugin_id;

			// Nothing left to check on a failed install.
		}

		// If the install was successful, increment my count.
		if ( false !== $maybe_install && ! is_wp_error( $maybe_install ) ) {

			// Bump the install count.
			$install_count++;
		}

		// That's it for each plugin. For now.
	}

	// If we have failed plugins, add the data.
	if ( ! empty( $failed_items ) ) {
		update_option( Core\OPTION_PREFIX . 'failed_installs', $failed_items, 'no' );
	}

	// Run our post-install cleanup.
	Utilities\cleanup_data_after_install();

	// Now return an array of the results.
	return array(
		'requested' => count( $install_instructions ),
		'installed' => absint( $install_count ),
		'failed'    => absint( $failed_count ),
	);
}

/**
 * License each plugin as requested.
 *
 * @param  array $license_instructions  The instructions for each plugin to license.
 *
 * @return mixed
 */
function license_requested_plugins( $license_instructions ) {

	// Bail if our array isn't what we want.
	if ( empty( $license_instructions ) || ! is_array( $license_instructions ) ) {
		return new WP_Error( 'missing_license_instructions', __( 'The requested licensing instructions were not provided.', 'nexcess-mapps-dashboard' ) );
	}

	// Check the availablity of using CLI.
	$maybe_cli  = Utilities\maybe_cli_allowed();

	// Set a few variables for use in the return.
	$finish_count  = 0;
	$failed_count   = 0;
	$finish_items  = array();
	$failed_items   = array();

	// Loop each requested item and grab their args.
	foreach ( $license_instructions as $plugin_id => $plugin_args ) {

		// Bail with no license script inside to run.
		if ( empty( $plugin_args['licensing_script'] ) ) {

			// Bump the failed count.
			$failed_count++;

			// And add to the array.
			$failed_items[] = $plugin_id;

			// Then go on to the next one.
			continue;
		}

		// Pull out the script args inside the array.
		$license_script = (array) $plugin_args['licensing_script'];

		// Determine the licensing method and source.
		$license_method = key( $license_script );
		$license_setup  = current( $license_script );

		// Bail with no return at all.
		if ( empty( $license_method ) || empty( $license_setup ) ) {

			// Bump the failed count.
			$failed_count++;

			// And add to the array.
			$failed_items[] = $plugin_id;

			// Then go on to the next one.
			continue;
		}

		// If this is a CLI command and we don't have CLI, fail.
		if ( ! $maybe_cli && 'wp_cli' === $license_method ) {

			// Bump the failed count.
			$failed_count++;

			// And add to the array.
			$failed_items[] = $plugin_id;

			// Then go on to the next one.
			continue;
		}

		// If need be, we will add individual pre-license actions here.

		// Now handle each licensing method.
		// We currently have 3 but that could change.
		switch ( $license_method ) {

			// Set a key / value pair.
			case 'wp_option' :

				// Run the basic option setup.
				$maybe_licensed = Process\attempt_single_plugin_licensing_via_option( $license_setup );
				break;

			// Run a command line shell.
			case 'wp_cli' :

				// Pass the command string along.
				foreach ( (array) $license_setup as $shell_instruction ) {

					// This is our temporary string replace for Brainstorm.
					$set_cli_shell_instructionstring = str_replace( 'brainstormforce license', 'nxmapps brainstormforce', $shell_instruction );

					$maybe_licensed = Process\attempt_single_plugin_licensing_via_shell( $shell_instruction );

					if (false === $maybe_licensed) {
						break;
					}
				}

				break;

			// Handle an API response, which is just Jetpack right now.
			case 'response' :

				// We are setting this to "true" for now but need to handle.
				$maybe_licensed = Process\confirm_single_plugin_licensing_via_response( $license_setup );
				break;

			// We can add any additional
			// license methods here before
			// we hit the fallback "false".

			// Our default is gonna be "false" for now.
			default :

				$maybe_licensed = false;
				break;
		}

		// First handle if the licensing failed.
		if ( false === $maybe_licensed || is_wp_error( $maybe_licensed ) ) {

			// Bump the failed count.
			$failed_count++;

			// And add to the array.
			$failed_items[] = $plugin_id;

			// Nothing left to check on a failed install.
		}

		// If need be, we will add individual post-license actions here.

		// If the licensing was successful, increment my count.
		if ( false !== $maybe_licensed && ! is_wp_error( $maybe_licensed ) ) {

			// Bump the licensing count.
			$finish_count++;

			// And add to the array of good items.
			$finish_items[] = $plugin_id;
		}

		// That's it for each plugin. For now.
	}

	// Go and remove all the successfull ones from the data.
	if ( ! empty( $finish_items ) ) {
		Helpers\remove_completed_licensing( $finish_items );
	}

	// If we have failed plugins, add the data.
	if ( ! empty( $failed_items ) ) {
		Helpers\update_failed_licensing( $failed_items );
	}

	// Now return an array of the results.
	return array(
		'requested' => count( $license_instructions ),
		'licensed'  => absint( $finish_count ),
		'failed'    => absint( $failed_count ),
	);
}
