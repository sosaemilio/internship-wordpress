<?php

namespace Nexcess\MAPPS;

use Nexcess\MAPPS\Concerns\HasFlags;
use Nexcess\MAPPS\Exceptions\ImmutableValueException;
use Nexcess\MAPPS\Exceptions\SiteWorxException;
use Nexcess\MAPPS\Support\GroupedOption;

/**
 * @property-read int    $account_id                   The Nexcess cloud account (site) ID.
 * @property-read bool   $autoscaling_enabled          TRUE if autoscaling is enabled for this site.
 * @property-read string $canny_board_token            The Canny board token used for the collecting customer feedback.
 * @property-read int    $client_id                    The Nexcess client ID.
 * @property-read string $config_path                  The absolute system path to the site's wp-config.php file.
 * @property-read bool   $customer_jetpack             TRUE if the customer is using their own Jetpack subscription.
 * @property-read string $environment                  The current environment. One of "production", "staging",
 *                                                     "regression", or "development".
 * @property-read string $feature_flags_url            The endpoint to retrieve feature flags details.
 * @property-read bool   $is_beginner_plan             TRUE if this site is on the WooCommerce beginner plan.
 * @property-read bool   $is_beta_tester               TRUE if this account is part of our beta testing program.
 * @property-read bool   $is_development_site          TRUE if this is a development environment.
 * @property-read bool   $is_mapps_site                TRUE if this is a Managed Applications (MAPPS) site.
 * @property-read bool   $is_mwch_site                 TRUE if this is a Managed WooCommerce hosting site.
 * @property-read bool   $is_nexcess_site              TRUE if this is running on the Nexcess platform.
 * @property-read bool   $is_production_site           TRUE if this is a production environment.
 * @property-read bool   $is_qa_environment            TRUE if running in Nexcess' QA environment, rather than a customer-facing cloudhost.
 * @property-read bool   $is_quickstart                TRUE if the site is a WP QuickStart site.
 * @property-read bool   $is_regression_site           TRUE if this is a regression environment.
 * @property-read bool   $is_staging_site              TRUE if this is a staging environment.
 * @property-read bool   $is_storebuilder              TRUE if this site is running on a StoreBuilder plan.
 * @property-read bool   $is_temp_domain               TRUE if the site is currently running on its temporary domain.
 * @property-read bool   $mapps_core_updates_enabled   TRUE if MAPPS is responsible for automatic core updates,
 *                                                     FALSE if the responsibility falls to WordPress core.
 * @property-read bool   $mapps_plugin_updates_enabled TRUE if MAPPS is responsible for automatic plugin updates,
 *                                                     FALSE if the responsibility falls to WordPress core.
 * @property-read string $mapps_version                The MAPPS MU plugin version.
 * @property-read string $managed_apps_endpoint        The MAPPS API endpoint.
 * @property-read string $managed_apps_token           The MAPPS API token.
 * @property-read string $package_label                The platform package label.
 * @property-read string $performance_monitor_endpoint The endpoint used to retrieve Lighthouse reports for a site.
 * @property-read string $php_version                  The current MAJOR.MINOR PHP version.
 * @property-read int    $plan_id                      The Nexcess plan ID.
 * @property-read string $plan_name                    The (legacy) plan code, based on the $package_label.
 * @property-read string $plan_type                    The plan type ("wordpress", "woocommerce", etc.).
 * @property-read string $quickstart_app_url           The WP QuickStart SaaS URL.
 * @property-read string $quickstart_public_key        The public key used to verify WP QuickStart requests.
 * @property-read string $quickstart_site_id           The WP QuickStart site UUID.
 * @property-read string $quickstart_site_type         The type of QuickStart site, or an empty string if not a WP QuickStart site.
 * @property-read string $redis_host                   The Redis server host.
 * @property-read int    $redis_port                   The Redis server port.
 * @property-read int    $service_id                   The Nexcess service ID.
 * @property-read string $storebuilder_site_id         The store ID for WooCommerce stores utilizing StoreBuilder.
 * @property-read string $telemetry_key                API key for the plugin reporter (telemetry).
 * @property-read string $temp_domain                  The site's temporary domain.
 * @property-read string $wc_automated_testing_url     The WooCommerce Automated Testing SaaS URL.
 */
