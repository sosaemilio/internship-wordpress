<?php
/**
 * Plugin Name: Nexcess Managed Apps Dashboard
 * Plugin URI:  https://www.nexcess.net
 * Description: Allows Nexcess customers to self-install specific plugins.
 * Version:     1.4.0
 * Author:      Nexcess
 * Author URI:  https://www.nexcess.net
 * Text Domain: nexcess-mapps-dashboard
 * Domain Path: /languages
 * License:     MIT
 * License URI: https://opensource.org/licenses/MIT
 *
 * @package PlatformSelfInstall
 */

// Call our namepsace.
namespace Nexcess\MAPPS\Dashboard;

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) exit;

// Define our version.
define( __NAMESPACE__ . '\VERS', '1.4.0' );

// Plugin Folder URL.
define( __NAMESPACE__ . '\URL', plugin_dir_url( __FILE__ ) );

// Plugin root file.
define( __NAMESPACE__ . '\FILE', __FILE__ );

// Plugin root file.
define( __NAMESPACE__ . '\PLUGIN', plugin_basename( __FILE__ ) );

// Set our assets directory constant.
define( __NAMESPACE__ . '\ASSETS_URL', URL . 'assets' );

// Menu page root.
define( __NAMESPACE__ . '\MENU_ROOT', 'nexcess-mapps-dashboard' );

// Option key prefix.
define( __NAMESPACE__ . '\OPTION_PREFIX', 'nexcess_selfinstall_' );

// Action and filter prefix.
define( __NAMESPACE__ . '\HOOK_PREFIX', 'nexcess_selfinstall_' );

// Define our transient time.
define( __NAMESPACE__ . '\CACHE_TIME', 5 * MINUTE_IN_SECONDS );

// Load the files assuming we have the environment constant.
if ( defined( 'NEXCESS_MAPPS_SITE' ) && false !== NEXCESS_MAPPS_SITE ) {
	nexcess_selfinstall_file_load();
}

/**
 * The function that loads the files.
 *
 * @return void
 */
function nexcess_selfinstall_file_load() {

	// Go and load our files.
	require_once __DIR__ . '/includes/helpers.php';
	require_once __DIR__ . '/includes/utilities.php';

	// Now load the files with the specific functionality we need.
	require_once __DIR__ . '/includes/external.php';
	require_once __DIR__ . '/includes/datasets.php';

	// Pull our formatter and markup.
	require_once __DIR__ . '/includes/structure/formatting.php';
	require_once __DIR__ . '/includes/structure/markup.php';

	// Split out the admin items.
	require_once __DIR__ . '/includes/admin/config.php';
	require_once __DIR__ . '/includes/admin/requests.php';
	require_once __DIR__ . '/includes/admin/display.php';
	require_once __DIR__ . '/includes/admin/notices.php';

	// Load the functions routing our installing.
	require_once __DIR__ . '/includes/routing/process.php';

	// And last, load the activations and whatnot.
	require_once __DIR__ . '/includes/activate.php';
	require_once __DIR__ . '/includes/deactivate.php';
	require_once __DIR__ . '/includes/uninstall.php';
}
