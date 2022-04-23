<?php

/**
 * Modify site behavior for staging sites.
 */

namespace Nexcess\MAPPS\Integrations;

use Nexcess\MAPPS\Concerns\HasDashboardNotices;
use Nexcess\MAPPS\Concerns\HasHooks;
use Nexcess\MAPPS\Services\WPConfig;
use Nexcess\MAPPS\Settings;
use Nexcess\MAPPS\Support\AdminNotice;

use const Nexcess\MAPPS\PLUGIN_VERSION;

class StagingSites extends Integration {
	use HasDashboardNotices;
	use HasHooks;

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
	const WP_CONFIG_UPDATED = 'NEXCESS_MAPPS_STAGING_SITE';

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
		return $this->settings->is_staging_site;
	}

	/**
	 * Perform any necessary setup for the integration.
	 *
	 * This method is automatically called as part of Plugin::loadIntegration(), and is the
	 * entry-point for all integrations.
	 */
	public function setup() {
		$this->addHooks();

		$this->addDashboardNotice( new AdminNotice(
			__( 'You are currently in a staging environment.', 'nexcess-mapps' ),
			'warning',
			false,
			'staging-notice'
		), 1 );
	}

	/**
	 * Retrieve all actions for the integration.
	 *
	 * @return array[]
	 */
	protected function getActions() {
		return [
			[ 'muplugins_loaded', [ $this, 'updateWpConfig' ] ],
		];
	}

	/**
	 * Retrieve all filters for the integration.
	 *
	 * @return array[]
	 */
	protected function getFilters() {
		return [
			// Block robots, regardless of the blog_public option.
			[ 'pre_option_blog_public', '__return_zero' ],

			// Don't send an email when the admin email changes.
			[ 'send_email_change_email', '__return_false' ],

			// Don't warn about pending activations within the Nexcess installer.
			[ 'pre_option_nexcess_selfinstall_pending_licensing', '__return_null' ],
		];
	}

	/**
	 * Update the wp-config.php file to prevent collisions with real sites.
	 */
	public function updateWpConfig() {
		// Only update the config once per release.
		if (
			defined( self::WP_CONFIG_UPDATED )
			&& version_compare( constant( self::WP_CONFIG_UPDATED ), PLUGIN_VERSION, '>=' )
		) {
			return;
		}

		// Re-check the environment (bypassing the cache) to ensure this is indeed a staging site.
		if ( 'staging' !== $this->settings->refresh()->environment ) {
			return;
		}

		// Explicitly override constants in the wp-config.php file.
		$this->config->setConstant( 'WP_ENVIRONMENT_TYPE', 'staging' );
		$this->config->setConstant( 'JETPACK_STAGING_MODE', true );
		$this->config->setConstant( 'WP_CACHE_KEY_SALT', uniqid( 'staging-site-' ) );
		$this->config->setConstant( 'RCP_DISABLE_EMAILS', true );

		/*
		 * Finally, flag the file as having been updated to prevent this method from running on
		 * subsequent requests.
		 */
		$this->config->setConstant( self::WP_CONFIG_UPDATED, PLUGIN_VERSION );
	}
}
