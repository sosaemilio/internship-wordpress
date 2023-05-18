<?php

/**
 * Expose a cached getOption() method for interacting with a GroupedOption.
 *
 * IMPORTANT: PHP traits can't define constants, but the getOption() method assumes that the class
 * has an "OPTION_NAME" constant defined, corresponding to the option name.
 */

namespace Nexcess\MAPPS\Concerns;

use Nexcess\MAPPS\Support\GroupedOption;

trait CollectsTelemetryData {

	/**
	 * The GroupedOption instance used to .
	 *
	 * @var GroupedOption
	 */
	private $telemetryDataStore;

	/**
	 * Get the GroupedOption instance.
	 *
	 * Note that this method assumes the class has an "OPTION_NAME" class constant defined.
	 *
	 * @return GroupedOption
	 */
	public function getTelemetryData() {
		if ( null === $this->telemetryDataStore ) {
			$this->telemetryDataStore = new GroupedOption( self::TELEMETRY_DATA_STORE_NAME );
		}

		return $this->telemetryDataStore;
	}
}
