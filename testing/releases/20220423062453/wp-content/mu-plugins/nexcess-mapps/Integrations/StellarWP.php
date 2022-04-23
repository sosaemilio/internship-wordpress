<?php

namespace Nexcess\MAPPS\Integrations;

use Nexcess\MAPPS\Concerns\HasWordPressDependencies;
use Nexcess\MAPPS\Concerns\ManagesGroupedOptions;
use Nexcess\MAPPS\Services\Options;

/**
 * @group StellarWP
 */
class StellarWP extends Integration {
	use HasWordPressDependencies;
	use ManagesGroupedOptions;

	/**
	 * The option for disabling the StellarWP Plugin Panel.
	 */
	const OPTION_NAME = 'nexcess_mapps_stellarwp_plugin_installer';

	/**
	 * @var \Nexcess\MAPPS\Services\Options
	 */
	protected $options;

	/**
	 * @param Options $options
	 */
	public function __construct( Options $options ) {
		$this->options = $options;
	}

	/**
	 * Determine whether or not this integration should be loaded.
	 *
	 * @return bool Whether or not this integration be loaded in this environment.
	 */
	public function shouldLoadIntegration() {
		return ! self::isPluginActive( 'stellarwp-plugin-installer/stellarwp-plugin-installer.php' )
			&& ! self::isPluginBeingActivated( 'stellarwp-plugin-installer/stellarwp-plugin-installer.php' )
			&& ! is_multisite();
	}

	/**
	 * Perform necessary setup for this integration.
	 *
	 * This method is automatically called as part of Plugin::loadIntegration(), and is the
	 * entry-point for all integrations.
	 */
	public function setup() {
		if ( $this->getStellarWPPluginPanelSetting() ) {
			$this->loadPlugin( 'stellarwp/stellarwp-plugin-installer/stellarwp-plugin-installer.php' );
		}

		$this->registerOption();
	}

	/**
	 * Enable StellarWP Plugin Installer.
	 */
	public function enableStellarWPPluginPanel() {
		$this->getOption()->set( 'stellarwp_plugin_panel_is_enabled', true )->save();
	}

	/**
	 * Disable StellarWP Plugin Installer.
	 */
	public function disableStellarWPPluginPanel() {
		$this->getOption()->set( 'stellarwp_plugin_panel_is_enabled', false )->save();
	}

	/**
	 * Get current setting for the StellarWP Plugin Installer.
	 *
	 * @return bool
	 */
	public function getStellarWPPluginPanelSetting() {
		return $this->getOption()->stellarwp_plugin_panel_is_enabled;
	}

	/**
	 * Add a toggle to the settings page.
	 */
	public function registerOption() {
		$this->options->addOption(
			[ self::OPTION_NAME, 'stellarwp_plugin_panel_is_enabled' ],
			'checkbox',
			__( 'Enable StellarWP Plugin Integration', 'nexcess-mapps' ),
			[
				'description' => __( 'StellarWP plugins that can be used on your site.', 'nexcess-mapps' ),
				'default'     => true,
			]
		);
	}
}
