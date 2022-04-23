<?php

/**
 * Customizations related to the Nexcess CDN.
 */

namespace Nexcess\MAPPS\Integrations;

use Nexcess\MAPPS\Concerns\HasHooks;

class CDN extends Integration {
	use HasHooks;

	/**
	 * Retrieve all filters for the integration.
	 *
	 * @return array[]
	 */
	protected function getFilters() {
		return [
			[ 'cdn_enabler_contents_after_rewrite', [ $this, 'filterCdnUrl' ], -100 ],
		];
	}

	/**
	 * Ensure that Nexcess CDN URIs always include the /cdn/ prefix.
	 *
	 * @param string $url The URL being filtered.
	 *
	 * @return string The potentially-modified $url.
	 */
	public function filterCdnUrl( $url ) {
		return (string) preg_replace( '#(\.nxedge\.io/)(?:cdn/)?([^\'",]+)#i', '$1cdn/$2', $url );
	}
}
