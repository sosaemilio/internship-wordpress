<?php
/**
 * Any markup and HTML we have.
 *
 * @package PlatformSelfInstall
 */

// Call our namepsace.
namespace Nexcess\MAPPS\Dashboard\Structure\Markup;

// Set our alias items.
use Nexcess\MAPPS\Dashboard as Core;
use Nexcess\MAPPS\Dashboard\Helpers as Helpers;
use Nexcess\MAPPS\Dashboard\Utilities as Utilities;
use Nexcess\MAPPS\Dashboard\Datasets as Datasets;
use Nexcess\MAPPS\Dashboard\Structure\Formatting as Formatting;

/**
 * Display an admin notice.
 *
 * @param  string  $notice       The actual message to display.
 * @param  string  $result       Which type of message it is.
 * @param  array   $custom       Any additional classes to add to the notice.
 * @param  boolean $dismiss      Whether it should be dismissable.
 * @param  boolean $show_button  Show the dismiss button (for Ajax calls).
 *
 * @param  boolean $echo         Whether to echo out the markup or return it.
 *
 * @return mixed
 */
function display_admin_notice( $notice = '', $result = '', $custom = array(), $dismiss = true, $show_button = false, $echo = true ) {

	// Bail without the required notice text.
	if ( empty( $notice ) ) {
		return;
	}

	// Set the markup classes.
	$notice_classes = array(
		'notice',
		'notice-' . sanitize_html_class( $result ),
		'nexcess-installer-notice',
	);

	// Add any possible customs in there.
	if ( ! empty( $custom ) ) {
		$notice_classes = wp_parse_args( (array) $custom, $notice_classes );
	}

	// Add the dismiss in there.
	if ( ! empty( $dismiss ) ) {
		$notice_classes = wp_parse_args( array( 'is-dismissible' ), $notice_classes );
	}

	// Sanitize each possible class.
	$sanitize_args  = array_map( 'sanitize_html_class', $notice_classes );

	// Now set the class string.
	$set_class_str  = implode( ' ', $sanitize_args );

	// Set an empty.
	$build  = '';

	// And the actual message.
	$build .= '<div class="' . esc_attr( $set_class_str ) . '">';

		// Handle the actual text.
		$build .= '<p><strong>' . wp_kses_post( $notice ) . '</strong></p>';

		// Show the button if we set dismiss and button variables.
		$build .= $dismiss && $show_button ? '<button type="button" class="notice-dismiss">' . screen_reader_text() . '</button>' : '';

	$build .= '</div>';

	// Return if requested.
	if ( ! $echo ) {
		return $build;
	}

	// Echo it out.
	echo $build;
}

/**
 * Display an error if we were unable to retrieve available plugins.
 */
function display_installer_api_error() {
	echo '<div class="wrap nexcess-mapps-dashboard-page-wrap">';
	echo '<h1 class="nexcess-mapps-dashboard-intro-headline">' . esc_html( get_admin_page_title() ) . '</h1>';
	echo '<div class="notice notice-error inline">';
	echo '<p><strong>' . esc_html__( 'There was an error retrieving available plugin data!', 'nexcess-mapps-dashboard' ) . '</strong></p>';
	echo '<p>' . wp_kses_post( sprintf(
		/* Translators: %1$s is the Support tab URL of the Nexcess dashboard. */
		__( 'Please refresh this page to try again. If the problem persists, <a href="%1$s">please contact Nexcess support</a>.', 'nexcess-mapps-dashboard' ),
		admin_url( 'admin.php?page=nexcess-mapps#support' )
	) ) . '</p>';
	echo '</div>';
	echo '</div>';
}

/**
 * Display a warning if the dataset is empty.
 */
function display_installer_missing_dataset() {
	echo '<div class="wrap nexcess-mapps-dashboard-page-wrap">';
	echo '<h1 class="nexcess-mapps-dashboard-intro-headline">' . esc_html( get_admin_page_title() ) . '</h1>';
	echo '<div class="notice notice-warning inline">';
	echo '<p><strong>' . esc_html__( 'No plugins are available for installation!', 'nexcess-mapps-dashboard' ) . '</strong></p>';
	echo '<p>' . esc_html__( 'There don\'t appear to be any eligible plugins to install, which is likely an error on our part.', 'nexcess-mapps-dashboard' );
	echo '<p>' . wp_kses_post( sprintf(
		/* Translators: %1$s is the Support tab URL of the Nexcess dashboard. */
		__( 'If the problem persists after refreshing the page, <a href="%1$s">please contact Nexcess support</a> and we\'ll be happy to help you get your new plugins installed.', 'nexcess-mapps-dashboard' ),
		admin_url( 'admin.php?page=nexcess-mapps#support' )
	) ) . '</p>';
	echo '</div>';
	echo '</div>';
}

/**
 * Handle fetching and using the introduction data.
 *
 * @param  boolean $echo  Whether to echo or just return it.
 *
 * @return HTML
 */
