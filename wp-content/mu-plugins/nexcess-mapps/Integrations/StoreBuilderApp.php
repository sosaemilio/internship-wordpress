<?php

/**
 * The Nexcess MAPPS storebuilderapp_setup.
 */

namespace Nexcess\MAPPS\Integrations;

use Nexcess\MAPPS\Concerns\CollectsTelemetryData;
use Nexcess\MAPPS\Concerns\HasAdminPages;
use Nexcess\MAPPS\Concerns\HasAssets;
use Nexcess\MAPPS\Concerns\HasHooks;
use Nexcess\MAPPS\Concerns\HasWordPressDependencies;
use Nexcess\MAPPS\Integrations\StoreBuilder\Setup as StoreBuilderSetup;
use Nexcess\MAPPS\Integrations\StoreBuilder\StoreBuilderUser;
use Nexcess\MAPPS\Routes\StoreBuilderAppFontSync;
use Nexcess\MAPPS\Routes\StoreBuilderAppFTC;
use Nexcess\MAPPS\Routes\StoreBuilderAppFTCPost;
use Nexcess\MAPPS\Routes\StoreBuilderAppLookAndFeel;
use Nexcess\MAPPS\Routes\StoreBuilderAppLookAndFeelPost;
use Nexcess\MAPPS\Routes\StoreBuilderAppRoute;
use Nexcess\MAPPS\Routes\StoreBuilderAppUsername;
use Nexcess\MAPPS\Services\Managers\RouteManager;
use Nexcess\MAPPS\Settings;

use const Nexcess\MAPPS\PLUGIN_URL;

class StoreBuilderApp extends Integration {
	use CollectsTelemetryData;
	use HasAdminPages;
	use HasAssets;
	use HasHooks;
	use HasWordPressDependencies;

	const TELEMETRY_DATA_STORE_NAME = 'nexcess_mapps_storebuilder_telemetry';

	/**
	 * @var \Nexcess\MAPPS\Settings
	 */
	protected $settings;

	/**
	 * @var StoreBuilderSetup $storeBuilderSetup The Store Builder Setup Class
	 */
	protected $storeBuilderSetup;

	/**
	 * @var \Nexcess\MAPPS\Services\Managers\RouteManager
	 */
	protected $route_manager;

	/**
	 * @var StoreBuilderUser
	 */
	protected $storebuilderUser;

	/**
	 * The top-level Nexcess menu page slug.
	 */
	const ADMIN_MENU_SLUG = 'storebuilderapp';

	/**
	 * Initialize the dependencies.
	 *
	 * @param \Nexcess\MAPPS\Settings                       $settings
	 * @param \Nexcess\MAPPS\Services\Managers\RouteManager $route_manager
	 * @param StoreBuilderSetup                             $store_builder_setup The StoreBuilder Class.
	 * @param StoreBuilderUser                              $storebuilder_user   The StoreBuilderUser Class.
	 */
	public function __construct( Settings $settings, RouteManager $route_manager, StoreBuilderSetup $store_builder_setup, StoreBuilderUser $storebuilder_user ) {
		$this->settings          = $settings;
		$this->route_manager     = $route_manager;
		$this->storeBuilderSetup = $store_builder_setup;
		$this->storebuilderUser  = $storebuilder_user;
	}

	/**
	 * Perform any necessary setup for the integration.
	 *
	 * This method is automatically called as part of Plugin::loadIntegration(), and is the
	 * entry-point for all integrations.
	 */
	public function setup() {
		$this->addHooks();
		$this->route_manager->addRoute( StoreBuilderAppRoute::class );
		$this->route_manager->addRoute( StoreBuilderAppFTCPost::class );
		$this->route_manager->addRoute( StoreBuilderAppFTC::class );
		$this->route_manager->addRoute( StoreBuilderAppUsername::class );
		$this->route_manager->addRoute( StoreBuilderAppLookAndFeel::class );
		$this->route_manager->addRoute( StoreBuilderAppLookAndFeelPost::class );
		$this->route_manager->addRoute( StoreBuilderAppFontSync::class );
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
		// phpcs:disable WordPress.Arrays
		return [
			[ 'admin_init',                 [ $this, 'registerAdminColorScheme' ] ],
			[ 'admin_menu',                 [ $this, 'registerMenuPage' ], -1 ],
			[ 'user_register',              [ $this, 'setDefaultAdminColorScheme' ] ],
			[ 'wme_event_wizard_started',   [ $this, 'captureWizardStarted' ] ],
			[ 'wme_event_wizard_completed', [ $this, 'captureWizardCompleted' ] ],
			[ 'wme_event_wizard_telemetry', [ $this, 'captureWizardEvents' ], 10, 3 ],
		];
		// phpcs:enable WordPress.Arrays
	}

