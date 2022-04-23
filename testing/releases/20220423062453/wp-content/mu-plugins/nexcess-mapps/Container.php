<?php

/**
 * A PSR-11 implementation of a Dependency Injection (DI) container.
 *
 * Note that the official interface, psr/container, requires PHP >= 7.2.0, so we can't explicitly
 * implement the interface until the minimum version of PHP on our plans has been raised.
 */

namespace Nexcess\MAPPS;

use Nexcess\MAPPS\Exceptions\ContainerException;
use Nexcess\MAPPS\Exceptions\ContainerNotFoundException;
use Nexcess\MAPPS\Integrations\Integration;
use Nexcess\Vendor;

class Container {

	/**
	 * Any extensions that have been applied via $this->extend().
	 *
	 * @var mixed[]
	 */
	protected $extensions = [];

	/**
	 * Resolved instances.
	 *
	 * @var mixed[]
	 */
	protected $resolved = [];

	/**
	 * Finds an entry of the container by its identifier and returns it.
	 *
	 * @param string $id Identifier of the entry to look for.
	 *
	 * @throws ContainerNotFoundException No entry was found for **this** identifier.
	 * @throws ContainerException         Error while retrieving the entry.
	 *
	 * @return mixed Entry.
	 */
	public function get( $id ) {
		if ( ! isset( $this->resolved[ $id ] ) ) {
			$this->resolved[ $id ] = $this->make( $id );
		}

		return $this->resolved[ $id ];
	}

	/**
	 * Returns true if the container can return an entry for the given identifier.
	 * Returns false otherwise.
	 *
	 * `has($id)` returning true does not mean that `get($id)` will not throw an exception.
	 * It does however mean that `get($id)` will not throw a `ContainerNotFoundException`.
	 *
	 * @param string $id Identifier of the entry to look for.
	 *
	 * @return bool
	 */
	public function has( $id ) {
		$config = $this->getConfiguration();

		return array_key_exists( $id, $config )
			|| ( class_exists( $id ) && is_subclass_of( $id, Integration::class, true ) );
	}

	/**
	 * Determine whether or not we have a resolved instance of the given identifier.
	 *
	 * @param string $id Identifier of the entry to look for.
	 *
	 * @return bool True if we have a cached instance, false otherwise.
	 */
	public function hasResolved( $id ) {
		return isset( $this->resolved[ $id ] );
	}

	/**
	 * Make a new instance of the given identifier.
	 *
	 * @param string $id The identifier to construct.
	 *
	 * @throws ContainerNotFoundException No entry was found for **this** identifier.
	 * @throws ContainerException         Error while retrieving the entry.
	 *
	 * @return mixed
	 */
	public function make( $id ) {
		$config = $this->getConfiguration();

		try {
			// We have an explicit definition.
			if ( array_key_exists( $id, $config ) ) {
				if ( null === $config[ $id ] ) {
					return new $id();
				}

				return call_user_func( $config[ $id ], $this, $id );
			}
		} catch ( \Exception $e ) {
			throw new ContainerException(
				sprintf( 'Unable to build %1$s: %2$s', $id, $e->getMessage() ),
				$e->getCode(),
				$e
			);
		}

		// If we haven't returned yet, we couldn't find a definition.
		throw new ContainerNotFoundException(
			sprintf( 'Unable to find a definition for %1$s.', $id )
		);
	}

	/**
	 * Create or replace an existing definition.
	 *
	 * @param string        $id       The identifier.
	 * @param callable|null $callback The callback used to build the service.
	 *
	 * @return self
	 */
	public function extend( $id, $callback ) {
		$this->extensions[ $id ] = $callback;

		return $this->forget( $id );
	}

	/**
	 * Forget any cached instance of the given $id.
	 *
	 * @param string $id The ID to forget.
	 */
	public function forget( $id ) {
		unset( $this->resolved[ $id ] );

		return $this;
	}

