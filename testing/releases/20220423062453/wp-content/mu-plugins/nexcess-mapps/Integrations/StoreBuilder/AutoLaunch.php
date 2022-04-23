<?php

/**
 * Adds the ability to AutoLaunch the StoreBuilder Wizard.
 */

namespace Nexcess\MAPPS\Integrations\StoreBuilder;

use Nexcess\MAPPS\Concerns\HasHooks;
use Nexcess\MAPPS\Concerns\HasWordPressDependencies;
use Nexcess\MAPPS\Integrations\Integration;
use Nexcess\MAPPS\Settings;

class AutoLaunch extends Integration {
	use HasHooks;
	use HasWordPressDependencies;

	const WIZARD_FTC = 'first-time-configuration';
	const PAGE_SLUG  = 'storebuilderapp';

	/**
	 * @var \Nexcess\MAPPS\Settings
	 */
	protected $settings;

	/**
	 * @var StoreBuilderFTC
	 */
	protected $storebuilder_ftc;

	/**
	 * Initialize the dependencies.
	 *
	 * @param \Nexcess\MAPPS\Settings $settings
	 * @param StoreBuilderFTC         $sb_ftc
	 */
	public function __construct( Settings $settings, StoreBuilderFTC $sb_ftc ) {
		$this->settings         = $settings;
		$this->storebuilder_ftc = $sb_ftc;
	}

	/**
	 * Perform any necessary setup for the integration.
	 *
	 * This method is automatically called as part of Plugin::loadIntegration(), and is the
	 * entry-point for all integrations.
	 */
	public function setup() {
		$this->addHooks();
	}

	/**
	 * Determine whether or not this integration should be loaded.
	 *
	 * @return bool Whether or not this integration should be loaded in this environment.
	 */
	public function shouldLoadIntegration() {
		return $this->settings->is_storebuilder && $this->isPluginActive( 'woocommerce/woocommerce.php' );
	}

	/**
	 * Retrieve all actions for the integration.
	 *
	 * @return array[]
	 */
	protected function getActions() {
		if ( defined( 'DOING_AJAX' ) && DOING_AJAX ) {
			return [];
		}

		return [
			[ 'admin_init', [ $this, 'autolaunchStorebuilder' ] ],
		];
	}

	/**
	 * Auto launch the StoreBuilder Wizard.
	 */
	public function autolaunchStorebuilder() {
		if ( ! $this->storebuilder_ftc->isFtcComplete() ) {
			global $pagenow;
			// phpcs:disable WordPress.Security.NonceVerification.Recommended
			if ( 'admin.php' === $pagenow && isset( $_GET['page'], $_GET['wizard'] ) && self::PAGE_SLUG === $_GET['page'] && self::WIZARD_FTC === $_GET['wizard'] ) {
				return;
			}
			// phpcs:enable WordPress.Security.NonceVerification.Recommended
			wp_safe_redirect( add_query_arg( 'wizard', self::WIZARD_FTC, admin_url( 'admin.php?page=' . self::PAGE_SLUG ) ) );
			exit;
		}
	}
}
