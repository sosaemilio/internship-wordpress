<?php
/**
 * The display and layout functions tied to the wp-admin area.
 *
 * @package PlatformSelfInstall
 */

// Call our namepsace.
namespace Nexcess\MAPPS\Dashboard\Admin\Notices;

// Set our alias items.
use Nexcess\MAPPS\Dashboard as Core;
use Nexcess\MAPPS\Dashboard\Helpers as Helpers;
use Nexcess\MAPPS\Dashboard\Utilities as Utilities;
use Nexcess\MAPPS\Dashboard\Datasets as Datasets;
use Nexcess\MAPPS\Dashboard\Structure\Markup as Markup;

/**
 * Start our engines.
 */
add_action( 'admin_notices', __NAMESPACE__ . '\display_requested_installs_result', 10 );
add_action( 'admin_notices', __NAMESPACE__ . '\display_pending_licensing_prompt', 12 );
add_action( 'admin_notices', __NAMESPACE__ . '\display_requested_licensing_result', 14 );

/**
 * Display the results of the installer.
 *
 * @return void
 */
function display_requested_installs_result() {

	// Check our admin screen including an Ajax check.
	$check_our_screen   = Utilities\check_admin_screen( true );

	// Bail if we aren't in the right spot.
	if ( ! $check_our_screen ) {
		return;
	}

	// Bail without the completed flag.
	if ( empty( $_GET['nexcess-selfinstall'] ) || 'completed' !== sanitize_text_field( $_GET['nexcess-selfinstall'] ) ) {
		return;
	}

	// If we are doing another notice, bail.
	if ( isset( $_GET['nexcess-run-licensing'] ) ) {
		return;
	}

	// If we have an error code, get the text and then display a message.
	if ( ! empty( $_GET['errcode'] ) ) {

		// Get my error text.
		$error_text = Helpers\get_admin_error_text( $_GET['errcode'] );

		// And the actual message.
		Markup\display_admin_notice( $error_text, 'error' );

		// And be done.
		return;
	}

	// Parse out the number.
	$installed  = ! empty( $_GET['nexcess-installed'] ) ? absint( $_GET['nexcess-installed'] ) : 0;

	// Figure out the message based on the numbers.
	$workd_text = sprintf( _n( 'Success! %d plugin was installed.', 'Success! %d plugins were installed.', $installed, 'nexcess-mapps-dashboard' ), $installed );

	// And the actual message.
	Markup\display_admin_notice( $workd_text, 'success' );

	// And be done.
	return;
}

/**
 * Handle displaying the additional prompt for licensing.
 *
 * @param  boolean $echo  Whether to echo or just return it.
 *
 * @return HTML
 */
function display_pending_licensing_prompt() {

	// Check our admin screen including an Ajax check.
	$check_our_screen   = Utilities\check_admin_screen( true );

	// Bail if we aren't in the right spot.
	if ( ! $check_our_screen ) {
		return;
	}

	// If we are doing another notice, bail.
	if ( isset( $_GET['nexcess-run-licensing'] ) ) {
		return;
	}

	// Check to see if we need licensing at all.
	$check_licensing    = Helpers\maybe_has_pending_licensing( 'boolean' );

	// Bail if we don't need it.
	if ( ! $check_licensing ) {
		return;
	}

	// Set a nonce.
	$licensing_nonce    = wp_create_nonce( 'nexcess-do-licensing' );

	// Create a link to trigger.
	$licensing_args     = array( 'nexcess-run-licensing' => 'run', 'nexcess-licensing-nonce' => $licensing_nonce );
	$licensing_link     = add_query_arg( $licensing_args, Utilities\get_installer_menu_link() );

	// Set up the text itself.
	$licensing_text     = sprintf( __( 'Some plugins you have installed require a license key provided by Nexcess. <a class="nexcess-license-prompt-link" href="%s">Click Here</a> to complete this process.', 'nexcess-mapps-dashboard' ), esc_url( $licensing_link ) );

	// And the actual message.
	Markup\display_admin_notice( $licensing_text, 'info', array( 'nexcess-installer-big-notice' ), false );

	// And be done.
	return;
}

/**
 * Display the results of the installer.
 *
 * @return void
 */
function display_requested_licensing_result() {

	// Check our admin screen including an Ajax check.
	$check_our_screen   = Utilities\check_admin_screen( true );

	// Bail if we aren't in the right spot.
	if ( ! $check_our_screen ) {
		return;
	}

	// Bail without the completed flag.
	if ( empty( $_GET['nexcess-run-licensing'] ) || 'completed' !== sanitize_text_field( $_GET['nexcess-run-licensing'] ) ) {
		return;
	}

	// If we have an error code, get the text and then display a message.
	if ( ! empty( $_GET['errcode'] ) ) {

		// Get my error text.
		$error_text = Helpers\get_admin_error_text( $_GET['errcode'] );

		// And the actual message.
		Markup\display_admin_notice( $error_text, 'error' );

		// And be done.
		return;
	}

	// Parse out the number.
	$licensed   = ! empty( $_GET['nexcess-licensed'] ) ? absint( $_GET['nexcess-licensed'] ) : 0;

	// Figure out the message based on the numbers.
	$workd_text = sprintf( _n( 'Success! %d plugin was licensed.', 'Success! %d plugins were licensed.', $licensed, 'nexcess-mapps-dashboard' ), $licensed );

	// And the actual message.
	Markup\display_admin_notice( $workd_text, 'success' );

	// And be done.
	return;
}
