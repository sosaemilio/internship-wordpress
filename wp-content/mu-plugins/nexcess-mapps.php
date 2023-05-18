<?php
/**
 * Plugin Name: Nexcess Managed Apps
 * Plugin URI:  https://www.nexcess.net
 * Description: Functionality to support the Nexcess Managed Apps WordPress and WooCommerce platforms.
 * Version:     1.40.0
 * Author:      Nexcess
 * Author URI:  https://www.nexcess.net
 * Text Domain: nexcess-mapps
 * Awesome:     Yes.
 *
 * For details on how to customize the MU plugin behavior, please see nexcess-mapps/README.md.
 */

namespace Nexcess\MAPPS;

use Nexcess\MAPPS\Exceptions\IsNotNexcessSiteException;
use Nexcess\MAPPS\Support\PlatformRequirements;
use StellarWP\PluginFramework\Support\Branding;

// At this time, the MU plugin doesn't need to do anything if WordPress is currently installing.
if ( defined( 'WP_INSTALLING' ) && WP_INSTALLING ) {
	return;
}

// If the plugin isn't being loaded as an mu-plugin, then don't let it load. We hook in earlier
// than `plugins_loaded`, so loading as a normal plugin or another way is unsupported.
if ( did_action( 'muplugins_loaded' ) ) {
	return;
}

// The version of the Nexcess Managed Apps plugin.
define( __NAMESPACE__ . '\PLUGIN_VERSION', '1.40.0' );
define( __NAMESPACE__ . '\PLUGIN_URL', plugins_url( '', __FILE__ ) );
define( __NAMESPACE__ . '\PLUGIN_DIR', __DIR__ . '/nexcess-mapps/' );
define( __NAMESPACE__ . '\VENDOR_DIR', __DIR__ . '/nexcess-mapps/vendor/' );

// Initialize the plugin.
try {
	require_once VENDOR_DIR . 'autoload.php';

	// Check for anything that might prevent the plugin from loading.
	$requirements = new PlatformRequirements();

	if ( ! $requirements->siteMeetsMinimumRequirements() ) {
		return $requirements->renderUnsupportedWordPressVersionNotice();
	}

	// Finish loading files that should be explicitly required.
	require_once __DIR__ . '/nexcess-mapps/Support/Compat.php';
	require_once __DIR__ . '/nexcess-mapps/vendor/stevegrunwell/wp-admin-tabbed-settings-pages/wp-admin-tabbed-settings-pages.php';

	// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
	$container_pf = new ContainerPF();
	$container    = new Container();

	$container_pf->get( PluginPF::class )->init();
	$container->extend( ContainerPF::class, function() use ( $container_pf ) {
		return $container_pf;
	} );
	// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
	$plugin = $container->get( Plugin::class );
	$plugin->bootstrap();
} catch ( IsNotNexcessSiteException $e ) {
	$container = new Container();
	// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_trigger_error
	trigger_error( esc_html( sprintf(
		'The %1$s plugin may only be loaded on the %2$s platform.',
		Branding::getCompanyName(),
		Branding::getPlatformName()
	) ), E_USER_NOTICE );
} catch ( \Exception $e ) {
	$container = new Container();
	// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_trigger_error
	trigger_error( esc_html( sprintf(
		'%1$s Error: %2$s',
		Branding::getPlatformName(),
		$e->getMessage()
	) ), E_USER_WARNING );
}
