<?php

/**
 * Object cache integration for Nexcess MAPPS.
 */

namespace Nexcess\MAPPS\Integrations;

use Nexcess\MAPPS\Concerns\HasHooks;
use Nexcess\MAPPS\Concerns\HasWordPressDependencies;
use Nexcess\MAPPS\Concerns\InvokesCli;
use Nexcess\MAPPS\Modules\Telemetry;
use Nexcess\MAPPS\Plugins;
use Nexcess\MAPPS\Services\AdminBar;
use Nexcess\MAPPS\Services\Managers\PluginConfigManager;
use Nexcess\MAPPS\Settings;
use Nexcess\MAPPS\Support\AdminNotice;
use WP_Screen;

class ObjectCache extends Integration {
	use HasHooks;
	use HasWordPressDependencies;
	use InvokesCli;

	/**
	 * @var \Nexcess\MAPPS\Services\AdminBar
	 */
	protected $adminBar;

	/**
	 * @var \Nexcess\MAPPS\Services\Managers\PluginConfigManager
	 */
	protected $manager;

	/**
	 * @var \Nexcess\MAPPS\Settings
	 */
	protected $settings;

	/**
	 * The key used in the telemetry report which contains the relevant integration info.
	 */
	const TELEMETRY_FEATURE_KEY = 'object_cache';

	/**
	 * The key used to report whether the integration is enabled.
	 */
	const TELEMETRY_FEATURE_ENABLED_KEY = 'enabled';

	/**
	 * The key used to report the integration provider.
	 */
	const TELEMETRY_FEATURE_PROVIDER_KEY = 'provider';

	/**
	 * @param \Nexcess\MAPPS\Settings                              $settings
	 * @param \Nexcess\MAPPS\Services\AdminBar                     $admin_bar
	 * @param \Nexcess\MAPPS\Services\Managers\PluginConfigManager $manager
	 */
	public function __construct( Settings $settings, AdminBar $admin_bar, PluginConfigManager $manager ) {
		$this->settings = $settings;
		$this->adminBar = $admin_bar;
		$this->manager  = $manager;
	}

	/**
	 * Retrieve all actions for the integration.
	 *
	 * @return array[]
	 */
	protected function getActions() {
		// phpcs:disable WordPress.Arrays
		return [
			[ 'init',                                                 [ $this, 'registerAdminBarMenu'            ] ],
			[ 'admin_action_nexcess-mapps-flush-object-cache',        [ $this, 'adminBarFlushObjectCache'        ] ],
			[ 'admin_post_nexcess-mapps-flush-object-cache',          [ $this, 'adminBarFlushObjectCache'        ] ],
			[ 'admin_action_nexcess-mapps-delete-expired-transients', [ $this, 'adminBarDeleteExpiredTransients' ] ],
			[ 'admin_post_nexcess-mapps-delete-expired-transients',   [ $this, 'adminBarDeleteExpiredTransients' ] ],
		];
		// phpcs:enable WordPress.Arrays
	}

	/**
	 * Retrieve all filters for the integration.
	 *
	 * @return array[]
	 */
	protected function getFilters() {
		// phpcs:disable WordPress.Arrays
		return [
			[ 'default_hidden_meta_boxes', [ $this, 'hideObjectCacheProDashboardWidget' ], 10, 2 ],
			[ Telemetry::REPORT_DATA_FILTER, [ $this, 'addFeatureToTelemetry' ] ],
			[ 'objectcache_omit_settings_pointer', '__return_true' ],
			[ 'site_status_persistent_object_cache_url', [ $this, 'siteStatusPersistentObjectCacheUrl' ] ]
		];
		// phpcs:enable WordPress.Arrays
	}

	/**
	 * Register the admin bar menu item.
	 */
	public function registerAdminBarMenu() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		if ( wp_using_ext_object_cache() ) {
			$this->adminBar->addMenu(
				'flush-object-cache',
				AdminBar::getActionPostForm(
					'nexcess-mapps-flush-object-cache',
					_x( 'Flush object cache', 'admin bar menu title', 'nexcess-mapps' )
				)
			);
		}

