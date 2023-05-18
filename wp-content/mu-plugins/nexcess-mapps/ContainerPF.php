<?php
/**
 * A PSR-11 implementation of a Dependency Injection (DI) container.
 *
 * Note that the official interface, psr/container, requires PHP >= 7.2.0, so we can't explicitly
 * implement the interface until the minimum version of PHP on our plans has been raised.
 */

namespace Nexcess\MAPPS;

use StellarWP\PluginFramework as Framework;

class ContainerPF extends Framework\Container {

	/**
	 * Definitions for all entries registered within in the container.
	 *
	 * For classes that can be constructed directly, pass NULL as the value.
	 *
	 * @return Array<string,callable|object|string|null> Array of keys mapped to callables
	 *
	 * @codeCoverageIgnore
	 */
	public function config() {
		/**
		 * Note that indentation is broken up by group so one long class doesn't cause *every* line
		 * to be changed in diffs.
		 *
		 * Order:
		 *  - General
		 *  - Commands
		 *  - Site Health checks
		 *  - Plugin customizations
		 *  - Routes
		 *  - Services
		 *  - Support
		 *  - Vendor packages
		 *  - WordPress core
		 *
		 * phpcs:disable WordPress.Arrays.MultipleStatementAlignment.DoubleArrowNotAligned
		 */
		return array_merge( parent::config(), [
			// Prevent recursion by returning the current container instance.
			self::class                                         => function ( $app ) {
				return $app;
			},

			/**
			 * ...................................
			 * | General                         :
			 * ...................................
			 */
			Framework\Plugin::class                             => PluginPF::class,
			PluginPF::class                                     => function ( $app ) {
				return new PluginPF(
					$app,
					$app->make( Framework\Services\Logger::class )
				);
			},
			SettingsPF::class                                   => null,
			// Enable core Plugin Framework Modules.
			Framework\Services\Managers\CronEventManager::class => null,
			Framework\Contracts\ProvidesSettings::class         => SettingsPF::class,
			Framework\Modules\Branding::class                   => function ( $app ) {
				return new Framework\Modules\Branding(
					$app->make( Framework\Contracts\ProvidesSettings::class )
				);
			},
			Framework\Modules\Updates::class                    => function ( $app ) {
				return new Framework\Modules\Updates(
					$app->make( Framework\Contracts\ProvidesSettings::class ),
					$app->make( Framework\Services\FeatureFlags::class )
				);
			},

			/**
			 * ...................................
			 * Custom Modules                    :
			 * ...................................
			 */
			Modules\Telemetry::class                                    => function ( $app ) {
				return new Modules\Telemetry(
					$app->make( Framework\Contracts\ProvidesSettings::class ),
					$app->make( Framework\Services\Managers\CronEventManager::class ),
					$app->make( Framework\Services\Nexcess\Telemetry::class )
				);
			},
			Modules\VisualComparison::class                                    => function ( $app ) {
				return new Modules\VisualComparison(
					$app->make( Framework\Contracts\ProvidesSettings::class ),
					$app->make( Framework\Services\Logger::class )
				);
			},

			/**
			 * ...................................
			 * Commands                          :
			 * ...................................
			 */
			Framework\Console\Commands\TelemetryCommand::class => function ( $app ) {
				return new Framework\Console\Commands\TelemetryCommand(
					$app->get( Modules\Telemetry::class )
				);
			},
			Framework\Console\Commands\VisualComparisonCommand::class => function ( $app ) {
				return new Framework\Console\Commands\VisualComparisonCommand(
					$app->get( Modules\VisualComparison::class )
				);
			},
		] );
		// phpcs:enable WordPress.Arrays.MultipleStatementAlignment.DoubleArrowNotAligned
	}
}
