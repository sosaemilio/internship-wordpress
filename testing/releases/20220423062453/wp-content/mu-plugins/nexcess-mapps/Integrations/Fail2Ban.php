<?php

/**
 * Platform integration with fail2ban.
 */

namespace Nexcess\MAPPS\Integrations;

use Nexcess\MAPPS\Concerns\HasHooks;
use Nexcess\MAPPS\Concerns\HasWordPressDependencies;
use Nexcess\MAPPS\Support\Stubs\Freemius;

class Fail2Ban extends Integration {
	use HasHooks;
	use HasWordPressDependencies;

	/**
	 * Determine whether or not this integration should be loaded.
	 *
	 * @return bool Whether or not this integration be loaded in this environment.
	 */
	public function shouldLoadIntegration() {
		return ! ( defined( 'WP_CLI' ) && WP_CLI )
			&& ! self::isPluginActive( 'wp-fail2ban/wp-fail2ban.php' )
			&& ! self::isPluginBeingActivated( 'wp-fail2ban/wp-fail2ban.php' )
			&& ! $this->isMuPluginInstalled( 'wp-fail2ban' );
	}

	/**
	 * Perform any necessary setup for the integration.
	 *
	 * This method is automatically called as part of Plugin::loadIntegration(), and is the
	 * entry-point for all integrations.
	 *
	 * @global $wf_fs
	 */
	public function setup() {
		global $wf_fs;

		// Short-circuit the Freemius integration.
		$wf_fs = new Freemius();

		$this->loadPlugin( 'wpackagist-plugin/wp-fail2ban/wp-fail2ban.php' );
		$this->addHooks();
	}

	/**
	 * Retrieve all actions for the integration.
	 *
	 * @return array[]
	 */
	protected function getActions() {
		// phpcs:disable WordPress.Arrays
		return [
			[ 'admin_menu',                 [ $this, 'removeAdminMenu'       ], 1 ],
			[ 'wp_dashboard_setup',         [ $this, 'removeDashboardWidget' ], 1 ],
			[ 'wp_network_dashboard_setup', [ $this, 'removeDashboardWidget' ], 1 ],
		];
		// phpcs:enable WordPress.Arrays
	}

	/**
	 * Remove the WP-fail2ban menu.
	 */
	public function removeAdminMenu() {
		remove_action( 'admin_menu', 'org\lecklider\charles\wordpress\wp_fail2ban\admin_menu' );
	}

	/**
	 * Remove the WP-fail2ban dashboard widget, introduced in 4.3.
	 */
	public function removeDashboardWidget() {
		remove_action( current_action(), 'org\lecklider\charles\wordpress\wp_fail2ban\wp_dashboard_setup' );
	}
}
