<?php

/**
 * Apply custom rules when a plugin is activated or deactivated.
 */

namespace Nexcess\MAPPS\Integrations;

use Nexcess\MAPPS\Concerns\HasHooks;
use Nexcess\MAPPS\Services\Managers\PluginConfigManager;
use WP_Upgrader;

class PluginConfig extends Integration {
	use HasHooks;

	/**
	 * @var PluginConfigManager
	 */
	protected $manager;

	/**
	 * @param PluginConfigManager $manager
	 */
	public function __construct( PluginConfigManager $manager ) {
		$this->manager = $manager;
	}

	/**
	 * Retrieve all actions for the integration.
	 *
	 * @return array[]
	 */
	protected function getActions() {
		// phpcs:disable WordPress.Arrays
		return [
			[ 'activate_plugin',           [ $this, 'activatePlugin'   ], 10, 2 ],
			[ 'deactivate_plugin',         [ $this, 'deactivatePlugin' ], 10, 2 ],
			[ 'plugin_loaded',             [ $this, 'loadPlugin'       ], 10    ],
			[ 'upgrader_process_complete', [ $this, 'updatePlugins'    ], 10, 2 ],
		];
		// phpcs:enable WordPress.Arrays
	}

	/**
	 * Handle plugin activation.
	 *
	 * @param string $plugin       The plugin name.
	 * @param bool   $network_wide Whether or not the plugin is being activated across the network.
	 *                             Default is false.
	 */
	public function activatePlugin( $plugin, $network_wide = false ) {
		if ( ! is_string( $plugin ) || ! $this->manager->has( $plugin ) ) {
			return;
		}

		$this->manager->get( $plugin )->activate( $network_wide );
	}

	/**
	 * Handle plugin deactivation.
	 *
	 * @param string $plugin       The plugin name.
	 * @param bool   $network_wide Whether or not the plugin is being deactivated across the network.
	 *                             Default is false.
	 */
	public function deactivatePlugin( $plugin, $network_wide = false ) {
		if ( ! is_string( $plugin ) || ! $this->manager->has( $plugin ) ) {
			return;
		}

		$this->manager->make( $plugin )->deactivate( $network_wide );
	}

	/**
	 * Handle the loading of plugins.
	 *
	 * @param string $plugin The plugin name.
	 */
	public function loadPlugin( $plugin ) {
		if ( ! is_string( $plugin ) || ! $this->manager->has( $plugin ) ) {
			return;
		}

		$this->manager->get( $plugin )->load();
	}

	/**
	 * Handle plugin updates.
	 *
	 * @param WP_Upgrader $upgrader The WP_Upgrader instance.
	 * @param mixed[]     $data     Details about the upgraded plugin(s).
	 */
	public function updatePlugins( WP_Upgrader $upgrader, array $data ) {
		if ( empty( $data['plugins'] ) ) {
			return;
		}

		foreach ( (array) $data['plugins'] as $plugin ) {
			if ( ! $this->manager->has( $plugin ) ) {
				continue;
			}

			$this->manager->get( $plugin )->update();
		}
	}
}
