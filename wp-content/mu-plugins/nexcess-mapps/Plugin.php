<?php

/**
 * The main Nexcess Managed Apps plugin.
 *
 * This class is responsible for starting up services and loading integrations.
 */

namespace Nexcess\MAPPS;

use Nexcess\MAPPS\Exceptions\IsNotNexcessSiteException;
use Nexcess\MAPPS\Integrations\Integration;
use WP_CLI;

class Plugin {

	/**
	 * Base command names for WP-CLI. This allows either `wp nxmapps` or `wp nexcess-mapps` for commands.
	 *
	 * In the future, this may be filtered to be able to be brandable or customizeable.
	 *
	 * @var array
	 */
	private $command_namespace = [ 'nexcess-mapps', 'nxmapps' ];

	/**
	 * All available WP-CLI commands.
	 *
	 * @var mixed[]
	 */
	private $commands = [
		'affiliatewp'          => Commands\AffiliateWP::class,
		'brainstormforce'      => Commands\BrainstormForce::class,
		'cache'                => Commands\Cache::class,
		'config'               => Commands\Config::class,
		'dokan'                => Commands\Dokan::class,
		'details'              => [ Commands\Support::class, 'details' ], // Deprecated by 'support details' command.
		'installer'            => Commands\Installer::class,
		'ithemes'              => Commands\iThemes::class,
		'migration'            => Commands\Migration::class,
		'performance-monitor'  => Commands\PerformanceMonitor::class,
		'qubely'               => Commands\Qubely::class,
		'quickstart'           => Commands\QuickStart::class,
		'setup'                => [ Commands\Setup::class, 'setup' ],
		'setup:pre-install'    => [ Commands\Setup::class, 'preInstallPlugins' ],
		'setup:woocommerce'    => [ Commands\Setup::class, 'woocommerce' ],
		'stellarwp'            => Commands\StellarWP::class,
		'storebuilder'         => Commands\StoreBuilder::class,
		'support'              => Commands\Support::class,
		'support-user'         => [ Commands\Support::class, 'supportUser' ], // Deprecated by 'support user' command.
		'vc'                   => Commands\VisualComparison::class,
		'wc'                   => Commands\WooCommerce::class,
		'wc automated-testing' => Commands\WooCommerceAutomatedTesting::class,
		'wp-all-import-pro'    => Commands\WPAllImportPro::class,
	];

	/**
	 * The Container instance.
	 *
	 * @var \Nexcess\MAPPS\Container
	 */
	private $container;

	/**
	 * All available integrations.
	 *
	 * @var array[]
	 */
	private $integrations = [

		/*
		 * Integrations here will always be loaded, regardless of whether or not this is running
		 * on a MAPPS site.
		 */
		'global' => [
			Integrations\PHPCompatibility::class,
		],

		/*
		 * These integrations will never be instantiated unless we're running on MAPPS.
		 */
		'mapps'  => [
			Integrations\Admin::class,
			Integrations\Cache::class,
			Integrations\Cron::class,
			Integrations\Dashboard::class,
			Integrations\Debug::class,
			Integrations\DisplayEnvironment::class,
			Integrations\DomainChanges::class,
			Integrations\ErrorHandling::class,
			Integrations\Feedback::class,
			Integrations\Iconic::class,
			Integrations\Jetpack::class,
			Integrations\Maintenance::class,
			Integrations\ObjectCache::class,
			Integrations\PageCache::class,
			Integrations\Partners::class,
			Integrations\PerformanceMonitor::class,
			Integrations\PluginConfig::class,
			Integrations\PluginInstaller::class,
			Integrations\QuickStart::class,
			Integrations\Recapture::class,
			Integrations\RegressionSites::class,
			Integrations\Requirements::class,
			Integrations\RestApi::class,
			Integrations\SiteHealth::class,
			Integrations\StagingSites::class,
			Integrations\StellarWP::class,
			Integrations\StoreBuilder\AdminPointer::class,
			Integrations\StoreBuilder\AutoLaunch::class,
			Integrations\StoreBuilderApp::class,
			Integrations\StoreBuilder::class,
			Integrations\Support::class,
			Integrations\SupportUsers::class,
			Integrations\Telemetry::class,
			Integrations\Themes::class,
			Integrations\Updates::class,
			Integrations\Varnish::class,
			Integrations\VisualComparison::class,
			Integrations\WooCommerce::class,
			Integrations\WooCommerceAutomatedTesting::class,
			Integrations\WooCommerceCartFragments::class,
			Integrations\WooCommerceUpperLimits::class,

			// Loaded after the main integrations are loaded, so that the shouldLoad
			// method can accurately determine whether or not to load the integration.
			Integrations\SimpleAdminMenu::class,
		],
	];

