<?php

namespace Nexcess\MAPPS;

use Nexcess\MAPPS\Concerns\HasFlags;
use Nexcess\MAPPS\Support\GroupedOption;
use StellarWP\PluginFramework\Settings as BaseSettings;

/**
 * @codingStandardsIgnoreStart
 */
/**
 * @property-read int    $account_id                      The Nexcess cloud account (site) ID.
 * @property-read bool   $autoscaling_enabled             TRUE if autoscaling is enabled for this site.
 * @property-read string $canny_board_token               The Canny board token used for the collecting customer feedback.
 * @property-read int    $client_id                       The Nexcess client ID.
 * @property-read string $config_path                     The absolute system path to the site's wp-config.php file.
 * @property-read bool   $customer_jetpack                TRUE if the customer is using their own Jetpack subscription.
 * @property-read bool   $enable_auto_core_updates_major  TRUE if WordPress core should be automatically updated to new major versions,
 *                                                        FALSE (default) to keep major updates as a manual action.
 * @property-read string $environment                     The current environment. One of "production", "staging",
 *                                                        "regression", or "development".
 * @property-read string $feature_flags_url               The endpoint to retrieve feature flags details.
 * @property-read bool   $is_beta_tester                  TRUE if this account is part of our beta testing program.
 * @property-read bool   $is_development_site             TRUE if this is a development environment.
 * @property-read bool   $is_mapps_site                   TRUE if this is a Managed Applications (MAPPS) site.
 * @property-read bool   $is_mwch_site                    TRUE if this is a Managed WooCommerce hosting site.
 * @property-read bool   $is_nexcess_site                 TRUE if this is running on the Nexcess platform.
 * @property-read bool   $is_production_site              TRUE if this is a production environment.
 * @property-read bool   $is_qa_environment               TRUE if running in Nexcess' QA environment, rather than a customer-facing cloudhost.
 * @property-read bool   $is_quickstart                   TRUE if the site is a WP QuickStart site.
 * @property-read bool   $is_regression_site              TRUE if this is a regression environment.
 * @property-read bool   $is_staging_site                 TRUE if this is a staging environment.
 * @property-read bool   $is_storebuilder                 TRUE if this site is running on a StoreBuilder plan.
 * @property-read bool   $is_temp_domain                  TRUE if the site is currently running on its temporary domain.
 * @property-read bool   $mapps_core_updates_enabled      TRUE if MAPPS is responsible for automatic core updates,
 *                                                        FALSE if the responsibility falls to WordPress core.
 * @property-read bool   $mapps_plugin_updates_enabled    TRUE if MAPPS is responsible for automatic plugin updates,
 *                                                        FALSE if the responsibility falls to WordPress core.
 * @property-read string $mapps_version                   The MAPPS MU plugin version.
 * @property-read string $managed_apps_endpoint           The MAPPS API endpoint.
 * @property-read string $managed_apps_token              The MAPPS API token.
 * @property-read string $package_label                   The platform package label.
 * @property-read string $performance_monitor_endpoint    The endpoint used to retrieve Lighthouse reports for a site.
 * @property-read string $php_version                     The current MAJOR.MINOR PHP version.
 * @property-read int    $plan_id                         The Nexcess plan ID.
 * @property-read string $plan_name                       The (legacy) plan code, based on the $package_label.
 * @property-read string $plan_type                       The plan type ("WordPress", "woocommerce", etc.).
 * @property-read string $quickstart_app_url              The WP QuickStart SaaS URL.
 * @property-read string $quickstart_public_key           The public key used to verify WP QuickStart requests.
 * @property-read string $quickstart_site_id              The WP QuickStart site UUID.
 * @property-read string $quickstart_site_type            The type of QuickStart site, or an empty string if not a WP QuickStart site.
 * @property-read string $redis_host                      The Redis server host.
 * @property-read int    $redis_port                      The Redis server port.
 * @property-read string $redis_socket                    The Redis server socket.
 * @property-read int    $service_id                      The Nexcess service ID.
 * @property-read string $storebuilder_site_id            The store ID for WooCommerce stores utilizing StoreBuilder.
 * @property-read string $telemetry_key                   API key for the plugin reporter (telemetry).
 * @property-read string $telemetry_reporter_endpoint     Endpoint used to report telemetry data.
 * @property-read string $temp_domain                     The site's temporary domain.
 * @property-read string $wc_automated_testing_url        The WooCommerce Automated Testing SaaS URL.
 */
/**
 * @codingStandardsIgnoreEnd
 */
class SettingsPF extends BaseSettings {
	use HasFlags;

	/**
	 * The transient key used to cache SiteWorx data.
	 */
	const SITEWORX_CACHE_KEY = 'nexcess-mapps-environment';

	const PREFIX_ENV_VAR = '';

	/**
	 * Plan names mapped to package labels.
	 *
	 * Every defined plan should have a corresponding class constant, and these constants should
	 * be the only thing used for conditionals throughout the codebase.
	 */

