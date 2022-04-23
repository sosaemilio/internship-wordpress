<?php

/**
 * Small customizations for partner plugins.
 */

namespace Nexcess\MAPPS\Integrations;

use Nexcess\MAPPS\Concerns\HasHooks;

class Partners extends Integration {
	use HasHooks;

	/**
	 * Retrieve all filters for the integration.
	 *
	 * @return array[]
	 */
	protected function getFilters() {
		return [
			[ 'envira_tracking_source', [ $this, 'returnNexcess' ] ],
		];
	}

	/**
	 * Simply return the string "nexcess", used for some partners' referral tracking.
	 *
	 * @return string
	 */
	public function returnNexcess() {
		return 'nexcess';
	}
}