	/**
	 * The Settings instance.
	 *
	 * @var \Nexcess\MAPPS\Settings
	 */
	private $settings;

	/**
	 * Instantiate the class.
	 *
	 * @param \Nexcess\MAPPS\Container $container
	 * @param \Nexcess\MAPPS\Settings  $settings
	 */
	public function __construct( Container $container, Settings $settings ) {
		$this->container = $container;
		$this->settings  = $settings;
	}

	/**
	 * Bootstrap the plugin.
	 *
	 * This method is responsible for orchestrating the setup of the plugin:
	 *
	 * 1. Define any necessary constants.
	 * 2. Load registered integrations.
	 * 3. Load registered WP-CLI commands (if WP-CLI is available).
	 *
	 * @throws \Nexcess\MAPPS\Exceptions\IsNotNexcessSiteException If bootstrapping on a non-MAPPS site.
	 */
	public function bootstrap() {
		// Abort if this is not an Nexcess Managed Apps site.
		if ( ! $this->settings->is_nexcess_site && ( ! defined( 'WP_CLI' ) || ! constant( 'WP_CLI' ) ) ) {
			throw new IsNotNexcessSiteException( 'Does not appear to be an Nexcess Managed Apps site.' );
		}

		$this->watchPluginCommands();
		$this->defineConstants();
		$this->loadIntegrations();
		$this->loadCommands();

		// Allow others to easily do something after we've been loaded.
		add_action( 'plugins_loaded', function () {
			// This action is deliberately not namespaced, as we want to keep it available to other plugins.
			do_action( 'nexcess_mapps_loaded', __NAMESPACE__ . '\PLUGIN_VERSION' );

			// Backwards compatibility.
			do_action( 'nexcess_mapps_before_loading', __NAMESPACE__ . '\PLUGIN_VERSION' );
		} );
	}

	/**
	 * Watch for changes to plugins via WP-CLI.
	 *
	 * This method reads the current WP_CLI runner instance and, if it has to do with installing,
	 * activating, deactivating, or uninstalling plugins, fire corresponding hooks so integrations
	 * may respond to the action.
	 */
	protected function watchPluginCommands() {
		if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
			return;
		}

		$runner = WP_CLI::get_runner();
		$args   = $runner->arguments;
		$opts   = $runner->assoc_args;
		$cmd    = array_shift( $args );

		// Only proceed if the user is running `wp plugin ...`.
		if ( 'plugin' !== $cmd ) {
			return;
		}

		$subcmd  = array_shift( $args );
		$actions = [
			'activating'   => [],
			'deactivating' => [],
			'installing'   => [],
			'uninstalling' => [],
		];

		// Sub-commands we want to watch.
		switch ( $subcmd ) {
			case 'activate':
				$actions['activating'] = $args;
				break;

			case 'deactivate':
				$actions['deactivating'] = $args;
				break;

			case 'install':
				$actions['installing'] = $args;

				if ( ! empty( $opts['activate'] ) ) {
					$actions['activating'] = $args;
				}
				break;

			case 'uninstall':
				$actions['uninstalling'] = $args;
				$actions['deactivating'] = $args;
				break;
		}

