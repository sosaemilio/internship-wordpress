<?php

/**
 * Small customizations for the Iconic suite of plugins.
 */

namespace Nexcess\MAPPS\Integrations;

use Nexcess\MAPPS\Concerns\HasHooks;

class Iconic extends Integration {
	use HasHooks;

	/**
	 * Retrieve all filters for the integration.
	 *
	 * @return array[]
	 */
	protected function getFilters() {
		// phpcs:disable WordPress.Arrays
		return [
			// Individual plugin redirects.
			[ 'fs_redirect_on_activation_woothumbs',                               '__return_false', 11 ],
			[ 'fs_redirect_on_activation_show-single-variations',                  '__return_false', 11 ],
			[ 'fs_redirect_on_activation_iconic-woo-delivery-slots',               '__return_false', 11 ],
			[ 'fs_redirect_on_activation_iconic-woo-linked-variations',            '__return_false', 11 ],
			[ 'fs_redirect_on_activation_iconic-woo-attribute-swatches',           '__return_false', 11 ],
			[ 'fs_redirect_on_activation_iconic-bundled-products',                 '__return_false', 11 ],
			[ 'fs_redirect_on_activation_iconic-woo-custom-fields-for-variations', '__return_false', 11 ],
			[ 'fs_redirect_on_activation_iconic-woo-product-configurator',         '__return_false', 11 ],
			[ 'fs_redirect_on_activation_iconic-woo-quickview',                    '__return_false', 11 ],
			[ 'fs_redirect_on_activation_iconic-woo-wishlists',                    '__return_false', 11 ],
			[ 'fs_redirect_on_activation_iconic-woo-account-pages',                '__return_false', 11 ],
			[ 'fs_redirect_on_activation_iconic-woo-quicktray',                    '__return_false', 11 ],
		];
		// phpcs:enable WordPress.Arrays
	}

}
