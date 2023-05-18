<?php

namespace Nexcess\MAPPS\Integrations\StoreBuilder;

use Nexcess\MAPPS\Concerns\InvokesCli;
use Nexcess\MAPPS\Integrations\Integration;
use WP_Error;

class ShippingPlugins extends Integration {
	use InvokesCli;

	const AJAX_STARTED_ACTION = 'storebuilder_shipping_started';
	const AJAX_ACTION         = 'mapps-shipping-configuration';

	/**
	 * @var array
	 */
	protected $plugins = [];

	/**
	 * Setup.
	 */
	public function setup() {
		$this->registerHooks();
		$this->registerPlugins();
	}

	/**
	 * Register hooks.
	 */
	protected function registerHooks() {
		add_action( 'wp_ajax_' . self::AJAX_ACTION, [ $this, 'ajax__install_activate_plugins' ] );
		add_action( 'wp_ajax_' . self::AJAX_STARTED_ACTION, [ $this, 'ajaxStarted' ] );
	}

	/**
	 * AJAX action to register telemetry that wizard started.
	 */
	public function ajaxStarted() {
		if ( empty( $_REQUEST['_wpnonce'] ) || empty( $_REQUEST['plugin_slug'] ) ) {
			return wp_send_json_error( 'Missing required parameters.', 400 );
		}

		if ( ! wp_verify_nonce( $_REQUEST['_wpnonce'], self::AJAX_STARTED_ACTION ) ) {
			return wp_send_json_error( 'Nonce is invalid.', 403 );
		}

		$plugin_slug = sanitize_text_field( $_REQUEST['plugin_slug'] );

		if ( ! array_key_exists( $plugin_slug, $this->plugins ) ) {
			return wp_send_json_error( 'Plugin is not registered.' );
		}

		do_action( 'wme_event_wizard_started', sprintf( 'shipping-%s', $plugin_slug ) );

		return wp_send_json_success();
	}

	/**
	 * Register supported plugins.
	 */
	protected function registerPlugins() {
		/**
		 * ELEX WooCommerce USPS Shipping Method.
		 *
		 * @link https://wordpress.org/plugins/elex-usps-shipping-method/
		 */
		$this->plugins['elex-usps-shipping-method'] = [
			'active' => (bool) is_plugin_active( 'elex-usps-shipping-method/usps-woocommerce-shipping.php' ),
			'card'   => [
				'id'          => 'usps',
				'type'        => 'task',
				'taskCta'     => __( 'USPS Settings', 'nexcess-mapps' ),
				'title'       => __( 'USPS', 'nexcess-mapps' ),
				'intro'       => __( 'Shipping rates based on address and cart content through USPS.', 'nexcess-mapps' ),
				'icon'        => 'setup-icon-usps.png',
				'disabled'    => false,
				'disableText' => '',
				'url'         => admin_url( 'admin.php?page=wc-settings&tab=shipping&section=elex_shipping_usps' ),
			],
		];
	}

	/**
	 * Get supported plugins.
	 *
	 * @return array
	 */
	public function getPlugins() {
		return $this->plugins;
	}

	/**
	 * AJAX action to install and activate requested plugins.
	 */
	public function ajax__install_activate_plugins() {
		if ( empty( $_POST['_mapps_nonce'] ) || ! wp_verify_nonce( $_POST['_mapps_nonce'], self::AJAX_ACTION ) ) {
			return wp_send_json_error( new WP_Error(
				'mapps-nonce-failure',
				__( 'The security nonce has expired or is invalid. Please refresh the page and try again.', 'nexcess-mapps' )
			), 400 );
		}

		if ( ! current_user_can( 'install_plugins' ) ) {
			return wp_send_json_error( new WP_Error(
				'mapps-capabilities-failure',
				__( 'You do not have permission to perform this action. Please contact a site administrator or log into the Nexcess portal to change the site domain.', 'nexcess-mapps' )
			), 403 );
		}

		$requested_plugins = filter_input( INPUT_POST, 'shippingProviders', FILTER_DEFAULT, FILTER_REQUIRE_ARRAY );

		if ( is_null( $requested_plugins ) ) {
			return wp_send_json_error( new WP_Error(
				'mapps-missing-parameter',
				__( 'Required "shippingProviders" parameter is missing. Please check your spelling and try again.', 'nexcess-mapps' )
			), 422 );
		}

		$install_plugins = [];

		foreach ( $requested_plugins as $plugin_slug ) {
			if ( ! isset( $this->plugins[ $plugin_slug ] ) ) {
				continue;
			}

			if ( ! empty( $this->plugins[ $plugin_slug ]['active'] ) ) {
				continue;
			}

			$install_plugins[] = $plugin_slug;
		}

		if ( empty( $install_plugins ) ) {
			return wp_send_json_error( new WP_Error(
				'mapps-missing-parameter',
				__( 'Required "shippingProviders" parameter was populated, but not actionable.', 'nexcess-mapps' )
			), 422 );
		}

		$response = $this->makeCommand( 'wp plugin install', [
			implode( ' ', $install_plugins ),
			'--activate',
		] )->execute();

		if ( ! $response->wasSuccessful() ) {
			return wp_send_json_error( new WP_Error(
				'mapps-wpcli-error',
				sprintf(
					/* Translators: %1$s is the exit code from WP CLI; %2$s is output from WP CLI. */
					__( 'Encountered WP CLI exit code "%1$s" with output "%2$s".', 'nexcess-mapps' ),
					sanitize_text_field( $response->getExitCode() ),
					sanitize_text_field( $response->getOutput() )
				)
			), 500 );
		}

		foreach ( $install_plugins as $plugin_slug ) {
			do_action( 'wme_event_wizard_completed', sprintf( 'shipping-%s', $plugin_slug ) );
		}

		return wp_send_json_success( null, 200 );
	}

}