	/**
	 * Retrieve all filters for the integration.
	 *
	 * @return array[]
	 */
	protected function getFilters() {
		// phpcs:disable WordPress.Arrays
		return [
			// Collect the Started/Completed Status of Wizards for Telemetry.
			[ 'nexcess_mapps_telemetry_report', [ $this, 'collectSetupData' ] ],
		];
		// phpcs:enable WordPress.Arrays
	}

	/**
	 * Register StoreBuilder admin color scheme.
	 */
	public function registerAdminColorScheme() {
		$assets_dir = PLUGIN_URL . '/nexcess-mapps/assets/';
		wp_admin_css_color( 'storebuilder_scheme_v3', __( 'StoreBuilder', 'nexcess-mapps' ),
			$assets_dir . '/storebuilder-scheme-v3.css',
			[ '#2a3353', '#ffffff', '#192039', '#192039' ],
			[
				'base'    => '#ffffff',
				'focus'   => '#ffffff',
				'current' => '#ffffff',
			]
		);
	}

	/**
	 * Set default admin color scheme.
	 *
	 * @param int $user_id User ID.
	 */
	public function setDefaultAdminColorScheme( $user_id ) {
		update_user_meta( $user_id, 'admin_color', 'storebuilder_scheme_v3' );
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

		$icon  = '<?xml version="1.0" encoding="UTF-8"?><svg fill="none" viewBox="-3 -4 13 24" xmlns="http://www.w3.org/2000/svg"><path d="m3.4616 15.258h-0.83333l0.83333-5.8333h-2.9167c-0.48333 0-0.475-0.26667-0.31667-0.55 0.15833-0.28333 0.041667-0.06667 0.058334-0.1 1.075-1.9 2.6917-4.7333 4.8417-8.5167h0.83333l-0.83333 5.8333h2.9167c0.40834 0 0.46667 0.275 0.39167 0.425l-0.05833 0.125c-3.2833 5.7416-4.9167 8.6166-4.9167 8.6166z" fill="#fff"/></svg>';
		$title = __( 'Set up', 'nexcess-mapps' );

		// Define the top-level navigation item.
		add_menu_page(
			$title,
			$title,
			'manage_options',
			self::ADMIN_MENU_SLUG,
			[ $this, 'renderMenuPage' ],
			// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
			'data:image/svg+xml;base64,' . base64_encode( $icon ),
			3
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
		$maybe_enabled = apply_filters( 'Nexcess\\Mapps\\Branding\\EnableStoreBuilderAppSetupTemplate', true );

		if ( false === $maybe_enabled ) {
			return;
		}

		wp_enqueue_script( 'nexcess-mapps-admin' );

		$this->enqueueScript( 'nexcess-mapps-storebuilderapp', 'storebuilder-app.js', [ 'wp-element', 'underscore', 'password-strength-meter', 'wp-api', 'wp-edit-post' ] );
		$this->enqueueStyle( 'nexcess-mapps-storebuilderapp', 'storebuilder-app.css', [], 'screen' );

		$this->injectScriptData(
			'nexcess-mapps-storebuilderapp',
			'storebuilderapp',
			$this->storeBuilderSetup->getUIData()
		);

		$this->renderTemplate('storebuilderapp-setup', [
			'settings' => $this->settings,
		]);
	}

	/**
	 * Helper function to capture started events emitted by the wizards.
	 *
	 * @param string $wizard Name of the Wizard.
	 */
	public function captureWizardStarted( $wizard ) {
		$this->captureWizardEvents( $wizard, 'started', gmdate( 'c' ) );
	}

	/**
	 * Helper function to capture completion events emitted by the wizards.
	 *
	 * @param string $wizard Name of the Wizard.
	 */
	public function captureWizardCompleted( $wizard ) {
		$this->captureWizardEvents( $wizard, 'completed', gmdate( 'c' ) );
	}

	/**
	 * Collect and save events emitted by the wizards.
	 *
	 * @param string $wizard Name of the Wizard.
	 * @param string $event  Captured event, typically 'started', 'completed', etc.
	 * @param mixed  $data   Data to be stored along with the event.
	 */
	public function captureWizardEvents( $wizard, $event, $data ) {
		$wizards                      = $this->getTelemetryData()->get( 'wizards', [] );
		$wizards[ $wizard ][ $event ] = $data;

		$this->getTelemetryData()->set( 'wizards', $wizards )->save();
	}

	/**
	 * Add StoreBuilder telemetry data to telemetry report.
	 *
	 * @param mixed[] $report
	 *
	 * @return mixed[]
	 */
	public function collectSetupData( $report ) {
		// Limiting this to just the wizards for now until we have a reason to
		// capture dynamic data points.
		if ( ! empty( $this->getTelemetryData()->get( 'wizards' ) ) ) {
			$report['setup']['storebuilder']['wizards'] = $this->getTelemetryData()->get( 'wizards' );
		}

		return $report;
	}
}
