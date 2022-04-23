<?php

/**
 * Modify site behavior for regression sites.
 */

namespace Nexcess\MAPPS\Integrations;

use Nexcess\MAPPS\Concerns\HasAssets;
use Nexcess\MAPPS\Concerns\HasHooks;
use Nexcess\MAPPS\Concerns\HasPluggables;
use Nexcess\MAPPS\Services\WPConfig;
use Nexcess\MAPPS\Settings;
use WP_Query;

class RegressionSites extends Integration {
	use HasAssets;
	use HasHooks;
	use HasPluggables;

	/**
	 * @var \Nexcess\MAPPS\Services\WPConfig
	 */
	protected $config;

	/**
	 * @var \Nexcess\MAPPS\Settings
	 */
	protected $settings;

	/**
	 * Used to indicate that the wp-config.php file has already been updated.
	 *
	 * @var string
	 */
	const WP_CONFIG_UPDATED = 'NEXCESS_MAPPS_REGRESSION_SITE';

	/**
	 * @param \Nexcess\MAPPS\Settings          $settings
	 * @param \Nexcess\MAPPS\Services\WPConfig $wp_config
	 */
	public function __construct( Settings $settings, WPConfig $wp_config ) {
		$this->settings = $settings;
		$this->config   = $wp_config;
	}

	/**
	 * Determine whether or not this integration should be loaded.
	 *
	 * @return bool Whether or not this integration be loaded in this environment.
	 */
	public function shouldLoadIntegration() {
		return $this->settings->is_regression_site;
	}

	/**
	 * Perform any necessary setup for the integration.
	 *
	 * This method is automatically called as part of Plugin::loadIntegration(), and is the
	 * entry-point for all integrations.
	 */
	public function setup() {
		$this->addHooks();
		$this->seedRandomNumberGenerator();

		// If the site is running Sucuri, ensure scans aren't being run.
		remove_all_actions( 'sucuriscan_scheduled_scan' );
	}

	/**
	 * Retrieve all actions for the integration.
	 *
	 * @return array[]
	 */
	protected function getActions() {
		// phpcs:disable WordPress.Arrays
		return [
			[ 'muplugins_loaded', [ $this, 'updateWpConfig' ] ],
			[ 'plugins_loaded',    [ $this, 'loadPluggables' ] ],
			[ 'wp_enqueue_scripts', [ $this, 'enqueueScripts' ], 1  ],
		];
		// phpcs:enable WordPress.Arrays
	}

	/**
	 * Retrieve all filters for the integration.
	 *
	 * @return array[]
	 */
	protected function getFilters() {
		return [
			// Set Jetpack CDN to passthrough images without caching.
			[ 'jetpack_photon_development_mode', '__return_true' ],

			// Block robots, regardless of the blog_public option.
			[ 'pre_option_blog_public', '__return_zero' ],

			// Reduce randomness to prevent false negatives.
			[ 'posts_orderby', [ $this, 'preventRandomQueryResults' ], 100, 2 ],

			// Prevent mail from being sent on WordPress 5.7+.
			[ 'pre_wp_mail', '__return_true', PHP_INT_MAX ],

			// Fallback to prevent emails from being sent to customers.
			[ 'wp_mail', [ $this, 'rerouteEmails' ], PHP_INT_MAX ],
		];
	}

	/**
	 * Prevent queries from using "ORDER BY RAND()".
	 *
	 * Since displaying content in random orders can cause the regression tool to think something
	 * has changed, replace any "ORDER BY RAND()" clauses going through $wpdb to use predictable
	 * values (e.g. the ID).
	 *
	 * @param string   $orderby The ORDER BY clause of the query.
	 * @param WP_Query $query   The WP_Query instance (passed by reference).
	 */
	public function preventRandomQueryResults( $orderby, WP_Query $query ) {
		return false === stripos( $orderby, 'RAND()' ) ? $orderby : '';
	}

	/**
	 * Route all emails to an unused email address.
	 *
	 * If we're unable to replace wp_mail() for some reason, this ensures that emails get routed to
	 * an email address that discards all messages.
	 *
	 * @param mixed[] $args A compacted array of wp_mail() arguments, including the "to" email,
	 *                      subject, message, headers, and attachments values.
	 *
	 * @return mixed[] The $args array with 'to' changed to an unused email address.
	 */
	public function rerouteEmails( $args ) {
		$args['to'] = 'devnull@nexcess.net';

		return $args;
	}

	/**
	 * Seed PHP's random number generator.
	 *
	 * While we'd never want to do this in production environments, seeding the generators will
	 * produce more predictable results between runs on regression sites.
	 *
	 * @global $rnd_value
	 */
	public function seedRandomNumberGenerator() {
		// phpcs:ignore WordPress.WP.AlternativeFunctions.rand_seeding_mt_srand
		mt_srand( 1 );
	}

	/**
	 * Update the wp-config.php file to prevent collisions with real sites.
	 */
	public function updateWpConfig() {
		if ( defined( self::WP_CONFIG_UPDATED ) || $this->config->hasConstant( self::WP_CONFIG_UPDATED ) ) {
			return;
		}

		// Re-check the environment (bypassing the cache) to ensure this is indeed a regression site.
		if ( 'regression' !== $this->settings->refresh()->environment ) {
			return;
		}

		// Explicitly override constants in the wp-config.php file.
		$this->config->setConstant( 'WP_ENVIRONMENT_TYPE', 'staging' );
		$this->config->setConstant( 'WP_CACHE_KEY_SALT', uniqid( 'regression-site-' ) );

		// Jetpack.
		$this->config->setConstant( 'JETPACK_STAGING_MODE', true );

		// Redis Cache, WP-Redis.
		$this->config->setConstant( 'WP_REDIS_DISABLED', true );

		// Wordfence.
		$this->config->setConstant( 'WFWAF_ENABLED', false );

		// iThemes Security / Security Pro.
		$this->config->setConstant( 'ITSEC_DISABLE_MODULES', true );

		/*
		 * Finally, flag the file as having been updated to prevent this method from running on
		 * subsequent requests.
		 */
		$this->config->setConstant( self::WP_CONFIG_UPDATED, true );
	}

	/**
	 * Register and/or enqueue custom scripts and styles.
	 */
	public function enqueueScripts() {
		$this->enqueueScript( 'nexcess-mapps-disable-animations', 'disable-animations.js' );
	}
}
