<?php

/**
 * The Nexcess MAPPS dashboard.
 */

namespace Nexcess\MAPPS\Integrations;

use Nexcess\MAPPS\Concerns\HasAdminPages;
use Nexcess\MAPPS\Concerns\HasHooks;
use Nexcess\MAPPS\Settings;
use Nexcess\MAPPS\Support\Branding;

class Dashboard extends Integration {
	use HasAdminPages;
	use HasHooks;

	/**
	 * @var \Nexcess\MAPPS\Settings
	 */
	protected $settings;

	/**
	 * The top-level Nexcess menu page slug.
	 */
	const ADMIN_MENU_SLUG = 'nexcess-mapps';

	/**
	 * @param \Nexcess\MAPPS\Settings $settings
	 */
	public function __construct( Settings $settings ) {
		$this->settings = $settings;
	}

	/**
	 * Determine whether or not this integration should be loaded.
	 *
	 * @return bool Whether or not this integration should be loaded in this environment.
	 */
	public function shouldLoadIntegration() {
		return apply_filters( 'nexcess_mapps_show_dashboard', true );
	}

	/**
	 * Retrieve all actions for the integration.
	 *
	 * @return array[]
	 */
	protected function getActions() {
		// phpcs:disable WordPress.Arrays
		return [
			[ 'admin_menu', [ $this, 'registerMenuPage'         ], -1 ],
			[ 'admin_init', [ $this, 'registerDashboardSection' ], -1 ],
		];
		// phpcs:enable WordPress.Arrays
	}

	/**
	 * Register the "Dashboard" settings section.
	 */
	public function registerDashboardSection() {
		add_settings_section(
			'dashboard',
			_x( 'Dashboard', 'settings section', 'nexcess-mapps' ),
			function () {
				$this->renderTemplate( 'dashboard', [
					'settings' => $this->settings,
				] );
			},
			self::ADMIN_MENU_SLUG
		);
	}

	/**
	 * Register the top-level "Nexcess" menu item.
	 */
	public function registerMenuPage() {
		/*
		 * WordPress uses the svg-painter.js file to re-color SVG files, but this can cause a brief
		 * flash of oddly-colored logos. By setting it to the background color of the admin bar,
		 * the icon remains hidden until it's colored.
		 */
		$icon  = Branding::getCompanyIcon( '#ffffff' );
		$title = Branding::getDashboardPageTitle();

		// Define the top-level navigation item.
		add_menu_page(
			$title,
			Branding::getDashboardMenuItemLabel(),
			'manage_options',
			self::ADMIN_MENU_SLUG,
			[ $this, 'renderMenuPage' ],
			// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
			'data:image/svg+xml;base64,' . base64_encode( $icon ),
			3
		);

		// Define a submenu with the same page, letting us override the first sub-menu.
		add_submenu_page(
			self::ADMIN_MENU_SLUG,
			$title,
			_x( 'Dashboard', 'menu item title', 'nexcess-mapps' ),
			'manage_options',
			self::ADMIN_MENU_SLUG,
			[ $this, 'renderMenuPage' ]
		);
	}

	/**
	 * Render the top-level "Nexcess" admin page.
	 */
	public function renderMenuPage() {

		/**
		 * Allow the admin menu template section to be completely disabled.
		 *
		 * @param bool $maybe_enabled Passing a "false" will disable this template call completely.
		 */
		$maybe_enabled = apply_filters( 'nexcess_mapps_branding_enable_admin_template', true );

		if ( false === $maybe_enabled ) {
			return;
		}

		wp_enqueue_script( 'nexcess-mapps-admin' );

		$this->renderTemplate( 'admin', [
			'settings' => $this->settings,
		] );
	}
}
