<?php

/**
 * Customizations to themes.
 */

namespace Nexcess\MAPPS\Integrations;

use Nexcess\MAPPS\Concerns\HasHooks;

class Themes extends Integration {
	use HasHooks;

	/**
	 * Retrieve all filters for the integration.
	 *
	 * @return array[]
	 */
	protected function getFilters() {
		return [
			// Whitelabel Astra to hide ads and up-sells (outside of Astra Pro).
			[ 'astra_is_white_labelled', '__return_true' ],

			/**
			 * Hide Kadence's welcome notice.
			 */
			[ 'pre_option_kadence_starter_plugin_notice', '__return_true' ],
			[ 'pre_option_kadence_blocks_redirect_on_activation', '__return_zero' ],
		];
	}
}
