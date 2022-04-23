<?php

namespace Nexcess\MAPPS\Commands;

use Nexcess\MAPPS\Integrations\StellarWP as Integration;
use Nexcess\MAPPS\Settings;

/**
 * WP-CLI sub-commands for the StellarWP Plugin Installer.
 */
class StellarWP extends Command {

	/**
	 * @var \Nexcess\MAPPS\Integrations\StellarWP
	 */
	protected $integration;

	/**
	 * @var Settings
	 */
	protected $settings;

	/**
	 * @param Settings                              $settings
	 * @param \Nexcess\MAPPS\Integrations\StellarWP $integration
	 */
	public function __construct( Settings $settings, Integration $integration ) {
		$this->settings    = $settings;
		$this->integration = $integration;
	}

	/**
	 * Enable the StellarWP Plugin Installer integration for this site.
	 */
	public function enable() {
		$this->integration->enableStellarWPPluginPanel();

		$this->success( 'The StellarWP Plugin Installer integration has been enabled for this site!' );
	}

	/**
	 * Disable the StellarWP Plugin Installer integration for this site.
	 */
	public function disable() {
		$this->integration->disableStellarWPPluginPanel();

		$this->success( 'The StellarWP Plugin Installer integration has been disabled for this site!' );
	}

	/**
	 * Show status of the StellarWP Plugin Installer integration.
	 */
	public function status() {
		if ( $this->integration->getStellarWPPluginPanelSetting() ) {
			$this->log( 'StellarWP Plugin Installer integration Status: Enabled.' );
		} else {
			$this->log( 'StellarWP Plugin Installer integration Status: Disabled.' );
		}
	}
}
