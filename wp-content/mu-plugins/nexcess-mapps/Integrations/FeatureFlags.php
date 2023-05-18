<?php

/**
 * Integration with the Nexcess Feature Flag Service to allow reporting and non-service.
 *
 * Most of the work here is handled by the underlying FeatureFlag service,
 * {@see Nexcess\MAPPS\Services\FeatureFlags}.
 */

namespace Nexcess\MAPPS\Integrations;

use Nexcess\MAPPS\Concerns\HasHooks;
use Nexcess\MAPPS\Services\FeatureFlags as FeatureFlagService;

class FeatureFlags extends Integration {
	use HasHooks;

	/**
	 * The underlying RouteManager instance.
	 *
	 * @var \Nexcess\MAPPS\Services\FeatureFlags
	 */
	protected $features;

	/**
	 * Create a new instance of the REST API integration.
	 *
	 * @param \Nexcess\MAPPS\Services\FeatureFlags $features
	 */
	public function __construct( FeatureFlagService $features ) {
		$this->features = $features;
	}

	/**
	 * Retrieve all filters for the integration.
	 *
	 * @return array[]
	 */
	protected function getFilters() {
		return [
			[ 'nexcess_mapps_telemetry_report', [ $this, 'collectTelemetryData' ] ],
		];
	}

	/**
	 * Collect telemetry data about the current site.
	 *
	 * @param array $report
	 *
	 * @return array
	 */
	public function collectTelemetryData( $report = [] ) {
		$report['features']['flags'] = $this->features->getCohorts();

		return $report;
	}
}
