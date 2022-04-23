<?php

/**
 * Check if WooCommerce Cart Fragments are enabled or disabled.
 */

namespace Nexcess\MAPPS\HealthChecks;

use Nexcess\MAPPS\Concerns\HasWordPressDependencies;
use Nexcess\MAPPS\Integrations\WooCommerceCartFragments as Integration;
use Nexcess\MAPPS\Support\Branding;

class WooCommerceCartFragments extends HealthCheck {
	use HasWordPressDependencies;

	/**
	 * @var \Nexcess\MAPPS\Integrations\WooCommerceCartFragments
	 */
	protected $integration;

	/**
	 * The health check's ID.
	 *
	 * @var string
	 */
	protected $id = 'mapps_woocommerce_cart_fragments';

	/**
	 * Construct a new instance of the health check.
	 *
	 * @param \Nexcess\MAPPS\Integrations\WooCommerceCartFragments $integration The WooCommerceCartFragments integration.
	 */
	public function __construct( Integration $integration ) {
		$this->integration = $integration;

		$this->label       = _x( 'You should disable WooCommerce cart fragments', 'Site Health check', 'nexcess-mapps' );
		$this->description = __( 'Disabling cart fragments will prevent a number of uncached requests on every page load, increasing site performance.', 'nexcess-mapps' );
		$this->badgeLabel  = _x( 'Performance', 'Site Health category', 'nexcess-mapps' );
	}

	/**
	 * Perform the site health check.
	 *
	 * @return bool True if the check passes, false if it fails.
	 */
	public function performCheck() {
		// Check to see if any of the replacement plugins are active.
		if ( $this->isAtLeastOnePluginActive( $this->integration->fullCartPluginList() ) ) {
			return true;
		}

		// Sprintf for readability, because dealing with HTML strings is always _so_ fun.
		$link_start = sprintf( '<a href="%s">', esc_url_raw( admin_url( 'admin.php?page=nexcess-mapps#settings' ) ) );

		// If cart fragments are enabled, show the health check warning.
		if ( 'enabled' === $this->integration->getCartFragmentsSetting() ) {
			$this->description .= PHP_EOL . PHP_EOL . sprintf(
				/* Translators: %1$s: Branded company name, %2$s: Start of link tag, %3$s: End of link tag. */
				__( '%1$s provides %2$s a toggle for enabling or disabling cart fragments%3$s.', 'nexcess-mapps' ),
				Branding::getCompanyName(),
				$link_start,
				'</a>'
			);

			$this->actions  = $link_start;
			$this->actions .= _x( 'Disable WooCommerce cart fragments', 'Site Health action', 'nexcess-mapps' );
			$this->actions .= '</a>';

			return false;
		}

		return true;
	}
}
