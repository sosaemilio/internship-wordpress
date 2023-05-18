<?php

/**
 * The main Nexcess Managed Apps plugin.
 *
 * This class is responsible for starting up services and loading integrations.
 */

namespace Nexcess\MAPPS;

use StellarWP\PluginFramework as Framework;

class PluginPF extends Framework\Plugin {

	/**
	 * Base command names for WP-CLI. This allows either `wp nxmapps` or `wp nexcess-mapps` for commands.
	 *
	 * @var array<string>
	 */
	protected $command_namespaces = [ 'nexcess-mapps', 'nxmapps' ];

	/**
	 * An array containing all registered WP-CLI commands.
	 *
	 * @var Array<string,class-string<Framework\Console\WPCommand>>
	 */
	protected $commands = [
		'telemetry' => Framework\Console\Commands\TelemetryCommand::class,
		'vc'        => Framework\Console\Commands\VisualComparisonCommand::class,
	];

	/**
	 * An array containing all registered modules.
	 *
	 * @var Array<int,class-string<Framework\Modules\Module>>
	 */
	protected $modules = [
		Framework\Modules\Branding::class,
		Framework\Modules\FeatureFlags::class,
		Framework\Modules\Support::class,
		Framework\Modules\SupportUsers::class,
		Framework\Modules\Telemetry::class,
		Framework\Modules\Updates::class,
		Modules\VisualComparison::class,
	];

	/**
	 * An array containing all registered plugin configurations.
	 *
	 * @var Array<string,class-string<Framework\Extensions\Plugins\PluginConfig>>
	 */
	protected $plugins = [];
}