class Settings {
	use HasFlags;

	/**
	 * An array of Account Configuration Details provided by SiteWorx.
	 *
	 * @var mixed[]
	 */
	private $config;

	/**
	 * Parsed settings, which are immutable outside of this object.
	 *
	 * @var mixed[]
	 */
	private $settings;

	/**
	 * The transient key used to cache SiteWorx data.
	 */
	const ENVIRONMENT_TRANSIENT = 'nexcess-mapps-environment';

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
	const PLAN_BEGINNER     = 'woo.beginner';
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
	 * Initialize the settings instance.
	 *
	 * @param string[] $settings Optional. Settings to merge into what's parsed from the
	 *                           environment. Default is empty.
	 */
	public function __construct( $settings = [] ) {
		$this->settings = array_merge( $this->loadEnvironmentVariables(), $settings );
	}

	/**
	 * Retrieve a setting as a property.
	 *
	 * This is merely a wrapper around getSetting(), but enables us to do things like:
	 *
	 *     $settings->is_mwch_site
	 *
	 * @param string $setting The setting name.
	 *
	 * @return mixed
	 */
	public function __get( $setting ) {
		return $this->getSetting( $setting );
	}

	/**
	 * Don't permit properties to be overridden on the class.
	 *
	 * @param string $property The property name.
	 * @param mixed  $value    The value being assigned.
	 *
	 * @throws \Nexcess\MAPPS\Exceptions\ImmutableValueException If the setting cannot be modified.
	 */
	public function __set( $property, $value ) {
		throw new ImmutableValueException(
			sprintf(
				/* Translators: %1$s is the property name. */
				__( 'Setting "%1$s" may not be modified.', 'nexcess-mapps' ),
				esc_html( $property )
			)
		);
	}

	/**
	 * Enable functions like isset() and empty() to work with dynamic properties.
	 *
	 * @param string $property The property name.
	 *
	 * @return bool True if the property exists on $this->settings, false otherwise.
	 */
	public function __isset( $property ) {
		return isset( $this->settings[ $property ] );
	}

	/**
	 * Retrieve a setting.
	 *
	 * If the setting is callable, the callback will be executed and cached, enabling lazy-loading
	 * of more complicated settings. The callback itself will retrieve the current instance of the
	 * Settings object.
	 *
	 * @param string $setting The setting name.
	 * @param mixed  $default Optional. The default value, if the setting is not present.
	 *                        Default is null.
	 *
	 * @return mixed
	 */
	public function getSetting( $setting, $default = null ) {
		if ( ! isset( $this->settings[ $setting ] ) ) {
			return $default;
		}

		// Lazy-load the setting if given a callable.
		if ( is_callable( $this->settings[ $setting ] ) ) {
			$this->settings[ $setting ] = call_user_func_array( $this->settings[ $setting ], [ $this ] );
		}

		return null !== $this->settings[ $setting ] ? $this->settings[ $setting ] : $default;
	}

	/**
	 * Retrieve all registered settings.
	 *
	 * @return mixed[]
	 */
	public function getSettings() {
		return $this->settings;
	}

	/**
	 * Refresh the cached configuration.
	 *
	 * @return self
	 */
	public function refresh() {
		// Clear caches.
		delete_site_transient( self::ENVIRONMENT_TRANSIENT );
		$this->config = [];

		// Re-calculate settings.
		$this->settings = $this->loadEnvironmentVariables();

		return $this;
	}

