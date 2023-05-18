<?php

namespace Nexcess\MAPPS\Commands;

use Nexcess\MAPPS\Integrations\WooCommerceAutomatedTesting as Integration;
use StellarWP\PluginFramework\Exceptions\WPErrorException;

/**
 * Commands for the Nexcess WooCommerce Automated Testing platform.
 */
class WooCommerceAutomatedTesting extends Command {

	/**
	 * @var \Nexcess\MAPPS\Integrations\WooCommerceAutomatedTesting
	 */
	protected $integration;

	/**
	 * Create a new command instance.
	 *
	 * @param \Nexcess\MAPPS\Integrations\WooCommerceAutomatedTesting $integration
	 */
	public function __construct( Integration $integration ) {
		$this->integration = $integration;
	}

	/**
	 * Disable nightly automated WooCommerce tests for a site.
	 *
	 * ## EXAMPLES
	 *
	 * $ wp nxmapps wc automated-testing disable
	 */
	public function disable() {
		if ( ! $this->integration->getOption()->api_key ) {
			return $this->warning(
				__( 'This site is not registered with the WooCommerce Automated Testing platform, skipping', 'nexcess-mapps' )
			);
		}

		try {
			$this->integration->forceEnableOrDisable( false );
			// Remove test product and user.
			$this->integration->deleteTestProductAdminUser();
		} catch ( WPErrorException $e ) {
			$this->error( sprintf( 'Unable to deactivate the site within the WooCommerce Automated Testing platform: %s', $e->getMessage() ) );
		}

		$this->success( sprintf(
			/* Translators: %1$s is the site ID within the WooCommerce Automated Testing platform. */
			__( 'Site has been disabled within the WooCommerce Automated Testing platform! (ID: %1$s)', 'nexcess-mapps' ),
			$this->integration->getOption()->site_id
		) );
	}

	/**
	 * Enable nightly automated WooCommerce tests for a site.
	 *
	 * ## EXAMPLES
	 *
	 * $ wp nxmapps wc automated-testing enable
	 */
	public function enable() {
		try {
			if ( $this->integration->getOption()->api_key ) {
				// Re-enable a site if it's already been registered.
				$this->integration->forceEnableOrDisable( true );
				// Re-add the test product and user for WCAT.
				$this->integration->addTestProductAdminUser();
			} else {
				// Register the site.
				$this->integration->registerSite();
			}
		} catch ( WPErrorException $e ) {
			$this->error( sprintf( 'Unable to connect to WooCommerce Automated Testing: %s', $e->getMessage() ) );
		}

		$this->success( sprintf(
			/* Translators: %1$s is the site ID within the WooCommerce Automated Testing platform. */
			__( 'Site has been enabled with the WooCommerce Automated Testing platform! (ID: %1$s)', 'nexcess-mapps' ),
			$this->integration->getOption()->site_id
		) );
	}

	/**
	 * Refresh site details within the WooCommerce Automated Testing platform.
	 *
	 * ## EXAMPLES
	 *
	 * $ wp nxmapps wc automated-testing update
	 */
	public function update() {
		if ( ! $this->integration->getOption()->api_key ) {
			return $this->error(
				__( 'This site has not yet been registered with the SaaS, cannot update.', 'nexcess-mapps' )
			);
		}

		try {
			$this->integration->updateSite();
		} catch ( WPErrorException $e ) {
			$this->error( sprintf( 'Unable to update site within the WooCommerce Automated Testing platform: %s', $e->getMessage() ) );
		}

		$this->success( sprintf(
			/* Translators: %1$s is the site ID within the WooCommerce Automated Testing platform. */
			__( 'Site has been updated within the WooCommerce Automated Testing platform! (ID: %1$s)', 'nexcess-mapps' ),
			$this->integration->getOption()->site_id
		) );
	}

	/**
	 * Show status of WCAT.
	 */
	public function status() {
		if ( $this->integration->getOption()->get( 'enable_wcat' ) ) {
			$this->log( 'WooCommerce Automated Testing Status: Enabled.' );
		} else {
			$this->log( 'WooCommerce Automated Testing Status: Disabled.' );
		}
	}
}