	/**
	 * Definitions for all entries registered within in the container.
	 *
	 * For classes that can be constructed directly, pass NULL as the value.
	 *
	 * @return mixed[] An array of keys mapped to callables that define how objects should be
	 *                 constructed (or NULL if objects have no dependencies).
	 *
	 * @codeCoverageIgnore
	 */
	public function getConfiguration() {
		/**
		 * Note that indentation is broken up by group so one long class doesn't cause *every* line
		 * to be changed in diffs.
		 *
		 * Order:
		 *  - General
		 *  - Commands
		 *  - Site Health checks
		 *  - Integrations
		 *  - Plugin customizations
		 *  - Routes
		 *  - Services
		 *  - Support
		 *  - Vendor packages
		 *  - WordPress core
		 *
		 * phpcs:disable WordPress.Arrays.MultipleStatementAlignment.DoubleArrowNotAligned
		 */
		return array_merge( [
			// Prevent recursion by returning the current container instance.
			self::class => function ( $app ) {
				return $app;
			},

			/**
			 * ...................................
			 * | General                         :
			 * ...................................
			 */
			Plugin::class   => function ( $app ) {
				return new Plugin( $app, $app->get( Settings::class ) );
			},
			Settings::class => null,

			/**
			 * ...................................
			 * Commands                          :
			 * ...................................
			 */
			Commands\AffiliateWP::class                 => null,
			Commands\BrainstormForce::class             => null,
			Commands\Cache::class                       => function ( $app ) {
				return new Commands\Cache(
					$app->get( Integrations\ObjectCache::class ),
					$app->get( Integrations\PageCache::class )
				);
			},
			Commands\Config::class                      => function ( $app ) {
				return new Commands\Config(
					$app->get( Services\WPConfig::class )
				);
			},
			Commands\Dokan::class                       => null,
			Commands\Installer::class                   => function ( $app ) {
				return new Commands\Installer(
					$app->get( Services\Installer::class )
				);
			},
			Commands\iThemes::class                     => null,
			Commands\Migration::class                   => function ( $app ) {
				return new Commands\Migration(
					$app->get( Services\MigrationCleaner::class )
				);
			},
			Commands\PerformanceMonitor::class          => function ( $app ) {
				return new Commands\PerformanceMonitor(
					$app->get( Settings::class ),
					$app->get( Integrations\PerformanceMonitor::class ),
					$app->get( Services\FeatureFlags::class )
				);
			},
			Commands\PlatformTesting::class             => null,
			Commands\Qubely::class                      => null,
			Commands\QuickStart::class                  => function ( $app ) {
				return new Commands\QuickStart(
					$app->get( Settings::class ),
					$app->get( Integrations\QuickStart::class ),
					$app->get( Integrations\SimpleAdminMenu::class ),
					$app->get( Services\Logger::class ),
					$app->get( Services\Importers\KadenceImporter::class )
				);
			},
			Commands\Setup::class                       => function ( $app ) {
				return new Commands\Setup(
					$app->get( Settings::class ),
					$app->get( Services\Installer::class ),
					$app->get( Integrations\WooCommerce::class )
				);
			},
			Commands\StellarWP::class                   => function ( $app ) {
				return new Commands\StellarWP(
					$app->get( Settings::class ),
					$app->get( Integrations\StellarWP::class )
				);
			},
			Commands\StoreBuilder::class                => function ( $app ) {
				return new Commands\StoreBuilder(
					$app->get( Settings::class ),
					$app->get( Integrations\StoreBuilder::class )
				);
			},
			Commands\Support::class                     => function ( $app ) {
				return new Commands\Support(
					$app->get( Integrations\PageCache::class )
				);
			},
			Commands\VisualComparison::class            => function ( $app ) {
				return new Commands\VisualComparison(
					$app->get( Integrations\VisualComparison::class )
				);
			},
			Commands\WooCommerce::class                 => function ( $app ) {
				return new Commands\WooCommerce(
					$app->get( Integrations\WooCommerceCartFragments::class )
				);
			},
			Commands\WooCommerceAutomatedTesting::class => function ( $app ) {
				return new Commands\WooCommerceAutomatedTesting(
					$app->get( Integrations\WooCommerceAutomatedTesting::class )
				);
			},
			Commands\WPAllImportPro::class              => null,

			/**
			 * ...................................
			 * Site Health checks                :
			 * ...................................
			 */
			HealthChecks\CronConstant::class             => null,
			HealthChecks\PageCache::class                => null,
			HealthChecks\WooCommerceCartFragments::class => function ( $app ) {
				return new HealthChecks\WooCommerceCartFragments(
					$app->get( Integrations\WooCommerceCartFragments::class )
				);
			},

			/**
			 * ...................................
			 * Integrations                      :
			 * ...................................
			 */
			Integrations\Admin::class                         => function ( $app ) {
				return new Integrations\Admin(
					$app->get( Settings::class ),
					$app->get( Services\AdminBar::class ),
					$app->get( Services\Installer::class ),
					$app->get( Services\Logger::class ),
					$app->get( Services\Options::class )
				);
			},
			Integrations\Cache::class                         => null,
			Integrations\Cron::class                          => function ( $app ) {
				return new Integrations\Cron(
					$app->get( Services\AdminBar::class ),
					$app->get( Services\WPConfig::class )
				);
			},
			Integrations\Dashboard::class                     => [ $this, 'buildIntegration' ],
			Integrations\Debug::class                         => null,
			Integrations\DisplayEnvironment::class            => null,
			Integrations\DomainChanges::class                 => function ( $app ) {
				return new Integrations\DomainChanges(
					$app->get( Settings::class ),
					$app->get( Support\DNS::class )
				);
			},
			Integrations\ErrorHandling::class                 => function ( $app ) {
				return new Integrations\ErrorHandling(
					$app->get( Settings::class ),
					$app->get( Services\DropIn::class )
				);
			},
			Integrations\Feedback::class                      => [ $this, 'buildIntegration' ],
			Integrations\Iconic::class                        => null,
			Integrations\Jetpack::class                       => [ $this, 'buildIntegration' ],
			Integrations\Maintenance::class                   => function ( $app ) {
				return new Integrations\Maintenance(
					$app->get( Services\DropIn::class ),
					$app->get( Services\MigrationCleaner::class ),
					$app->get( Integrations\WooCommerceAutomatedTesting::class ),
					$app->get( Services\FeatureFlags::class )
				);
			},
			Integrations\ObjectCache::class                   => function ( $app ) {
				return new Integrations\ObjectCache(
					$app->get( Settings::class ),
					$app->get( Services\AdminBar::class ),
					$app->get( Services\Managers\PluginConfigManager::class )
				);
			},
			Integrations\PageCache::class                     => function ( $app ) {
				return new Integrations\PageCache(
					$app->get( Settings::class ),
					$app->get( Services\WPConfig::class ),
					$app->get( Services\DropIn::class )
				);
			},
			Integrations\Partners::class                      => null,
			Integrations\PerformanceMonitor::class            => function( $app ) {
				return new Integrations\PerformanceMonitor(
					$app->get( Settings::class ),
					$app->get( Integrations\VisualComparison::class ),
					$app->get( Services\Managers\RouteManager::class ),
					$app->get( Services\Logger::class ),
					$app->get( Services\FeatureFlags::class ),
					$app->get( Services\Options::class )
				);
			},
			Integrations\PHPCompatibility::class              => null,
			Integrations\PluginConfig::class                  => function ( $app ) {
				return new Integrations\PluginConfig(
					$app->get( Services\Managers\PluginConfigManager::class )
				);
			},
			Integrations\PluginInstaller::class               => null,
			Integrations\QuickStart::class                    => function ( $app ) {
				return new Integrations\QuickStart(
					$app->get( Settings::class ),
					$app->get( Services\Managers\DashboardWidgetManager::class )
				);
			},
			Integrations\Recapture::class                     => function ( $app ) {
				return new Integrations\Recapture(
					$app->get( Settings::class ),
					$app->get( Integrations\PluginInstaller::class )
				);
			},
			Integrations\RegressionSites::class               => function ( $app ) {
				return new Integrations\RegressionSites(
					$app->get( Settings::class ),
					$app->get( Services\WPConfig::class )
				);
			},
			Integrations\Requirements::class                  => function ( $app ) {
				return new Integrations\Requirements(
					$app->get( Settings::class ),
					$app->get( Services\AdminBar::class ),
					$app->get( Support\PlatformRequirements::class )
				);
			},
			Integrations\RestApi::class                       => function ( $app ) {
				return new Integrations\RestApi(
					$this->get( Services\Managers\RouteManager::class )
				);
			},
			Integrations\SimpleAdminMenu::class               => function( $app ) {
				return new Integrations\SimpleAdminMenu(
					$app->get( Settings::class ),
					$app->get( Support\AdminMenus\MenuCustomizer::class ),
					$app->get( Services\Logger::class )
				);
			},
			Integrations\SiteHealth::class                    => function ( $app ) {
				return new Integrations\SiteHealth(
					$app->get( Settings::class ),
					$app->get( Services\Managers\SiteHealthManager::class )
				);
			},
			Integrations\StagingSites::class                  => function ( $app ) {
				return new Integrations\StagingSites(
					$app->get( Settings::class ),
					$app->get( Services\WPConfig::class )
				);
			},
			Integrations\StellarWP::class                     => function ( $app ) {
				return new Integrations\StellarWP(
					$app->get( Services\Options::class )
				);
			},
			Integrations\StoreBuilder::class                  => function ( $app ) {
				return new Integrations\StoreBuilder(
					$app->get( Settings::class ),
					$app->get( Services\Importers\AttachmentImporter::class ),
					$app->get( Services\Importers\WooCommerceProductImporter::class ),
					$app->get( Services\AdminBar::class ),
					$app->get( Services\Options::class )
				);
			},
			Integrations\StoreBuilder\LookAndFeel::class      => function ( $app ) {
				return new Integrations\StoreBuilder\LookAndFeel(
					$app->get( Integrations\StoreBuilder\StoreBuilderFTC::class )
				);
			},
			Integrations\StoreBuilder\AdminPointer::class     => function ( $app ) {
				return new Integrations\StoreBuilder\AdminPointer(
					$app->get( Settings::class ),
					$app->get( Integrations\StoreBuilder\StoreBuilderFTC::class )
				);
			},
			Integrations\StoreBuilder\Setup::class            => function ( $app ) {
				return new Integrations\StoreBuilder\Setup(
					$app->get( Integrations\StoreBuilder\StoreBuilderFTC::class ),
					$app->get( Integrations\StoreBuilder\LookAndFeel::class )
				);
			},
			Integrations\StoreBuilder\AutoLaunch::class       => function ( $app ) {
				return new Integrations\StoreBuilder\AutoLaunch(
					$app->get( Settings::class ),
					$app->get( Integrations\StoreBuilder\StoreBuilderFTC::class )
				);
			},
			Integrations\StoreBuilderApp::class               => function ( $app ) {
				return new Integrations\StoreBuilderApp(
					$app->get( Settings::class ),
					$app->get( Services\Managers\RouteManager::class ),
					$app->get( Integrations\StoreBuilder\Setup::class ),
					$app->get( Integrations\StoreBuilder\StoreBuilderUser::class )
				);
			},
			Integrations\Support::class                       => [ $this, 'buildIntegration' ],
			Integrations\SupportUsers::class                  => null,
			Integrations\Telemetry::class                     => [ $this, 'buildIntegration' ],
			Integrations\Themes::class                        => null,
			Integrations\Updates::class                       => [ $this, 'buildIntegration' ],
			Integrations\Varnish::class                       => null,
			Integrations\VisualComparison::class              => function ( $app ) {
				return new Integrations\VisualComparison(
					$app->get( Settings::class ),
					$app->get( Services\Logger::class )
				);
			},
			Integrations\WooCommerce::class                   => [ $this, 'buildIntegration' ],
			Integrations\WooCommerceAutomatedTesting::class => function ( $app ) {
				return new Integrations\WooCommerceAutomatedTesting(
					$app->get( Settings::class ),
					$app->get( Services\Managers\RouteManager::class ),
					$app->get( Services\Options::class )
				);
			},
			Integrations\WooCommerceCartFragments::class      => function ( $app ) {
				return new Integrations\WooCommerceCartFragments(
					$app->get( Settings::class ),
					$app->get( Services\Managers\SiteHealthManager::class ),
					$app->get( Services\Options::class )
				);
			},
			Integrations\WooCommerceUpperLimits::class        => [ $this, 'buildIntegration' ],
			Integrations\StoreBuilder\StoreBuilderFTC::class => null,
			Integrations\StoreBuilder\StoreBuilderUser::class => null,

			/**
			 * ...................................
			 * Plugins                           :
			 * ...................................
			 */
			Plugins\RedisCache::class => function ( $app ) {
				return new Plugins\RedisCache(
					$app->get( Settings::class ),
					$app->get( Services\DropIn::class ),
					$app->get( Services\WPConfig::class ),
					$app->get( Integrations\ObjectCache::class )
				);
			},
			Plugins\WPRedis::class    => function ( $app ) {
				return new Plugins\WPRedis(
					$app->get( Settings::class ),
					$app->get( Services\DropIn::class ),
					$app->get( Services\WPConfig::class ),
					$app->get( Integrations\ObjectCache::class )
				);
			},

			/**
			 * ...................................
			 * Routes                            :
			 * ...................................
			 */
			Routes\MappsStatus::class                      => function ( $app ) {
				return new Routes\MappsStatus(
					$app->get( Settings::class )
				);
			},
			Routes\WooCommerceAutomatedTestingRoute::class => function ( $app ) {
				return new Routes\WooCommerceAutomatedTestingRoute(
					$app->get( Integrations\WooCommerceAutomatedTesting::class )
				);
			},
			Routes\StoreBuilderAppRoute::class             => function ( $app ) {
				return new Routes\StoreBuilderAppRoute(
					$app->get( Integrations\StoreBuilder\Setup::class )
				);
			},
			Routes\StoreBuilderAppFTC::class               => function ( $app ) {
				return new Routes\StoreBuilderAppFTC(
					$app->get( Integrations\StoreBuilder\StoreBuilderFTC::class )
				);
			},
			Routes\StoreBuilderAppFTCPost::class           => function ( $app ) {
				return new Routes\StoreBuilderAppFTCPost(
					$app->get( Integrations\StoreBuilder\StoreBuilderFTC::class )
				);
			},
			Routes\StoreBuilderAppLookAndFeel::class       => function ( $app ) {
				return new Routes\StoreBuilderAppLookAndFeel(
					$app->get( Integrations\StoreBuilder\LookAndFeel::class )
				);
			},
			Routes\StoreBuilderAppLookAndFeelPost::class   => function ( $app ) {
				return new Routes\StoreBuilderAppLookAndFeelPost(
					$app->get( Integrations\StoreBuilder\LookAndFeel::class )
				);
			},
			Routes\StoreBuilderAppFontSync::class   => function ( $app ) {
				return new Routes\StoreBuilderAppFontSync(
					$app->get( Integrations\StoreBuilder\LookAndFeel::class )
				);
			},
			Routes\StoreBuilderAppUsername::class          => function ( $app ) {
				return new Routes\StoreBuilderAppUsername(
					$app->get( Integrations\StoreBuilder\StoreBuilderUser::class )
				);
			},

			/**
			 * ...................................
			 * Services                          :
			 * ...................................
			 */
			Services\AdminBar::class         => null,
			Services\DropIn::class           => function ( $app ) {
				return new Services\DropIn(
					$app->get( Services\Logger::class )
				);
			},
			Services\FeatureFlags::class     => function ( $app ) {
				return new Services\FeatureFlags(
					$app->get( Settings::class )
				);
			},
			Services\Logger::class           => null,
			Services\MigrationCleaner::class => function ( $app ) {
				return new Services\MigrationCleaner(
					$app->get( \WP_Filesystem_Base::class ),
					$app->get( Services\WPConfig::class )
				);
			},
			Services\Installer::class        => function ( $app ) {
				return new Services\Installer(
					$app->get( Settings::class ),
					$app->get( Services\Logger::class )
				);
			},
			Services\Options::class          => function ( $app ) {
				return new Services\Options(
					$app->get( Services\AdminBar::class )
				);
			},
			Services\WPConfig::class         => function ( $app ) {
				return new Services\WPConfig(
					$app->get( Vendor\WPConfigTransformer::class )
				);
			},

			// Services - Importers.
			Services\Importers\AttachmentImporter::class         => null,
			Services\Importers\KadenceImporter::class            => null,
			Services\Importers\WooCommerceProductImporter::class => null,

			// Services - Managers.
			Services\Managers\DashboardWidgetManager::class => null,
			Services\Managers\PluginConfigManager::class    => function ( $app ) {
				return new Services\Managers\PluginConfigManager(
					$app
				);
			},
			Services\Managers\RouteManager::class           => function ( $app ) {
				return new Services\Managers\RouteManager(
					$app
				);
			},
			Services\Managers\SiteHealthManager::class      => function ( $app ) {
				return new Services\Managers\SiteHealthManager(
					$app
				);
			},

			/**
			 * ...................................
			 * Support                           :
			 * ...................................
			 */
			Support\DNS::class                       => null,
			Support\AdminMenus\MenuCustomizer::class => null,
			Support\PlatformRequirements::class      => null,

			/**
			 * ...................................
			 * Vendor packages                   :
			 * ...................................
			 */
			Vendor\WPConfigTransformer::class => function ( $app ) {
				return new Vendor\WPConfigTransformer(
					$app->get( Settings::class )->config_path
				);
			},

			/**
			 * ...................................
			 * WordPress core                    :
			 * ...................................
			 */
			\WP_Filesystem_Base::class => function () {
				return Support\Filesystem::init();
			},

		], $this->extensions );
		// phpcs:enable WordPress.Arrays.MultipleStatementAlignment.DoubleArrowNotAligned
	}

	/**
	 * Build a generic integration that receives a Settings object.
	 *
	 * @param self   $app   The current container instance.
	 * @param string $class The integration class name.
	 *
	 * @return object The integration instance.
	 *
	 * @codeCoverageIgnore
	 */
	private function buildIntegration( $app, $class ) {
		return new $class( $app->get( Settings::class ) );
	}
}
