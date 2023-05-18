<?php

/**
 * General cron configuration.
 */

namespace Nexcess\MAPPS\Integrations;

use Nexcess\MAPPS\Concerns\HasHooks;
use Nexcess\MAPPS\Services\AdminBar;
use Nexcess\MAPPS\Services\WPConfig;
use Nexcess\MAPPS\Support\AdminNotice;
use StellarWP\PluginFramework\Exceptions\RequestException;

class Cron extends Integration {
	use HasHooks;

	/**
	 * @var \Nexcess\MAPPS\Services\AdminBar
	 */
	protected $adminBar;

	/**
	 * @var \Nexcess\MAPPS\Services\WPConfig
	 */
	protected $config;

	/**
	 * @param \Nexcess\MAPPS\Services\AdminBar $admin_bar
	 * @param \Nexcess\MAPPS\Services\WPConfig $wp_config
	 */
	public function __construct( AdminBar $admin_bar, WPConfig $wp_config ) {
		$this->adminBar = $admin_bar;
		$this->config   = $wp_config;
	}

	/**
	 * Retrieve all actions for the integration.
	 *
	 * @return array[]
	 */
	protected function getActions() {
		return [
			[ 'admin_action_mapps_disable_wp_cron', [ $this, 'addDisableWpCronConstant' ] ],
		];
	}

	/**
	 * Retrieve all filters for the integration.
	 *
	 * @return array[]
	 */
	protected function getFilters() {
		return [
			[ 'cron_schedules', [ $this, 'defineCronSchedules' ], -9999 ],
		];
	}

	/**
	 * Define any custom cron intervals.
	 *
	 * This callback should be run as early as possible to avoid potential conflicts with customer
	 * modifications (e.g. if they've defined what "weekly" should look like, use their definition
	 * instead of ours).
	 *
	 * @param array[] $schedules Registered cron schedules.
	 *
	 * @return array[] The filtered $schedules array.
	 */
	public function defineCronSchedules( $schedules ) {
		// The "weekly" cron definition was added to WordPress core in version 5.4.
		if ( ! isset( $schedules['weekly'] ) ) {
			$schedules['weekly'] = [
				'interval' => WEEK_IN_SECONDS,
				'display'  => _x( 'Once a Week', 'time interval', 'nexcess-mapps' ),
			];
		}

		return $schedules;
	}

	/**
	 * Add the DISABLE_WP_CRON constant to the wp-config.php file.
	 *
	 * If the constant already exists, its value will be updated to "true".
	 *
	 * Arguments are passed via the $_POST superglobal:
	 *
	 * @type string $_wpnonce The nonce for action "mapps_add_disable_wp_cron_constant".
	 *
	 * @throws \StellarWP\PluginFramework\Exceptions\RequestException If the nonce is invalid.
	 */
	public function addDisableWpCronConstant() {
		try {
			if ( ! wp_verify_nonce( $_POST['_wpnonce'], 'mapps_add_disable_wp_cron_constant' ) ) {
				throw new RequestException( 'Nonce verification failed' );
			}

			$this->config->setConstant( 'DISABLE_WP_CRON', true );
			$notice = new AdminNotice(
				__( 'WP-Cron has been disabled in your wp-config.php file', 'nexcess-mapps' ),
				'success'
			);

			/*
			 * If the constant isn't currently defined for the request, explicitly set it.
			 *
			 * This prevents the CronConstant health check from failing for the current request,
			 * since we wrote the constant to wp-config.php *after* it was already loaded.
			 */
			defined( 'DISABLE_WP_CRON' ) || define( 'DISABLE_WP_CRON', true );
		} catch ( \Exception $e ) {
			$notice = new AdminNotice(
				/* Translators: %1$s is the caught exception's message. */
				sprintf( __( 'WP-Cron could not be disabled: %1$s', 'nexcess-mapps' ), $e->getMessage() ),
				'error'
			);
		}

		$this->adminBar->addNotice( $notice );
	}
}
