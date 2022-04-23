<?php

/**
 * Customizations related to Recapture plugins.
 *
 * @link https://recapture.io/
 */

namespace Nexcess\MAPPS\Integrations;

use Nexcess\MAPPS\Concerns\HasDashboardNotices;
use Nexcess\MAPPS\Concerns\HasHooks;
use Nexcess\MAPPS\Concerns\HasWordPressDependencies;
use Nexcess\MAPPS\Settings;
use Nexcess\MAPPS\Support\AdminNotice;

class Recapture extends Integration {
	use HasDashboardNotices;
	use HasHooks;
	use HasWordPressDependencies;

	/**
	 * An instance of the PluginInstaller integration.
	 *
	 * @var \Nexcess\MAPPS\Integrations\PluginInstaller
	 */
	private $installer;

	/**
	 * @var \Nexcess\MAPPS\Settings
	 */
	protected $settings;

	/**
	 * The Nexcess discount code.
	 */
	const DISCOUNT_CODE = 'ZJUFKSOXUX';

	/**
	 * The option key that holds discount information.
	 */
	const DISCOUNT_CODE_OPTION = 'recapture_discount_code';

	/**
	 * @param \Nexcess\MAPPS\Settings                     $settings
	 * @param \Nexcess\MAPPS\Integrations\PluginInstaller $installer
	 */
	public function __construct( Settings $settings, PluginInstaller $installer ) {
		$this->settings  = $settings;
		$this->installer = $installer;
	}

	/**
	 * Determine whether or not this integration should be loaded.
	 *
	 * @return bool Whether or not this integration be loaded in this environment.
	 */
	public function shouldLoadIntegration() {
		return $this->installer->shouldLoadIntegration()
			&& ! $this->settings->is_storebuilder;
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
	 * Retrieve all filters for the integration.
	 *
	 * @return array[]
	 */
	protected function getFilters() {
		// phpcs:disable WordPress.Arrays
		return [
			[ 'activate_recapture-for-edd/recapture.php',                  [ $this, 'applyPromo' ] ],
			[ 'activate_recapture-for-restrict-content-pro/recapture.php', [ $this, 'applyPromo' ] ],
			[ 'activate_recapture-for-woocommerce/recapture.php',          [ $this, 'applyPromo' ] ],
		];
		// phpcs:enable WordPress.Arrays
	}

	/**
	 * Register promotions on the admin dashboard.
	 */
	public function addDashboardPromos() {

		// We're already on the Plugin Installer screen.
		if ( 'nexcess_page_nexcess-mapps-dashboard' === get_current_screen()->id ) {
			return;
		}

		$variants = [
			'recapture-nov2020-edd'         => [
				'name'      => 'Easy Digital Downloads',
				'plugin'    => 'easy-digital-downloads/easy-digital-downloads.php',
				'recapture' => 'recapture-for-edd/recapture.php',
			],
			'recapture-nov2020-rcp'         => [
				'name'      => 'Restrict Content Pro',
				'plugin'    => 'restrict-content-pro/restrict-content-pro.php',
				'recapture' => 'recapture-for-restrict-content-pro/recapture.php',
			],
			'recapture-nov2020-woocommerce' => [
				'name'      => 'WooCommerce',
				'plugin'    => 'woocommerce/woocommerce.php',
				'recapture' => 'recapture-for-woocommerce/recapture.php',
			],
		];

		foreach ( $variants as $id => $plugin ) {
			if ( ! $this->isPluginActive( $plugin['plugin'] ) ) {
				continue;
			}

			// Recapture for this plugin is already installed.
			if ( $this->isPluginInstalled( $plugin['recapture'] ) ) {
				continue;
			}

			$message  = sprintf(
				'<strong>We notice you have %1$s installed on your site.</strong>',
				esc_html( $plugin['name'] )
			);
			$message .= PHP_EOL . PHP_EOL;
			$message .= sprintf(
				'Generate more revenue by <a href="%s">activating Recapture</a> for free.',
				esc_url( admin_url( 'admin.php?page=nexcess-mapps-dashboard' ) )
			);

			$notice = new AdminNotice( $message, 'info', true, $id );
			$notice->setCapability( 'install_plugins' );
			$this->addGlobalNotice( $notice );
		}
	}

	/**
	 * Apply the Nexcess promo code to Recapture plugins.
	 *
	 * The code will only be applied if there isn't an existing discount code applied.
	 */
	public function applyPromo() {
		add_option( self::DISCOUNT_CODE_OPTION, [
			'code'        => self::DISCOUNT_CODE,
			'description' => 'Special deal for Nexcess customers -- your first 3 months with Recapture are 30% off the regular price!',
		] );
	}
}