		$this->adminBar->addMenu(
			'delete-expired-transients',
			AdminBar::getActionPostForm(
				'nexcess-mapps-delete-expired-transients',
				_x( 'Delete expired transients', 'admin bar menu title', 'nexcess-mapps' )
			)
		);
	}

	/**
	 * Callback for requests to flush the object cache via the Admin Bar.
	 */
	public function adminBarFlushObjectCache() {
		if ( ! AdminBar::validateActionNonce( 'nexcess-mapps-flush-object-cache' ) ) {
			return $this->adminBar->addNotice( new AdminNotice(
				__( 'We were unable to flush the object cache, please try again.', 'nexcess-mapps' ),
				'error',
				true
			) );
		}

		wp_cache_flush();

		$this->adminBar->addNotice( new AdminNotice(
			__( 'The object cache has been flushed successfully!', 'nexcess-mapps' ),
			'success',
			true
		) );

		// If we have a referrer, we likely came from the front-end of the site.
		$referrer = wp_get_referer();

		if ( $referrer ) {
			return wp_safe_redirect( $referrer );
		}
	}

	/**
	 * Callback for requests to delete expired transients via the Admin Bar.
	 */
	public function adminBarDeleteExpiredTransients() {
		if ( ! AdminBar::validateActionNonce( 'nexcess-mapps-delete-expired-transients' ) ) {
			return $this->adminBar->addNotice( new AdminNotice(
				__( 'We were unable to delete expired transients, please try again.', 'nexcess-mapps' ),
				'error',
				true
			) );
		}

		delete_expired_transients( true );

		$this->adminBar->addNotice( new AdminNotice(
			__( 'Expired transients have been deleted!', 'nexcess-mapps' ),
			'success',
			true
		) );

		// If we have a referrer, we likely came from the front-end of the site.
		$referrer = wp_get_referer();

		if ( $referrer ) {
			return wp_safe_redirect( $referrer );
		}
	}

	/**
	 * Hide the Object Cache Pro dashboard widget by default.
	 *
	 * @param string[]   $hidden The meta boxes hidden by default.
	 * @param \WP_Screen $screen The current WP_Screen object.
	 */
	public function hideObjectCacheProDashboardWidget( array $hidden, WP_Screen $screen ) {
		if ( in_array( $screen->id, [ 'dashboard', 'dashboard-network' ], true ) ) {
			$hidden[] = 'dashboard_rediscachepro';
		}

		return $hidden;
	}

	/**
	 * Find the current object cache plugin (if one exists) and symlink its object-cache.php drop-in.
	 */
	public function installObjectCacheDropIn() {
		// Known object cache plugins, in order of priority.
		$plugins = [
			Plugins\ObjectCachePro::class,
			Plugins\RedisCache::class,
			Plugins\WPRedis::class,
		];

		foreach ( $plugins as $plugin ) {
			$instances = $this->manager->resolved( $plugin );

			if ( ! empty( $instances ) ) {
				current( $instances )->activate();
				break;
			}
		}
	}

	/**
	 * Filter the Persistent object cache URL.
	 * Replace the original WordPress guide with our own.
	 *
	 * @return string URL to object cache guide
	 */
	public function siteStatusPersistentObjectCacheUrl() {
		return 'https://www.nexcess.net/help/enabling-redis-object-caching/';
	}

	/**
	 * Adds feature integration information to the telemetry report.
	 *
	 * @param array[] $report The gathered report data.
	 *
	 * @return array[] The $report array.
	 */
	public function addFeatureToTelemetry( array $report ) {
		$type    = $this->makeCommand( 'wp cache type' )
			->setPriority( 10 )
			->setTimeout( 60 )
			->execute()
			->getOutput();
		$enabled = 'Default' !== $type;

		$report['features'][ self::TELEMETRY_FEATURE_KEY ] = [
			self::TELEMETRY_FEATURE_ENABLED_KEY  => $enabled,
			self::TELEMETRY_FEATURE_PROVIDER_KEY => $enabled ? $type : null,
		];

		return $report;
	}
}
