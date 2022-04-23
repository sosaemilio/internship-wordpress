<?php

/**
 * Functionality related to Nexcess support.
 */

namespace Nexcess\MAPPS\Integrations;

use Nexcess\MAPPS\Concerns\HasAdminPages;
use Nexcess\MAPPS\Concerns\HasHooks;
use Nexcess\MAPPS\Settings;
use Nexcess\MAPPS\Support\Helpers;

class Support extends Integration {
	use HasAdminPages;
	use HasHooks;

	/**
	 * @var \Nexcess\MAPPS\Settings
	 */
	protected $settings;

	/**
	 * General support URL for Nexcess.
	 */
	const SUPPORT_URL = 'https://www.nexcess.net/support/';

	/**
	 * @param \Nexcess\MAPPS\Settings $settings
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
		/**
		 * Determine whether the "Support" section of the Nexcess Dashboard should be available.
		 *
		 * @param bool $enabled True if the section should be present, false otherwise.
		 */
		return (bool) apply_filters( 'nexcess_mapps_branding_enable_support_template', true );
	}

	/**
	 * Retrieve all actions for the integration.
	 *
	 * @return array[]
	 */
	protected function getActions() {
		return [
			[ 'admin_init', [ $this, 'registerSupportSection' ], 1000 ],  // Feedback tabs start at 1000, so this is the first one.
		];
	}

	/**
	 * Register the "Support" settings section.
	 */
	public function registerSupportSection() {
		add_settings_section(
			'support',
			_x( 'Support', 'settings section', 'nexcess-mapps' ),
			function () {
				$this->renderTemplate( 'support', [
					'details'  => $this->getSupportDetails(),
					'settings' => $this->settings,
				] );
			},
			Dashboard::ADMIN_MENU_SLUG
		);
	}

	/**
	 * Retrieve an array of support details.
	 *
	 * @return mixed[] Details that should be provided in the support details section of the
	 *                 Nexcess MAPPS dashboard.
	 */
	protected function getSupportDetails() {
		$details = [
			'Account ID'       => $this->settings->account_id,
			'Package'          => $this->settings->package_label,
			'Plan Name'        => $this->settings->plan_name,
			'Plan Type'        => $this->settings->plan_type,
			'PHP Version'      => $this->settings->php_version,
			'WP_DEBUG enabled' => Helpers::getEnabled( defined( 'WP_DEBUG' ) && WP_DEBUG ),
		];

		/**
		 * Filter the details displayed on the Nexcess MAPPS dashboard.
		 *
		 * @param array<string,mixed> An array of details, keyed by their label.
		 */
		return apply_filters( 'Nexcess\\MAPPS\\support_details', $details );
	}
}
