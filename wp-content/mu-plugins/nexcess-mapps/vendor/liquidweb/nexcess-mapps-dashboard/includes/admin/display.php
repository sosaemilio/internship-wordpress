<?php
/**
 * The display and layout functions tied to the wp-admin area.
 *
 * @package PlatformSelfInstall
 */

// Call our namepsace.
namespace Nexcess\MAPPS\Dashboard\Admin\Display;

// Set our alias items.
use Nexcess\MAPPS\Dashboard as Core;
use Nexcess\MAPPS\Dashboard\Helpers as Helpers;
use Nexcess\MAPPS\Dashboard\Utilities as Utilities;
use Nexcess\MAPPS\Dashboard\Datasets as Datasets;
use Nexcess\MAPPS\Dashboard\Structure\Markup as Markup;
use Nexcess\MAPPS\Dashboard\Structure\Formatting as Formatting;

// And pull in any other namespaces.
use WP_Error;

/**
 * Start our engines.
 */
add_action( 'admin_enqueue_scripts', __NAMESPACE__ . '\load_admin_display_assets' );
add_action( 'admin_menu', __NAMESPACE__ . '\setup_admin_menu_page', 99 );

/**
 * Load our admin side JS and CSS.
 *
 * @param $hook  Admin page hook we are current on.
 *
 * @return void
 */
function load_admin_display_assets( $hook ) {

	// Check our admin screen.
	$maybe  = Utilities\check_admin_screen();

	// Bail if we aren't in the right spot.
	if ( ! $maybe ) {
		return;
	}

	// Set my handle.
	$handle = 'nexcess-mapps-dashboard-admin';

	// Set a file suffix structure based on whether or not we want a minified version.
	$file   = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ? $handle : $handle . '.min';

	// Set a version for whether or not we're debugging.
	$vers   = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ? time() : Core\VERS;

	// Load our CSS file.
	wp_enqueue_style( $handle, Core\ASSETS_URL . '/css/' . $file . '.css', false, $vers, 'all' );

	// And our JS.
	wp_enqueue_script( $handle, Core\ASSETS_URL . '/js/' . $file . '.js', array( 'jquery' ), $vers, true );
	wp_localize_script( $handle, 'PlatformInstallAdmin', array(
		'resetText'     => __( 'Are you sure you want to reset your choices?', 'nexcess-mapps-dashboard' ),
		'installText'   => __( 'Are you sure you want to install the selected plugins?', 'nexcess-mapps-dashboard' ),
	//	'overlayWindow' => Markup\display_installer_overlay( false ),
	));
}

/**
 * Handle adding the submenu page.
 *
 * @return void
 */
function setup_admin_menu_page() {

	// If running within the Nexcess MAPPS MU plugin, register as a submenu of "Nexcess".
	if ( defined( '\\Nexcess\\MAPPS\\Integrations\\Dashboard::ADMIN_MENU_SLUG' ) ) {
		$hook = add_submenu_page(
			\Nexcess\MAPPS\Integrations\Dashboard::ADMIN_MENU_SLUG,
			_x( 'Nexcess Plugin Installer', 'page title', 'nexcess-mapps-dashboard' ),
			_x( 'Install Plugins', 'menu title', 'nexcess-mapps-dashboard' ),
			'install_plugins',
			Core\MENU_ROOT,
			__NAMESPACE__ . '\build_admin_display'
		);
	} else {
		$hook = add_dashboard_page(
			__( 'Nexcess Managed Apps Dashboard', 'nexcess-mapps-dashboard' ),
			__( 'Nexcess Installer', 'nexcess-mapps-dashboard' ),
			'manage_options',
			Core\MENU_ROOT,
			__NAMESPACE__ . '\build_admin_display'
		);
	}

	// Include the info tab loading.
	add_action( 'load-' . $hook, __NAMESPACE__ . '\build_admin_info_tab' );

	// Add our hook for loading up tabs.
	do_action( Core\HOOK_PREFIX . 'dashboard_menu_loaded', $hook );
}

/**
 * Load up the help tab we have.
 *
 * @return void
 */
function build_admin_info_tab() {

	// Check the admin screen and use that to load the tab.
	$screen = Utilities\check_admin_screen( false, 'screen' );

	// Bail if we aren't in the right spot.
	if ( ! $screen ) {
		return;
	}

	// Confirm we have a dataset to show.
	$plugin_dataset = Datasets\get_available_plugins_dataset();

	// Bail without data to display.
	if ( empty( $plugin_dataset ) || is_wp_error( $plugin_dataset ) ) {
		return;
	}

	// First load the primary tab.
	$screen->add_help_tab( array(
		'id'      => Core\MENU_ROOT . '-main-info-tab',
		'title'   => __( 'More Information', 'nexcess-mapps-dashboard' ),
		'content' => Markup\display_admin_info_tab_introduction( false ),
	) );

	// Now loop each group and render a list.
	foreach ( $plugin_dataset as $plugin_group => $plugin_list ) {

		// Now load the tab.
		$screen->add_help_tab( array(
			'id'      => Core\MENU_ROOT . '-' . sanitize_html_class( $plugin_group ) . '-list-tab',
			'title'   => Formatting\texturize_group_name( $plugin_group ),
			'content' => Markup\display_admin_tab_plugin_list( $plugin_list, false ),
		) );
	}

	// No more tabs to add.
}

/**
 * Our admin page inside the plugins menu.
 *
 * @return void
 */
function build_admin_display() {

	// Bail on a non authorized user.
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( __( 'You are not authorized to view this page.', 'nexcess-mapps-dashboard' ), __( 'Nexcess Managed Apps Install', 'nexcess-mapps-dashboard' ) );
	}

	// Handle the before action on the admin page.
	do_action( Core\HOOK_PREFIX . 'before_install_page_display' );

	// Get my plugin dataset.
	$plugin_dataset = Datasets\get_available_plugins_dataset();

	// Give ourselves a filter to modify the dataset if need be.
	apply_filters( Core\HOOK_PREFIX . 'plugin_dataset_admin_display', $plugin_dataset );

	// Bail without data to display.
	if ( is_wp_error( $plugin_dataset ) ) {
		return Markup\display_installer_api_error();
	} elseif ( empty( $plugin_dataset ) ) {
		return Markup\display_installer_missing_dataset();
	}

	// Get our admin link.
	$form_post_link = Utilities\get_installer_menu_link();

	// Parse the group names.
	$plugin_groups  = array_keys( $plugin_dataset );

	// Build out the page.
	echo '<div class="wrap nexcess-mapps-dashboard-page-wrap">';

		// Handle the intro portion.
		Markup\display_installer_introduction();

		// Set a div wrapper around the form markup.
		echo '<div class="nexcess-mapps-dashboard-section-wrap nexcess-mapps-dashboard-form-wrap">';

			// Wrap the form itself.
			echo '<form class="nexcess-mapps-dashboard-form-block" id="nexcess-mapps-dashboard-form" method="post" action="' . esc_url( $form_post_link ) . '">';

				// Show the dataset list.
				Markup\display_installer_form( $plugin_dataset );

				// Show the various buttons.
				Markup\display_installer_buttons( $plugin_groups );

			// Close up the form markup.
			echo '</form>';

			// Handle our fine print.
			Markup\display_installer_fine_print();

		// Close out the form wrapper.
		echo '</div>';

	// Close out the page.
	echo '</div>';

	// Handle the after action on the admin page.
	do_action( Core\HOOK_PREFIX . 'after_install_page_display' );
}