		// Fire action hooks for each of the identified actions and plugins.
		foreach ( $actions as $action => $plugins ) {
			foreach ( $plugins as $plugin ) {
				do_action( "Nexcess\\MAPPS\\WP-CLI\\{$action}_plugin_{$plugin}" );
			}
		}
	}

	/**
	 * Define constants for legacy integrations.
	 *
	 * Eventually, these constants should be unnecessary and removed.
	 */
	protected function defineConstants() {
		defined( 'ICONIC_DISABLE_DASH' ) || define( 'ICONIC_DISABLE_DASH', true );

		defined( 'NEXCESS_MAPPS_SITE' ) || define( 'NEXCESS_MAPPS_SITE', $this->settings->is_mapps_site );
		defined( 'NEXCESS_MAPPS_PLAN_NAME' ) || define( 'NEXCESS_MAPPS_PLAN_NAME', $this->settings->plan_name );
		defined( 'NEXCESS_MAPPS_PACKAGE_LABEL' ) || define( 'NEXCESS_MAPPS_PACKAGE_LABEL', $this->settings->package_label );
		defined( 'NEXCESS_MAPPS_ENDPOINT' ) || define( 'NEXCESS_MAPPS_ENDPOINT', $this->settings->managed_apps_endpoint );
		defined( 'NEXCESS_MAPPS_TOKEN' ) || define( 'NEXCESS_MAPPS_TOKEN', $this->settings->managed_apps_token );

		if ( $this->settings->is_mwch_site && ! defined( 'NEXCESS_MAPPS_MWCH_SITE' ) ) {
			define( 'NEXCESS_MAPPS_MWCH_SITE', true );
		}

		if ( $this->settings->is_staging_site && ! defined( 'NEXCESS_MAPPS_STAGING_SITE' ) ) {
			define( 'NEXCESS_MAPPS_STAGING_SITE', true );
		}
	}

	/**
	 * Load registered commands.
	 */
	protected function loadCommands() {
		if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
			return;
		}

		// Allow aliasing of our commands to all our command namespaces.
		foreach ( $this->command_namespace as $command_name ) {
			if ( ! is_string( $command_name ) ) {
				continue;
			}

			foreach ( $this->commands as $name => $callable ) {
				// Resolve class names through the DI container.
				if ( is_array( $callable ) && is_string( $callable[0] ) && isset( $callable[1] ) ) {
					$callable = [
						$this->container->get( $callable[0] ),
						$callable[1],
					];
				} else {
					$callable = $this->container->get( $callable );
				}

				// Add a space between our command namespace and the command name.
				WP_CLI::add_command( $command_name . ' ' . $name, $callable );
			}

			// Commands only loaded in the Nexcess QA environment.
			if ( $this->settings->is_qa_environment ) {
				WP_CLI::add_command( $command_name . ' platform-testing', Commands\PlatformTesting::class );
			}
		}
	}

	/**
	 * Load each integration.
	 *
	 * This method will loop through $this->integrations and call each integration's
	 * `shouldLoadIntegration()` method; if the result is false, the integration will be unset so
	 * that the garbage collector can clean it up.
	 */
	protected function loadIntegrations() {
		// Always load global integrations.
		array_map( [ $this, 'loadIntegration' ], $this->integrations['global'] );

		// Only load MAPPS integrations if we're on MAPPS.
		if ( $this->settings->is_mapps_site ) {
			array_map( [ $this, 'loadIntegration' ], $this->integrations['mapps'] );
		}
	}

	/**
	 * Load an individual integration.
	 *
	 * This method will resolve an instance of the integration within the DI container, then test
	 * the shouldLoadIntegration() method. If the method returns true, the integration's setup()
	 * method will be called; otherwise, the instance will be destroyed.
	 *
	 * @param string $integration The integration class name.
	 */
	protected function loadIntegration( $integration ) {
		// Create an instance of the integration, then see if it should be loaded.
		$instance = $this->container->get( $integration );

		// If we don't need to load it, discard and return.
		if ( ! $instance->shouldLoadIntegration() ) {
			$this->container->forget( $integration );
			unset( $instance );
			return;
		}

		$instance->setup();

		// The action for this will have the format:
		// "Nexcess\MAPPS\Plugin\Loaded\Nexcess\MAPPS\Integrations\CDN".
		// If you're doing something on this action, that's probably a bad idea.
		// The main reason for this is to easily do a "did_action( 'x' ) to check if the integration loaded.
		do_action( "Nexcess\\MAPPS\\Plugin\\Loaded\\{$integration}" );
	}
}
