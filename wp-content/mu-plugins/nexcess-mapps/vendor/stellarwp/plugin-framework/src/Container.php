<?php

namespace StellarWP\PluginFramework;

use Psr\Log\LoggerInterface;
use StellarWP\Container\Container as BaseContainer;
use WPConfigTransformer;

/**
 * The Dependency Injection (DI) container definition for the plugin.
 *
 * @link https://github.com/stellarwp/container
 */
class Container extends BaseContainer
{
    /**
     * Retrieve a mapping of abstract identifiers to callables.
     *
     * When an abstract is requested through the container, the container will find the given
     * dependency in this array, execute the callable, and return the result.
     *
     * @return Array<string,callable|object|string|null> A mapping of abstracts to callables.
     *
     * @codeCoverageIgnore
     */
    public function config()
    {
        return [
            // Prevent recursion by letting the container resolve itself if needed.
            static::class => $this,
            self::class   => $this,

            Plugin::class   => function ($app) {
                return new Plugin(
                    $app,
                    $app->make(LoggerInterface::class)
                );
            },
            Settings::class => null,

            // Default implementations of contracts.
            Contracts\ManagesDomain::class        => Services\Domain::class,
            Contracts\ProvidesSettings::class     => Settings::class,
            Contracts\ProvidesSupportUsers::class => Modules\SupportUsers::class,

            // Commands.
            Console\Commands\ExtensionCommand::class   => function ($app) {
                return new Console\Commands\ExtensionCommand(
                    $app->make(Services\Nexcess\MappsApiClient::class)
                );
            },
            Console\Commands\iThemesCommand::class     => null,
            Console\Commands\ObjectCacheProCommand::class => function ($app) {
                return new Console\Commands\ObjectCacheProCommand(
                    $app->make(Extensions\Plugins\ObjectCachePro::class)
                );
            },
            Console\Commands\Setup::class              => null,
            Console\Commands\SupportUserCommand::class => function ($app) {
                return new Console\Commands\SupportUserCommand(
                    $app->make(Contracts\ProvidesSupportUsers::class)
                );
            },
            Console\Commands\TelemetryCommand::class   => function ($app) {
                return new Console\Commands\TelemetryCommand(
                    $app->make(Modules\Telemetry::class)
                );
            },
            Console\Commands\VisualComparisonCommand::class   => function ($app) {
                return new Console\Commands\VisualComparisonCommand(
                    $app->make(Modules\VisualComparison::class)
                );
            },

            // Plugins.
            Extensions\Plugins\AffiliateWP::class          => null,
            Extensions\Plugins\CacheEnabler::class         => function ($app) {
                return new Extensions\Plugins\CacheEnabler(
                    $app->make(Services\DropIn::class),
                    $app->make(Services\WPConfig::class),
                    $app->make(Services\Apache::class),
                    $app->make(Contracts\ProvidesSettings::class),
                    $app->make(Services\Managers\ExtensionConfigManager::class)
                );
            },
            Extensions\Plugins\EasyDigitalDownloads::class => null,
            Extensions\Plugins\LearnDash::class            => null,
            Extensions\Plugins\MemberPress::class          => null,
            Extensions\Plugins\ObjectCachePro::class       => function ($app) {
                return new Extensions\Plugins\ObjectCachePro(
                    $app->make(Contracts\ProvidesSettings::class),
                    $app->make(Services\DropIn::class),
                    $app->make(Services\WPConfig::class),
                    $app->make(Services\Managers\CronEventManager::class)
                );
            },
            Extensions\Plugins\RedisCache::class           => function ($app) {
                return new Extensions\Plugins\RedisCache(
                    $app->make(Contracts\ProvidesSettings::class),
                    $app->make(Services\DropIn::class),
                    $app->make(Services\WPConfig::class)
                );
            },
            Extensions\Plugins\TheEventsCalendar::class    => null,
            Extensions\Plugins\WooCommerce::class          => null,

            // Modules.
            Modules\AutoLogin::class => function ($app) {
                return new Modules\AutoLogin(
                    $app->make(Services\Nexcess\MappsApiClient::class)
                );
            },
            Modules\Branding::class         => function ($app) {
                return new Modules\Branding(
                    $app->make(Contracts\ProvidesSettings::class)
                );
            },
            Modules\ExtensionConfig::class => function ($app) {
                return new Modules\ExtensionConfig(
                    $app->make(Services\Managers\ExtensionConfigManager::class),
                    $app->make(LoggerInterface::class)
                );
            },
            Modules\FeatureFlags::class => function ($app) {
                return new Modules\FeatureFlags(
                    $app->make(Services\FeatureFlags::class)
                );
            },
            Modules\GoLiveWidget::class    => function ($app) {
                return new Modules\GoLiveWidget(
                    $app->make(Contracts\ProvidesSettings::class),
                    $app->make(Services\Managers\MetaBoxManager::class),
                    $app->make(Services\Domain::class)
                );
            },
            Modules\Maintenance::class     => function ($app) {
                return new Modules\Maintenance(
                    $app->make(Services\Managers\CronEventManager::class),
                    $app->make(Services\DropIn::class)
                );
            },
            Modules\ObjectCache::class     => null,
            Modules\PurgeCaches::class     => function ($app) {
                return new Modules\PurgeCaches(
                    $app->make(Services\Cache::class)
                );
            },
            Modules\Support::class         => function ($app) {
                return new Modules\Support(
                    $app->make(Contracts\ProvidesSettings::class)
                );
            },
            Modules\SupportUsers::class    => function ($app) {
                return new Modules\SupportUsers(
                    $app->make(Services\Managers\CronEventManager::class),
                    $app->make(Contracts\ProvidesSettings::class)
                );
            },
            Modules\Telemetry::class       => function ($app) {
                return new Modules\Telemetry(
                    $app->make(Contracts\ProvidesSettings::class),
                    $app->make(Services\Managers\CronEventManager::class),
                    $app->make(Services\Nexcess\Telemetry::class)
                );
            },
            Modules\Updates::class         => function ($app) {
                return new Modules\Updates(
                    $app->make(Contracts\ProvidesSettings::class),
                    $app->make(Services\FeatureFlags::class)
                );
            },
            Modules\VisualComparison::class         => function ($app) {
                return new Modules\VisualComparison(
                    $app->make(Contracts\ProvidesSettings::class),
                    $app->make(Services\Logger::class)
                );
            },

            // Services.
            Services\Apache::class                          => function ($app) {
                return new Services\Apache(ABSPATH . '/.htaccess');
            },
            Services\Cache::class                           => function ($app) {
                return new Services\Cache(
                    $app->make(Services\Nexcess\MappsApiClient::class),
                    $app->make(LoggerInterface::class)
                );
            },
            Services\Domain::class                          => function ($app) {
                return new Services\Domain(
                    $app->make(Contracts\ProvidesSettings::class),
                    $app->make(Services\Nexcess\MappsApiClient::class)
                );
            },
            Services\DropIn::class                          => function ($app) {
                return new Services\DropIn(
                    $app->make(\Psr\Log\LoggerInterface::class)
                );
            },
            Services\FeatureFlags::class                          => function ($app) {
                return new Services\FeatureFlags(
                    $app->make(Contracts\ProvidesSettings::class)
                );
            },
            Services\Logger::class                          => null,
            Services\Managers\CronEventManager::class       => null,
            Services\Managers\MetaBoxManager::class         => null,
            Services\Managers\ExtensionConfigManager::class => function ($app) {
                return new Services\Managers\ExtensionConfigManager($app);
            },
            Services\Nexcess\MappsApiClient::class          => function ($app) {
                $settings = $app->make(Contracts\ProvidesSettings::class);

                return new Services\Nexcess\MappsApiClient(
                    $settings->mapps_api_url,
                    $settings->mapps_api_token,
                    $app->make(LoggerInterface::class)
                );
            },
            Services\Nexcess\Telemetry::class               => function ($app) {
                $settings = $app->make(Contracts\ProvidesSettings::class);

                return new Services\Nexcess\Telemetry(
                    $settings->telemetry_reporter_endpoint,
                    $settings->telemetry_key,
                    $app->make(LoggerInterface::class)
                );
            },
            Services\SetupInstructions::class               => function ($app) {
                return new Services\SetupInstructions(
                    $app->make(Contracts\ProvidesSettings::class),
                    $app->make(LoggerInterface::class)
                );
            },
            Services\WPConfig::class                        => function ($app) {
                return new Services\WPConfig(
                    $app->make(WPConfigTransformer::class)
                );
            },

            // Implementations of external interfaces.
            LoggerInterface::class => Services\Logger::class,

            // Third-party code.
            WPConfigTransformer::class => function () {
                return new WPConfigTransformer(
                    ABSPATH . '/wp-config.php'
                );
            },
        ];
    }
}
