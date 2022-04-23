<?php

namespace Nexcess\MAPPS\Commands;

use Nexcess\MAPPS\Integrations\PerformanceMonitor as Integration;
use Nexcess\MAPPS\Services\FeatureFlags;
use Nexcess\MAPPS\Settings;

/**
 * WP-CLI sub-commands for the Nexcess Performance Monitor.
 */
class PerformanceMonitor extends Command {

	/**
	 * @var FeatureFlags
	 */
	protected $featureFlags;

	/**
	 * @var \Nexcess\MAPPS\Integrations\PerformanceMonitor
	 */
	protected $integration;

	/**
	 * @var Settings
	 */
	protected $settings;

	/**
	 * @param Settings                                       $settings
	 * @param \Nexcess\MAPPS\Integrations\PerformanceMonitor $integration
	 * @param FeatureFlags                                   $flags
	 */
	public function __construct( Settings $settings, Integration $integration, FeatureFlags $flags ) {
		$this->settings     = $settings;
		$this->integration  = $integration;
		$this->featureFlags = $flags;
	}

	/**
	 * Enable the Performance Monitor for this site.
	 */
	public function enable() {
		if ( ! $this->settings->performance_monitor_endpoint ) {
			return $this->error( 'The performance_monitor_endpoint is not configured, unable to proceed.', 1 );
		}

		$this->integration->enablePerformanceMonitor();

		$this->success( 'The Performance Monitor has been enabled for this site!' );
	}

	/**
	 * Disable the Performance Monitor for this site.
	 */
	public function disable() {
		$this->integration->disablePerformanceMonitor();

		$this->success( 'The Performance Monitor has been disabled for this site!' );
	}

	/**
	 * Show status of Performance Monitor.
	 */
	public function status() {
		if ( $this->integration->getPerformanceMonitorSetting() ) {
			$this->log( 'Performance Monitor Status: Enabled.' );
		} else {
			$this->log( 'Performance Monitor Status: Disabled.' );
		}
	}
}
