<?php

/**
 * A base definition for plugin overrides.
 */

namespace Nexcess\MAPPS\Plugins;

abstract class Plugin {

	/**
	 * The absolute path to the plugin directory.
	 *
	 * @var string
	 */
	protected $pluginDir;

	/**
	 * Actions to perform upon plugin activation.
	 *
	 * @param bool $network_wide Optional. Is the plugin being activated network-wide?
	 *                           Default is false.
	 */
	public function activate( $network_wide = false ) {
		// No-op by default.
	}

	/**
	 * Actions to perform upon plugin deactivation.
	 *
	 * @param bool $network_wide Optional. Is the plugin being deactivated network-wide?
	 *                           Default is false.
	 */
	public function deactivate( $network_wide = false ) {
		// No-op by default.
	}

	/**
	 * Actions to perform every time the plugin is loaded.
	 */
	public function load() {
		// No-op by default.
	}

	/**
	 * Set the plugin directory.
	 *
	 * @param string $dir The plugin directory.
	 *
	 * @return self
	 */
	public function setPluginDir( $dir ) {
		$this->pluginDir = untrailingslashit( $dir );

		return $this;
	}

	/**
	 * Actions to perform when the plugin is updated.
	 */
	public function update() {
		// No-op by default.
	}
}
