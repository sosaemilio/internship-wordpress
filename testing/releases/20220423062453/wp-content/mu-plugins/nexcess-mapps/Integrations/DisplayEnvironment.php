<?php

/**
 * Display the current environment type in the Admin Bar.
 */

namespace Nexcess\MAPPS\Integrations;

use Nexcess\MAPPS\Concerns\HasWordPressDependencies;

class DisplayEnvironment extends Integration {
	use HasWordPressDependencies;

	/**
	 * Determine whether or not this integration should be loaded.
	 *
	 * @return bool Whether or not this integration be loaded in this environment.
	 */
	public function shouldLoadIntegration() {
		return $this->siteIsAtLeastWordPressVersion( '5.5' )
			&& ! ( defined( 'WP_CLI' ) && WP_CLI )
			&& ! $this->isPluginActive( 'where/where.php' )
			&& ! $this->isPluginBeingActivated( 'where/where.php' )
			&& apply_filters( 'nexcess_mapps_enable_environment_indicator', true );
	}

	/**
	 * Perform any necessary setup for the integration.
	 *
	 * This method is automatically called as part of Plugin::loadIntegration(), and is the
	 * entry-point for all integrations.
	 */
	public function setup() {
		$this->loadPlugin( 'bradp/where/where.php' );
	}
}