	/**
	 * Read and parse all environment variables.
	 *
	 * @return mixed[]
	 */
	private function loadEnvironmentVariables() {
		/*
		 * If the user has specified an environment type, we should respect that.
		 *
		 * The environment type may be set in two ways:
		 * 1. Via the WP_ENVIRONMENT_TYPE environment variable.
		 * 2. By defining the WP_ENVIRONMENT_TYPE constant.
		 */
		$environment_type = ! empty( getenv( 'WP_ENVIRONMENT_TYPE' ) ) || defined( 'WP_ENVIRONMENT_TYPE' )
			? wp_get_environment_type()
			: $this->getConfig( 'app_environment', 'production' );

		$quickstart_endpoint = $this->getConfig( 'quickstart_endpoint', '' );
		$quickstart_endpoint = ! empty( $quickstart_endpoint ) ? $quickstart_endpoint : 'https://storebuilder.app';

		// Assemble the most basic values.
		$environment = [
			'account_id'                   => (int) $this->getConfig( 'account_id' ),
			'autoscaling_enabled'          => (bool) $this->getConfig( 'autoscale_enabled', false ),
			'client_id'                    => (int) $this->getConfig( 'client_id' ),
			'customer_jetpack'             => (bool) $this->getConfig( 'customer_owns_jetpack', false ),
			'environment'                  => $environment_type,
			'feature_flags_url'            => $this->getConfig( 'feature_flags_url', 'https://feature-flags.nexcess-services.com' ),
			'package_label'                => $this->getConfig( 'package_label', false ),
			'plan_type'                    => $this->getConfig( 'app_type', 'unknown' ),
			'managed_apps_endpoint'        => $this->getConfig( 'mapp_endpoint', false ),
			'managed_apps_token'           => $this->getConfig( 'mapp_token', false ),
			'mapps_core_updates_enabled'   => (bool) $this->getConfig( 'app_updates_core', true ),
			'mapps_plugin_updates_enabled' => (bool) $this->getConfig( 'app_updates_plugin', true ),
			'mapps_version'                => PLUGIN_VERSION,
			'performance_monitor_endpoint' => $this->ensureEndpointHasProtocol( $this->getConfig( 'performance_monitor_endpoint', 'https://queue.ppm.nexcess-services.com' ) ),
			'plan_id'                      => (int) $this->getConfig( 'service_id' ),
			'plan_name'                    => $this->getConfig( 'package_name', false ),
			'quickstart_app_url'           => $quickstart_endpoint,
			'quickstart_public_key'        => $this->getConfig( 'quickstart_public_id' ),
			'quickstart_site_id'           => $this->getConfig( 'quickstart_uuid', '' ),
			'redis_host'                   => $this->getConfig( 'redis_host', '' ),
			'redis_port'                   => (int) $this->getConfig( 'redis_port', 0 ),
			'service_id'                   => (int) $this->getConfig( 'service_id' ),
			'storebuilder_site_id'         => $this->getConfig( 'storebuilder_uuid', '' ),
			'temp_domain'                  => $this->getConfig( 'temp_domain', '' ),
			'wc_automated_testing_url'     => $this->getConfig( 'wc_automated_testing_url', 'https://manager.wcat.nexcess-services.com' ),
			'is_beta_tester'               => defined( 'NEXCESS_MAPPS_BETA_TESTER' )
				? (bool) constant( 'NEXCESS_MAPPS_BETA_TESTER' )
				: (bool) $this->getConfig( 'beta_client', false ),
		];

		// Determine whether or not this is a StoreBuilder site.
		$is_storebuilder = 'woocommerce' === $environment['plan_type']
			&& (
				'mwc.starter-storebuilder' === $environment['package_label']
				|| ! empty( $environment['storebuilder_site_id'] )
			);

		// Merge in any calculated values.
		$settings = array_merge( $environment, [
			'is_nexcess_site'      => 'unknown' !== $environment['plan_type'],
			'is_mapps_site'        => ! in_array( $environment['plan_type'], [ 'generic', 'unknown' ], true )
										&& ! empty( $environment['package_label'] ),
			'is_mwch_site'         => 'woocommerce' === $environment['plan_type'],
			'is_production_site'   => 'production' === $environment['environment'],
			'is_qa_environment'    => 'https://mapp.qa.nxswd.net' === $environment['managed_apps_endpoint'],
			'is_regression_site'   => 'regression' === $environment['environment'],
			'is_staging_site'      => 'staging' === $environment['environment'],
			'is_development_site'  => 'development' === $environment['environment'],
			'is_beginner_plan'     => self::PLAN_BEGINNER === $environment['package_label'],
			'is_quickstart'        => ! empty( $environment['quickstart_site_id'] ) || $is_storebuilder,
			'is_storebuilder'      => $is_storebuilder,
			'is_temp_domain'       => wp_parse_url( site_url(), PHP_URL_HOST ) === $environment['temp_domain'],
			'php_version'          => PHP_MAJOR_VERSION . '.' . PHP_MINOR_VERSION,
			'quickstart_site_type' => [ $this, 'getQuickStartSiteType' ],
		] );

		// Finally, include any extra settings.
		return array_merge( $settings, [
			'canny_board_token' => [ $this, 'getCannyBoardToken' ],
			'config_path'       => [ $this, 'getConfigPath' ],
			'telemetry_key'     => 'ZTuhNKgzgmAAtZNNjRyqVuzQbv9NyWNJMf7',
		] );
	}

