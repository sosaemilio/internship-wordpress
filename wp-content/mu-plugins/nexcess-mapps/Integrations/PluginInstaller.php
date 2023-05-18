<?php

/**
 * Integration with the plugin installer ("Nexcess MAPPS Dashboard") plugin.
 */

namespace Nexcess\MAPPS\Integrations;

use Nexcess\MAPPS\Concerns\HasCronEvents;
use Nexcess\MAPPS\Concerns\HasWordPressDependencies;
use Nexcess\MAPPS\Services\Installer;
use Nexcess\MAPPS\Services\Logger;
use Nexcess\MAPPS\Settings;
use Nexcess\MAPPS\Support\Deprecation;
use StellarWP\PluginFramework\Exceptions\InstallationException;
use StellarWP\PluginFramework\Exceptions\LicensingException;
use StellarWP\PluginFramework\Services\FeatureFlags;
use WP_Error;

class PluginInstaller extends Integration {
	use HasCronEvents;
	use HasWordPressDependencies;

	/**
	 * The daily cron action name.
	 */
	const DAILY_OCP_PLUGIN_MIGRATION_CRON_ACTION = 'nexcess_mapps_daily_ocp_plugin_migration';

	/**
	 * Action name used to prevent the check from running multiple times in one request.
	 */
	const MIGRATE_REDIS_TO_OCP_ACTION = 'Nexcess\\MAPPS\\Integrations\\PluginInstaller\\MigrateRedisCacheToOCP';

	/**
	 * @var \StellarWP\PluginFramework\Services\FeatureFlags
	 */
	protected $featureFlags;

	/**
	 * @var \Nexcess\MAPPS\Services\Logger
	 */
	protected $logger;

	/**
	 * @var \Nexcess\MAPPS\Settings
	 */
	protected $settings;

	/**
	 * @param \Nexcess\MAPPS\Settings                          $settings
	 * @param \Nexcess\MAPPS\Services\Logger                   $logger
	 * @param \StellarWP\PluginFramework\Services\FeatureFlags $feature_flags
	 */
	public function __construct( Settings $settings, Logger $logger, FeatureFlags $feature_flags ) {
		$this->settings     = $settings;
		$this->logger       = $logger;
		$this->featureFlags = $feature_flags;
	}

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

		// Register cron events.
		if ( $this->featureFlags->enabled( 'migrate-redis-cache-to-ocp' ) ) {
			$this->registerCronEvent( self::DAILY_OCP_PLUGIN_MIGRATION_CRON_ACTION, 'daily' );
		}
	}

	/**
	 * Retrieve all actions for the integration.
	 *
	 * @return array[]
	 */
	public function getActions() {
		return [
			[ 'upgrader_source_selection', [ $this, 'blockInstall' ] ],

			/*
			 * Daily operations:
			 *
			 * - Check if Redis Cache (free) is being used, and replace with Object Cache Pro
			 */
			[ self::DAILY_OCP_PLUGIN_MIGRATION_CRON_ACTION, [ $this, 'migrateToOCP' ] ],
		];
	}

	/**
	 * Checks to see if the site is running Redis Cache with a managed drop-in.
	 * If so, replace Redis Cache with Object Cache Pro.
	 */
	public function migrateToOCP() {

		// Make sure that we don't run this action multiple times in one request.
		if ( did_action( self::MIGRATE_REDIS_TO_OCP_ACTION ) ) {
			return;
		}

		// Fire the action to avoid a loop.
		do_action( self::MIGRATE_REDIS_TO_OCP_ACTION );

		// Bail if Redis Cache isn't installed.
		if ( ! $this->isPluginActive( 'redis-cache/redis-cache.php' ) ) {
			return;
		}

		// If we've already migrated to OCP, no need to check it and do it again.
		if ( ! empty( get_option( 'nexcess_did_migrate_redis_to_ocp' ) ) ) {
			return;
		}

		// Bail if the drop-in does not exist.
		if ( ! file_exists( WP_CONTENT_DIR . '/object-cache.php' ) ) {
			return;
		}

		// Remove Redis Cache.
		$this->deactivatePlugin( 'redis-cache/redis-cache.php' );

		// Install and activate OCP.
		$installer     = new Installer( $this->settings, $this->logger );
		$ocp_plugin_id = $this->settings->is_qa_environment ? 80 : 125;

		try {
			$installer->install( $ocp_plugin_id );
		} catch ( InstallationException $e ) {
			$this->logger->info( sprintf(
				/* Translators: %1$s is the previous exception message. */
				__( 'Unable to install Object Cache Pro: %1$s', 'nexcess-mapps' ),
				$e->getMessage()
			) );
			return;
		}

		try {
			$installer->license( $ocp_plugin_id );
		} catch ( LicensingException $e ) {
			$this->logger->info( sprintf(
				/* Translators: %1$s is the previous exception message. */
				__( 'Unable to license Object Cache Pro: %1$s', 'nexcess-mapps' ),
				$e->getMessage()
			) );
			return;
		}

		// Prevent this from happening again.
		update_option( 'nexcess_did_migrate_redis_to_ocp', true );

		$timestamp = wp_next_scheduled( self::DAILY_OCP_PLUGIN_MIGRATION_CRON_ACTION );
		if ( $timestamp ) {
			wp_unschedule_event( $timestamp, self::DAILY_OCP_PLUGIN_MIGRATION_CRON_ACTION );
		}
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
