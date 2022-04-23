<?php

/**
 * Integration with the plugin installer ("Nexcess MAPPS Dashboard") plugin.
 */

namespace Nexcess\MAPPS\Integrations;

use Nexcess\MAPPS\Concerns\HasHooks;
use Nexcess\MAPPS\Concerns\HasWordPressDependencies;
use Nexcess\MAPPS\Support\Deprecation;
use WP_Error;

class PluginInstaller extends Integration {
	use HasHooks;
	use HasWordPressDependencies;

	/**
	 * Determine whether or not this integration should be loaded.
	 *
	 * @return bool Whether or not this integration should be loaded in this environment.
	 */
	public function shouldLoadIntegration() {
		return ! ( defined( 'WP_CLI' ) && WP_CLI )
			&& ! $this->hasBeenDisabledThroughLegacyMeans()
			&& apply_filters( 'nexcess_mapps_show_plugin_installer', true )
			&& $this->siteIsAtLeastWordPressVersion( '5.0' );
	}

	/**
	 * Perform any necessary setup for the integration.
	 *
	 * This method is automatically called as part of Plugin::loadIntegration(), and is the
	 * entry-point for all integrations.
	 */
	public function setup() {
		$this->addHooks();

		// Load the version bundled with this plugin.
		$this->loadPlugin( 'liquidweb/nexcess-mapps-dashboard/nexcess-mapps-dashboard.php' );
	}

	/**
	 * Retrieve all actions for the integration.
	 *
	 * @return array[]
	 */
	public function getActions() {
		return [
			[ 'upgrader_source_selection', [ $this, 'blockInstall' ] ],
		];
	}

	/**
	 * Prevent customers from installing their own copy of the installer plugin.
	 *
	 * @param string $source File source location.
	 *
	 * @return string|WP_Error Either the unmodified $source string or a WP_Error object
	 *                         (in order to prevent installation).
	 */
	public function blockInstall( $source ) {
		// If the extracted directory starts with "nexcess-mapps-dashboard", abort installation.
		if ( preg_match( '/^nexcess-mapps-dashboard/i', basename( $source ) ) ) {
			return new WP_Error(
				'nexcess-mapps-plugin-already-installed',
				__( 'The Nexcess MAPPS Dashboard plugin is already available on this site, aborting installation.', 'nexcess-mapps' )
			);
		}

		return $source;
	}

	/**
	 * Check to see if the integration has been disabled by legacy means.
	 *
	 * The proper way to disable the plugin installer moving forward is via the
	 * 'nexcess_mapps_show_plugin_installer' filter.
	 *
	 * @return bool TRUE if a legacy method has been used to disable the integration.
	 */
	public function hasBeenDisabledThroughLegacyMeans() {
		$disabled = false;

		/*
		 * This constant (introduced in 1.7.0) was never meant for customer use, but to prevent the auto-cleanup of
		 * development copies of the liquidweb/nexcess-mapps-dashboard plugin.
		 *
		 * The auto-removal was removed in version 1.11.0.
		 */
		if ( defined( 'NEXCESS_MAPPS_USE_LOCAL_DASHBOARD' ) ) {
			Deprecation::constant( 'NEXCESS_MAPPS_USE_LOCAL_DASHBOARD', '1.12.0', 'nexcess_mapps_show_plugin_installer' );

			$disabled = (bool) NEXCESS_MAPPS_USE_LOCAL_DASHBOARD;
		}

		// The "nexcess_mapps_disable_dashboard" filter was added in v1.11.0 and replaced in 1.12.0.
		if ( false !== has_filter( 'nexcess_mapps_disable_dashboard' ) ) {
			Deprecation::filter( 'nexcess_mapps_disable_dashboard', '1.12.0', 'nexcess_mapps_show_plugin_installer' );

			$disabled = apply_filters( 'nexcess_mapps_disable_dashboard', $disabled );
		}

		return $disabled;
	}
}