	/**
	 * Based on the current settings, determine which Canny board token to offer.
	 *
	 * @param Settings $settings The settings, parsed and calculated from the environment.
	 *
	 * @return string Either a valid Canny board token or an empty string.
	 *
	 * @codeCoverageIgnore
	 */
	private function getCannyBoardToken( Settings $settings ) {
		if ( $settings->is_beta_tester ) {
			return '1cdf6de0-9706-7444-68f9-cf2c141bcb3e';
		}

		return '';
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
	private function getConfigPath() {
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
	 * Retrieve a value from SiteWorx, falling back to a default value if the given configuration
	 * variable does not exist.
	 *
	 * @param string $name    The configuration name.
	 * @param mixed  $default The default value if the given $name is not set. Default is null.
	 *
	 * @return mixed
	 */
	private function getConfig( $name, $default = null ) {
		if ( empty( $this->config ) ) {
			try {
				$this->config = $this->loadSiteWorxEnvironment();
			} catch ( SiteWorxException $e ) {
				/*
				 * If the `siteworx` command is not available, fallback to environment variables.
				 *
				 * This allows us to test locally and only allow overrides when the site is not
				 * on the Nexcess platform.
				 */
				$value = getenv( $name, true );

				return false === $value ? $default : $value;
			}
		}

		return isset( $this->config[ $name ] ) ? $this->config[ $name ] : $default;
	}

	/**
	 * Get the WP QuickStart site type, if one exists.
	 *
	 * @return string The site type, if one exists, or an empty string if undetermined.
	 */
	private function getQuickStartSiteType() {
		if ( ! $this->is_quickstart ) {
			return '';
		}

		if ( $this->is_storebuilder ) {
			return 'store';
		}

		$option = new GroupedOption( Integrations\QuickStart::OPTION_NAME );

		return (string) $option->type;
	}

	/**
	 * Retrieve and parse environment details from SiteWorx.
	 *
	 * @throws \Nexcess\MAPPS\Exceptions\SiteWorxException If invalid data is returned from SiteWorx.
	 *
	 * @return mixed[] An array of environment details.
	 */
	private function loadSiteWorxEnvironment() {
		$cached = get_site_transient( self::ENVIRONMENT_TRANSIENT );

		// Return from the object cache, if available.
		if ( ! empty( $cached ) ) {
			return $cached;
		}

		try {
			$output    = [];
			$exit_code = 0;

			// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.system_calls_exec
			exec( 'siteworx -u -o json -n -c Overview -a listAccountConfig 2>&1', $output, $exit_code );

			// Received a non-zero exit code.
			if ( 0 !== $exit_code ) {
				throw new SiteWorxException( 'Unexpected exit code ' . $exit_code, $exit_code );
			}

			// Received an empty response from siteworx.
			if ( empty( $output ) ) {
				throw new SiteWorxException( 'Received an empty response' );
			}

			$return = json_decode( implode( '', $output ), true );

			if ( null === $return ) {
				throw new SiteWorxException( 'Unable to decode JSON response' );
			}
		} catch ( \Throwable $e ) {
			throw new SiteWorxException( 'An error occurred querying SiteWorx', 500, $e );
		}

		// Cache the results as a transient.
		set_site_transient( self::ENVIRONMENT_TRANSIENT, $return, HOUR_IN_SECONDS );

		return $return;
	}

	/**
	 * Ensures a URL has a protocol and if it does not adds it.
	 *
	 * @param string $endpoint The endpoint to ensure has a protocol.
	 *
	 * @return string The endpoint with https:// protocol added if needed.
	 */
	private function ensureEndpointHasProtocol( $endpoint ) {
		if ( null === wp_parse_url( $endpoint, PHP_URL_SCHEME ) ) {
			return 'https://' . $endpoint;
		}
		return $endpoint;
	}
}
