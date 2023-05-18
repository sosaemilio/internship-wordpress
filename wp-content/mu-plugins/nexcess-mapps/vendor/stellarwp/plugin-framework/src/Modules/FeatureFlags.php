<?php

/**
 * Module with the Nexcess Feature Flag Service to allow reporting and non-service.
 *
 * Most of the work here is handled by the underlying FeatureFlag service,
 * {@see StellarWP\PluginFramework\Services\FeatureFlags}.
 */

namespace StellarWP\PluginFramework\Modules;

use StellarWP\PluginFramework\Services\FeatureFlags as FeatureFlagService;

class FeatureFlags extends Module
{
    /**
     * The underlying RouteManager instance.
     *
     * @var \StellarWP\PluginFramework\Services\FeatureFlags
     */
    protected $features;

    /**
     * Create a new instance of the REST API integration.
     *
     * @param \StellarWP\PluginFramework\Services\FeatureFlags $features
     */
    public function __construct(FeatureFlagService $features)
    {
        $this->features = $features;
    }

    /**
     * Perform any necessary setup for the module.
     *
     * This method is automatically called as part of Plugin::load_modules(), and is the
     * entry-point for all modules.
     *
     * @return void
     */
    public function setup()
    {
        add_filter(Telemetry::REPORT_DATA_FILTER, [ $this, 'collectTelemetryData' ]);
    }

    /**
     * Collect telemetry data about the current site.
     *
     * @param array<mixed> $report Report data.
     *
     * @return array<mixed> Modified report data.
     */
    public function collectTelemetryData($report = [])
    {
        $report['features']['flags'] = $this->features->getCohorts(); // @phpstan-ignore-line

        return $report;
    }
}
