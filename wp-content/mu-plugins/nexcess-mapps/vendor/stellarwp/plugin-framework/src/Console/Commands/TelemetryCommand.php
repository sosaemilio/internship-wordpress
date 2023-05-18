<?php

namespace StellarWP\PluginFramework\Console\Commands;

use StellarWP\PluginFramework\Console\WPCommand;
use StellarWP\PluginFramework\Modules\Module;
use StellarWP\PluginFramework\Modules\Telemetry;

/**
 * WP-CLI methods for Telemetry module.
 */
class TelemetryCommand extends WPCommand
{
    /**
     * @var Telemetry
     */
    protected $module;

    /**
     * Create a new instance of the command.
     *
     * @param Telemetry $module The Telemetry module.
     */
    public function __construct(Module $module)
    {
        $this->module = $module;
    }

    /**
     * Output the collected telemetry data.
     *
     * ## OPTIONS
     *
     * [--ignore-plugins]
     * : Remove the plugins from the output to allow reading the other collected metrics easier.
     *
     * @synopsis [--ignore-plugins]
     *
     * @param Array<int,scalar>     $args Positional arguments.
     * @param Array<string,?scalar> $opts Options passed to the command.
     *
     * @return void
     */
    public function data($args, $opts)
    {
        $ignore_plugins = ! empty($opts['ignore-plugins']);
        $telemetry_data = $this->module->collectTelemetryData();

        if ($ignore_plugins) {
            unset($telemetry_data['plugins']);
        }
        print_r($telemetry_data); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_print_r
    }
}
