<?php

/**
 * Perform regular maintenance.
 */

namespace Nexcess\MAPPS\Integrations;

use Nexcess\MAPPS\Concerns\HasCronEvents;
use Nexcess\MAPPS\Services\DropIn;
use Nexcess\MAPPS\Services\FeatureFlags;
use Nexcess\MAPPS\Services\MigrationCleaner;

class Maintenance extends Integration {
	use HasCronEvents;

	/**
	 * @var \Nexcess\MAPPS\Services\DropIn
	 */
	protected $dropIn;

	/**
	 * @var \Nexcess\MAPPS\Services\FeatureFlags
	 */
	protected $flags;

	/**
	 * @var \Nexcess\MAPPS\Services\MigrationCleaner
	 */
	protected $migrationCleaner;

	/**
	 * @var \Nexcess\MAPPS\Integrations\WooCommerceAutomatedTesting
	 */
	protected $wcat;

	/**
	 * The daily cron action name.
	 */
	const DAILY_MAINTENANCE_CRON_ACTION = 'nexcess_mapps_daily_maintenance';

	/**
	 * The weekly cron action name.
	 */
	const WEEKLY_MAINTENANCE_CRON_ACTION = 'nexcess_mapps_weekly_maintenance';

	/**
	 * @param \Nexcess\MAPPS\Services\DropIn                          $drop_in
	 * @param \Nexcess\MAPPS\Services\MigrationCleaner                $cleaner
	 * @param \Nexcess\MAPPS\Integrations\WooCommerceAutomatedTesting $wcat
	 * @param \Nexcess\MAPPS\Services\FeatureFlags                    $flags
	 */
	public function __construct(
		DropIn $drop_in,
		MigrationCleaner $cleaner,
		WooCommerceAutomatedTesting $wcat,
		FeatureFlags $flags
	) {
		$this->dropIn           = $drop_in;
		$this->migrationCleaner = $cleaner;
		$this->wcat             = $wcat;
		$this->flags            = $flags;
	}

	/**
	 * Perform any necessary setup for the integration.
	 *
	 * This method is automatically called as part of Plugin::loadIntegration(), and is the
	 * entry-point for all integrations.
	 */
	public function setup() {
		$this->registerCronEvent( self::DAILY_MAINTENANCE_CRON_ACTION, 'daily' );
		$this->registerCronEvent( self::WEEKLY_MAINTENANCE_CRON_ACTION, 'weekly' );
		$this->addHooks();
	}

	/**
	 * Retrieve all actions for the integration.
	 *
	 * @return array[]
	 */
	protected function getActions() {
		return [

			/*
			 * Daily operations:
			 *
			 * - Set up any features that have been hidden behind feature flags.
			 * - Run the DropIn::cleanBrokenDropIns() method.
			 */
			[ self::DAILY_MAINTENANCE_CRON_ACTION, [ $this, 'enableFlaggedFeatures' ] ],
			[ self::DAILY_MAINTENANCE_CRON_ACTION, [ $this->dropIn, 'cleanBrokenDropIns' ] ],

			/*
			 * Weekly operations:
			 *
			 * - Run the migration cleaner.
			 */
			[ self::WEEKLY_MAINTENANCE_CRON_ACTION, [ $this->migrationCleaner, 'clean' ] ],
		];
	}

	/**
	 * Activate features that are currently locked behind feature flags.
	 *
	 * Once a day, loop through known feature flags that require some activation step and, if the
	 * site is eligible but not yet connected, activate it.
	 */
	public function enableFlaggedFeatures() {
		// WooCommerce Automated Testing.
		if (
			$this->wcat->eligibleForAutomatedTesting()
			&& ! $this->wcat->registered()
			&& $this->flags->enabled( 'woocommerce-automated-testing' )
		) {
			$this->wcat->registerSite();
		}
	}
}