function display_installer_introduction( $echo = true ) {

	// Check for the intro content.
	$intro_content  = Datasets\get_intro_content_data();

	// Confirm we didn't get a WP_Error return.
	$intro_content  = ! empty( $intro_content ) && is_wp_error( $intro_content ) ? array() : $intro_content;

	// Set an intro title fallback.
	$intro_headline = ! empty( $intro_content['headline'] ) ? $intro_content['headline'] : get_admin_page_title();

	// Set an empty.
	$build  = '';

	// Start with a div.
	$build .= '<div class="nexcess-mapps-dashboard-section-wrap nexcess-mapps-dashboard-intro-wrap">';

		// Display the headline if we have one.
		$build .= '<h1 class="nexcess-mapps-dashboard-intro-headline">' . esc_html( $intro_headline ) . '</h1>';

		// Display the subtitles if we have them.
		if ( ! empty( $intro_content['subtitles'] ) ) {

			// Loop and output each one.
			foreach ( (array) $intro_content['subtitles'] as $subtitle ) {
				$build .= '<p class="nexcess-mapps-dashboard-intro-subtitle">' . wp_kses_post( $subtitle ) . '</p>';
			}
		}

	// Close out my div.
	$build .= '</div>';

	// Return if requested.
	if ( ! $echo ) {
		return $build;
	}

	// Echo it out.
	echo $build;
}

/**
 * Build out the installation form and all it's fun.
 *
 * @param  array   $plugin_dataset  The dataset we're working with.
 * @param  boolean $echo            Whether to echo or just return it.
 *
 * @return HTML
 */
function display_installer_form( $plugin_dataset = array(), $echo = true ) {

	// Set an empty.
	$build  = '';

	// If we don't have any data, return some basic info.
	if ( empty( $plugin_dataset ) ) {

		// Set up the reply text.
		$build .= '<p>' . esc_html__( 'There was an error retrieving available plugin data.', 'nexcess-mapps-dashboard' ) . '</p>';

	} else {

		// Set a div around the big list.
		$build .= '<div class="nexcess-mapps-dashboard-form-section nexcess-mapps-dashboard-plugin-list">';

			// Show the dataset list.
			$build .= Formatting\format_available_plugin_list( $plugin_dataset );

		// Close the div for the plugins.
		$build .= '</div>';
	}

	// Return if requested.
	if ( ! $echo ) {
		return $build;
	}

	// Echo it out.
	echo $build;
}

/**
 * Handle all the specific actions tied to the plugins.
 *
 * @param  array   $plugin_groups  The different group names.
 * @param  boolean $echo           Whether to echo or just return it.
 *
 * @return HTML
 */
function display_installer_buttons( $plugin_groups = array(), $echo = true ) {

	// Bail without groups.
	if ( empty( $plugin_groups ) ) {
		return;
	}

	// Set an empty.
	$build  = '';

	// Set a div around the form actions.
	$build .= '<div class="nexcess-mapps-dashboard-form-section nexcess-mapps-dashboard-plugin-actions">';

		// Put our buttons in an unordered list.
		$build .= '<ul class="nexcess-mapps-dashboard-action-button-row">';

			// Handle our individual group buttons.
			$build .= Formatting\format_select_group_buttons( $plugin_groups );

			// Handle oursecondary and submit buttons.
			$build .= Formatting\format_secondary_select_buttons();

		// Close the unordered around the buttons.
		$build .= '</ul>';

		// Include the nonce field and trigger ID.
		$build .= '<input type="hidden" name="nexcess-mapps-dashboard-submit" value="1">';
		$build .= wp_nonce_field( 'nexcess-selfinstall-action', 'nexcess-selfinstall-nonce', false, false );

	// Close the div for the form actions.
	$build .= '</div>';

	// Return if requested.
	if ( ! $echo ) {
		return $build;
	}

	// Echo it out.
	echo $build;
}

/**
 * Fetch and return the content for the help tab.
 *
 * @param  boolean $echo  Whether to echo or just return it.
 *
 * @return HTML
 */
function display_admin_info_tab_introduction( $echo = true ) {

	// Set an empty.
	$build  = '';

	// Set some intro content.
	$build .= '<p>' . esc_html__( 'Want to learn more about the plugins we offer?', 'nexcess-mapps-dashboard' ) . '</p>';
	$build .= '<p>' . esc_html__( 'Select a group name from the menu and see details regarding the plugins.', 'nexcess-mapps-dashboard' ) . '</p>';

	// And return my build.
	return $build;

	// Return if requested.
	if ( ! $echo ) {
		return $build;
	}

	// Echo it out.
	echo $build;
}

/**
 * Format the individual list of plugins.
 *
 * @param  array   $plugin_list  The array of plugin data we have.
 * @param  boolean $echo         Whether to echo or just return it.
 *
 * @return HTML
 */
function display_admin_tab_plugin_list( $plugin_list = array(), $echo = true ) {

	// Bail without any data.
	if ( empty( $plugin_list ) ) {
		return;
	}

	// Format our table.
	$build  = Formatting\format_info_tab_plugin_group_table( $plugin_list );

	// Return if requested.
	if ( ! $echo ) {
		return $build;
	}

	// Echo it out.
	echo $build;
}

/**
 * Show the text to tell people to click the tab.
 *
 * @param  boolean $echo  Whether to echo or just return it.
 *
 * @return HTML
 */
function display_installer_fine_print( $echo = true ) {

	// Set an empty.
	$build  = '';

	// Set a paragraph around the text.
	$build .= '<p class="nexcess-mapps-dashboard-fine-print">';

		// Add the help icon.
		$build .= '<i class="dashicons dashicons-editor-help"></i>';

		// And the text itself.
		$build .= esc_html__( 'For more information regarding the plugins made available to you, click the help tab at the top of the screen.', $domain = 'nexcess-mapps-dashboard' );

	// Close the paragraph.
	$build .= '</p>';

	// Return if requested.
	if ( ! $echo ) {
		return $build;
	}

	// Echo it out.
	echo $build;
}