	/**
	 * Plans available prior to January 24, 2020.
	 */
	const PLAN_BASIC        = 'woo.basic';
	const PLAN_BUSINESS     = 'woo.business';
	const PLAN_FREELANCE    = 'wp.freelance';
	const PLAN_PERSONAL     = 'wp.personal';
	const PLAN_PLUS         = 'woo.plus';
	const PLAN_PRO          = 'woo.pro';
	const PLAN_PROFESSIONAL = 'wp.professional';
	const PLAN_STANDARD     = 'woo.standard';

	/**
	 * Plans available after January 24, 2020.
	 */
	const PLAN_MWP_SPARK      = 'mwp.spark';
	const PLAN_MWP_MAKER      = 'mwp.maker';
	const PLAN_MWP_BUILDER    = 'mwp.builder';
	const PLAN_MWP_PRODUCER   = 'mwp.producer';
	const PLAN_MWP_EXECUTIVE  = 'mwp.executive';
	const PLAN_MWP_ENTERPRISE = 'mwp.enterprise';
	const PLAN_MWC_STARTER    = 'mwc.starter';
	const PLAN_MWC_CREATOR    = 'mwc.creator';
	const PLAN_MWC_STANDARD   = 'mwc.standard';
	const PLAN_MWC_GROWTH     = 'mwc.growth';
	const PLAN_MWC_ENTERPRISE = 'mwc.enterprise';

	/**
	 * Load all custom settings.
	 *
	 * This method gets called as part of $this->load(), and can override any of the default settings
	 * (but can still be overridden via $this->overrides).
	 *
	 * @param Array<string,mixed> $settings Current settings. These are provided for reference and may
	 *                                      be returned, but it's not required to do so.
	 *
	 * @return Array<string,mixed> Custom settings.
	 */
	protected function loadSettings( array $settings ) {
		/*
		 * If the user has specified an environment type, we should respect that.
		 *
		 * The environment type may be set in two ways:
		 * 1. Via the WP_ENVIRONMENT_TYPE environment variable.
		 * 2. By defining the WP_ENVIRONMENT_TYPE constant.
		 */
		$environment_type = ! empty( getenv( 'WP_ENVIRONMENT_TYPE' ) ) || defined( 'WP_ENVIRONMENT_TYPE' )
			? wp_get_environment_type()
			: $this->getSiteWorxSetting( 'app_environment', 'production' );

		$quickstart_endpoint = $this->getSiteWorxSetting( 'quickstart_endpoint', '' );
		$quickstart_endpoint = ! empty( $quickstart_endpoint ) ? $quickstart_endpoint : 'https://storebuilder.app';

		// Assemble the most basic values.
		$defaults = [
			'account_id'                     => (int) $this->getSiteWorxSetting( 'account_id' ),
			'autoscaling_enabled'            => (bool) $this->getSiteWorxSetting( 'autoscale_enabled', false ),
			'company_name'                   => _x( 'Nexcess', 'company name', 'nexcess-mapps' ),
			'client_id'                      => (int) $this->getSiteWorxSetting( 'client_id' ),
			'core_updates_enabled'           => (bool) $this->getSiteWorxSetting( 'app_updates_core', false ),
			'enable_auto_core_updates_major' => false,
			'environment'                    => $environment_type,
			'feature_flags_url'              => $this->getSiteWorxSetting( 'feature_flags_url', 'https://feature-flags.nexcess-services.com' ),
			'logo_path'                      => '/assets/img/nexcess-logo.svg?v2021',
			'package_label'                  => $this->getSiteWorxSetting( 'package_label', false ),
			'plan_type'                      => $this->getSiteWorxSetting( 'app_type', 'unknown' ),
			'platform_name'                  => _x( 'Nexcess Managed Applications Platform', 'company name', 'nexcess-mapps' ),
			'managed_apps_endpoint'          => $this->getSiteWorxSetting( 'mapp_endpoint', false ),
			'managed_apps_token'             => $this->getSiteWorxSetting( 'mapp_token', false ),
			'mapps_core_updates_enabled'     => (bool) $this->getSiteWorxSetting( 'app_updates_core', true ),
			'mapps_plugin_updates_enabled'   => (bool) $this->getSiteWorxSetting( 'app_updates_plugin', true ),
			'mapps_version'                  => PLUGIN_VERSION,
			'plan_name'                      => $this->getSiteWorxSetting( 'package_name', false ),
			'performance_monitor_endpoint'   => $this->ensureEndpointHasProtocol( $this->getSiteWorxSetting( 'performance_monitor_endpoint', 'https://queue.ppm.nexcess-services.com' ) ),
			'plugin_updates_enabled'         => (bool) $this->getSiteWorxSetting( 'app_updates_plugin', false ),
			'quickstart_app_url'             => $quickstart_endpoint,
			'quickstart_public_key'          => $this->getSiteWorxSetting( 'quickstart_public_id' ),
			'quickstart_site_id'             => $this->getSiteWorxSetting( 'quickstart_uuid', '' ),
			'redis_socket'                   => $this->getSiteWorxSetting( 'redis_socket', '' ),
			'service_id'                     => (int) $this->getSiteWorxSetting( 'service_id' ),
			'storebuilder_site_id'           => $this->getSiteWorxSetting( 'storebuilder_uuid', '' ),
			'temp_domain'                    => $this->getSiteWorxSetting( 'temp_domain', '' ),
			'wc_automated_testing_url'       => $this->getSiteWorxSetting( 'wc_automated_testing_url', 'https://manager.wcat.nexcess-services.com' ),
			'is_beta_tester'                 => defined( 'NEXCESS_MAPPS_BETA_TESTER' )
				? (bool) constant( 'NEXCESS_MAPPS_BETA_TESTER' )
				: (bool) $this->getSiteWorxSetting( 'beta_client', false ),
		];

		// Determine whether or not this is a StoreBuilder site.
		$is_storebuilder = 'woocommerce' === $defaults['plan_type']
			&& (
				'mwc.starter-storebuilder' === $defaults['package_label']
				|| ! empty( $defaults['storebuilder_site_id'] )
			);

		// Merge in any calculated values.
		$settings = array_merge( $defaults, [
			'is_nexcess_site'      => 'unknown' !== $defaults['plan_type'],
			'is_mapps_site'        => ! in_array( $defaults['plan_type'], [ 'generic', 'unknown' ], true )
										&& ! empty( $defaults['package_label'] ),
			'is_mwch_site'         => 'woocommerce' === $defaults['plan_type'],
			'is_production_site'   => 'production' === $defaults['environment'],
			'is_qa_environment'    => 'https://mapp.quality.nxswd.net' === $defaults['managed_apps_endpoint'],
			'is_regression_site'   => 'regression' === $defaults['environment'],
			'is_staging_site'      => 'staging' === $defaults['environment'],
			'is_development_site'  => 'development' === $defaults['environment'],
			'is_quickstart'        => ! empty( $defaults['quickstart_site_id'] ) || $is_storebuilder,
			'is_storebuilder'      => $is_storebuilder,
			'is_temp_domain'       => wp_parse_url( site_url(), PHP_URL_HOST ) === $defaults['temp_domain'],
			'php_version'          => PHP_MAJOR_VERSION . '.' . PHP_MINOR_VERSION,
			'quickstart_site_type' => [ $this, 'getQuickStartSiteType' ],
		] );

		// Finally, include any extra settings.
		return array_merge( $settings, [
			'canny_board_token' => isset( $this->is_beta_tester ) && $this->is_beta_tester ? '1cdf6de0-9706-7444-68f9-cf2c141bcb3e' : '',
			'config_path'       => [ $this, 'getConfigPath' ],
			'telemetry_key'     => 'ZTuhNKgzgmAAtZNNjRyqVuzQbv9NyWNJMf7',
			'framework_url'     => plugins_url( '/vendor/stellarwp/plugin-framework/', __FILE__ ),
			'plugin_version'    => '1.0.3-dev',
		] );
	}

