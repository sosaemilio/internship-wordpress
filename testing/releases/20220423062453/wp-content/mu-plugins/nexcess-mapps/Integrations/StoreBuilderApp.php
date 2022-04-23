<?php

/**
 * The Nexcess MAPPS storebuilderapp_setup.
 */

namespace Nexcess\MAPPS\Integrations;

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
	use HasAdminPages;
	use HasAssets;
	use HasHooks;
	use HasWordPressDependencies;

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
			[ 'admin_init',              [ $this, 'registerAdminColorScheme' ] ],
			[ 'admin_menu',              [ $this, 'registerMenuPage'], -1 ],
			[ 'user_register',           [ $this, 'setDefaultAdminColorScheme'] ],
			[ 'validate_password_reset', [ $this, 'autoLoginFromInitialResetEmail' ], 1, 2 ],
		];
		// phpcs:enable WordPress.Arrays
	}

	/**
	 * Auto-log the user in if they are coming from the original password reset email.
	 * This allows us to skip the inital "Enter your new password below or generate one."
	 * and handle that in the first time set up wizard.
	 *
	 * @param \WP_Error          $errors WP Error object.
	 * @param \WP_User|\WP_Error $user   WP_User object if the login and reset key match. WP_Error object otherwise.
	 */
	public function autoLoginFromInitialResetEmail( $errors, $user ) {
		// Safety check. Make sure we have the expected types of data + no errors.
		// The password reset link we generate for the welcome email gets the additional
		// param of "app=storebuilder", so we can easily differentiate between that and
		// normal reset password links.
		if (
			! isset( $_GET['app'] ) // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			|| ( 'storebuilder' !== $_GET['app'] ) // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			|| is_wp_error( $user )
			|| ! is_wp_error( $errors )
			|| ! empty( $errors->errors )
			|| ! empty( $errors->error_data )
			|| ( get_option( 'admin_email' ) !== $user->data->user_email ) // Only want this to happen to the original admin user.
		) {
			return;
		}

		// Generate a new password for the user and update it.
		$new_pass = wp_generate_password( 24, true, true );
		wp_set_password( $new_pass, $user->ID );

		$signed_on = wp_signon( [
			'user_login'    => $user->data->user_login,
			'user_password' => $new_pass,
			'remember'      => false,
		] );

		// Bail out if we didn't sign the user in.
		if ( is_wp_error( $signed_on ) ) {
			return;
		}

		// Send the user to the first time set up wizard.
		wp_safe_redirect( admin_url( '?page=' . self::ADMIN_MENU_SLUG ) );
		exit;
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
}
