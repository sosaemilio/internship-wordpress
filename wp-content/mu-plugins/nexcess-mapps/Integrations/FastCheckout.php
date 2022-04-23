<?php

/**
 * Encourage WooCommerce store owners to consider Fast Checkout.
 *
 * @link https://wordpress.org/plugins/fast-checkout-for-woocommerce
 */

namespace Nexcess\MAPPS\Integrations;

use Nexcess\MAPPS\Concerns\HasDashboardNotices;
use Nexcess\MAPPS\Concerns\HasHooks;
use Nexcess\MAPPS\Concerns\HasWordPressDependencies;
use Nexcess\MAPPS\Settings;
use Nexcess\MAPPS\Support\AdminNotice;
use Nexcess\MAPPS\Support\Helpers;

class FastCheckout extends Integration {
	use HasDashboardNotices;
	use HasHooks;
	use HasWordPressDependencies;

	/**
	 * @var \Nexcess\MAPPS\Settings
	 */
	protected $settings;

	/**
	 * Create a new instance.
	 *
	 * @param \Nexcess\MAPPS\Settings $settings The settings object.
	 */
	public function __construct( Settings $settings ) {
		$this->settings = $settings;
	}

	/**
	 * Determine whether or not this integration should be loaded.
	 *
	 * @return bool Whether or not this integration be loaded in this environment.
	 */
	public function shouldLoadIntegration() {
		return ( ! defined( 'DISABLE_NAG_NOTICES' ) || ! DISABLE_NAG_NOTICES )
			&& version_compare( PHP_VERSION, '7.2', '>=' )
			&& $this->isPluginActive( 'woocommerce/woocommerce.php' )
			&& ! $this->isPluginActive( 'fast-checkout-for-woocommerce/fast.php' );
	}

	/**
	 * Retrieve all actions for the integration.
	 *
	 * @return array[]
	 */
	protected function getActions() {
		return [
			[ 'current_screen', [ $this, 'addDashboardPromos' ] ],
		];
	}

	/**
	 * Register promotions on the admin dashboard.
	 */
	public function addDashboardPromos() {
		// Don't display the notice on wp-admin/update.php.
		if ( 'update' === get_current_screen()->id ) {
			return;
		}

		// If the site is a StoreBuilder site, we don't want to show the notice.
		if ( $this->settings->is_storebuilder ) {
			return;
		}

		if ( $this->isPluginInstalled( 'fast-checkout-for-woocommerce/fast.php' ) ) {
			$message = sprintf(
				'<a href="%1$s">%2$s</a>',
				Helpers::getPluginActivationUrl( 'fast-checkout-for-woocommerce/fast.php' ),
				__( 'Finish setting up 1-Click Fast Checkout for your store', 'nexcess-mapps' )
			);
		} else {
			$message = sprintf(
				'<a href="%1$s"><strong>%2$s</strong></a>  ·  <a href="%3$s"><em>%4$s</em></a>',
				Helpers::getPluginInstallationUrl( 'fast-checkout-for-woocommerce' ),
				__( 'Install 1-Click Fast Checkout for your store and supercharge your sales', 'nexcess-mapps' ),
				'https://help.nexcess.net/79236-woocommerce/how-to-use-fast-on-your-woocommerce-store',
				__( 'Learn how to use Fast on your WooCommerce Store ', 'nexcess-mapps' )
			);
		}

		$notice = new AdminNotice( $message, 'info', true, 'fast-checkout-aug2021' );

		$notice->setCapability( 'install_plugins' );
		$this->addGlobalNotice( $notice );
	}
}