	/**
	 * Get the path to the site's wp-config.php file.
	 *
	 * Officially, WordPress supports loading the wp-config.php file from ABSPATH *or* one level
	 * above, as long as the latter doesn't also include its own wp-settings.php file.
	 *
	 * @see wp-load.php
	 *
	 * @return ?string The absolute system path to the wp-config.php file, or null if something has
	 *                 gone seriously wrong (e.g. this plugin running despite WordPress not being
	 *                 fully installed).
	 */
	public function getConfigPath() {
		if ( file_exists( ABSPATH . 'wp-config.php' ) ) {
			return ABSPATH . 'wp-config.php';
		}

		$parent_dir = dirname( ABSPATH );
		if ( file_exists( $parent_dir . '/wp-config.php' ) && ! file_exists( $parent_dir . '/wp-settings.php' ) ) {
			return $parent_dir . '/wp-config.php';
		}

		return null;
	}

	/**
	 * Get the WP QuickStart site type, if one exists.
	 *
	 * @return string The site type, if one exists, or an empty string if undetermined.
	 */
	public function getQuickStartSiteType() {
		if ( ! $this->get( 'is_quickstart' ) ) {
			return '';
		}

		if ( $this->get( 'is_storebuilder' ) ) {
			return 'store';
		}

		$option = new GroupedOption( Integrations\QuickStart::OPTION_NAME );

		return (string) $option->type;
	}

	/**
	 * Ensures a URL has a protocol and if it does not adds it.
	 *
	 * @param string $endpoint The endpoint to ensure has a protocol.
	 *
	 * @return string The endpoint with https:// protocol added if needed.
	 */
	public function ensureEndpointHasProtocol( $endpoint ) {
		if ( null === wp_parse_url( $endpoint, PHP_URL_SCHEME ) ) {
			return 'https://' . $endpoint;
		}
		return $endpoint;
	}
}
