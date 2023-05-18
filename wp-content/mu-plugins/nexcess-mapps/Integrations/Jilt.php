<?php

namespace Nexcess\MAPPS\Integrations;

use Nexcess\MAPPS\Concerns\HasDashboardNotices;
use Nexcess\MAPPS\Concerns\HasHooks;
use Nexcess\MAPPS\Concerns\HasWordPressDependencies;
use Nexcess\MAPPS\Support\AdminNotice;
use Nexcess\MAPPS\Support\Helpers;

class Jilt extends Integration {
	use HasDashboardNotices;
	use HasHooks;
	use HasWordPressDependencies;

	/**
	 * Determine if this integration should be loaded.
	 */
	public function shouldLoadIntegration() {
		return $this->isPluginActive( 'jilt-for-woocommerce/jilt-for-woocommerce.php' );
	}

	/**
	 * Get actions for the integration.
	 *
	 * @return array[]
	 */
	public function getActions() {
		return [
			[ 'current_screen', [ $this, 'jiltEol' ] ],
		];
	}

	/**
	 * If Jilt is installed, notify of EOL.
	 */
	public function jiltEol() {
		if ( $this->isPluginActive( 'recapture-for-woocommerce/recapture.php' ) ) {
			$message = sprintf( '<a href="%1$s">%2$s</a>', admin_url( 'plugins.php' ), __( 'The Jilt service is shutting down on the 30th of April 2022, please deactivate and uninstall the Jilt for WooCommerce plugin.', 'nexcess-mapps' ) );
		} else {
			$message = sprintf(
				'%1$s <a href="%2$s">%3$s</a>',
				__( 'The Jilt service is shutting down on the 30th of April 2022. It is recommended that you transition to using the Recapture platform.', 'nexcess-mapps' ),
				Helpers::getPluginInstallationUrl( 'recapture-for-woocommerce' ),
				__( 'The Recapture plugin can be installed on your site using the Nexcess Installer.', 'nexcess-mapps' )
			);
		}

		$jilt_notice = new AdminNotice( $message, 'warning', false, 'jilt-shutdown-apr2022' );
		$jilt_notice->setCapability( 'install_plugins' );
		$this->addGlobalNotice( $jilt_notice, 1 );
	}
}
