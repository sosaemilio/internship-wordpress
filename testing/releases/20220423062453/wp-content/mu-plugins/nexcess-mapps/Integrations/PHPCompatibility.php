<?php

/**
 * Provide additional filtering for PHPCompatibility checks.
 */

namespace Nexcess\MAPPS\Integrations;

use Nexcess\MAPPS\Concerns\HasHooks;

class PHPCompatibility extends Integration {
	use HasHooks;

	/**
	 * Retrieve all filters for the integration.
	 *
	 * @return array[]
	 */
	protected function getFilters() {
		return [
			[ 'phpcompat_whitelist', [ $this, 'whitelistPluginFiles' ] ],
		];
	}

	/**
	 * Inject plugin files that should be whitelisted within PHP compatibility checks.
	 *
	 * @param string[] $whitelist Currently-whitelisted plugins.
	 *
	 * @return string[] The $whitelist array with our plugins included.
	 */
	public function whitelistPluginFiles( $whitelist ) {
		return array_merge( $whitelist, [
			'*/woocommerce-pdf-invoices-packing-slips/vendor/dompdf/dompdf/src/Adapter/CPDF.php',
			'*/woocommerce-pdf-invoices-packing-slips/vendor/phenx/php-svg-lib/src/Svg/Surface/SurfaceCpdf.php',
			'*/ithemes-sync/functions.php',
		] );
	}
}
