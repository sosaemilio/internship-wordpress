<?php

namespace Nexcess\MAPPS\Integrations\StoreBuilder;

use Nexcess\MAPPS\Concerns\HasHooks;
use Nexcess\MAPPS\Concerns\HasWordPressDependencies;
use Nexcess\MAPPS\Concerns\InvokesCli;
use Nexcess\MAPPS\Integrations\Integration;
use WC_Stripe_Connect;
use WP_Error;

class PaymentPlugins extends Integration {
	use HasWordPressDependencies;
	use HasHooks;
	use InvokesCli;

	const AJAX_COMPLETED_ACTION   = 'storebuilder_payment_completed';
	const AJAX_INSTALL_ACTION     = 'mapps-payments-install';
	const AJAX_OAUTH_PROPS_ACTION = 'mapps-payments-oauth-props';
	const AJAX_STARTED_ACTION     = 'storebuilder_payment_started';

	const SUPPORTED_STRIPE_VERSION = '6.5.0';
	const SUPPORTED_PAYPAL_VERSION = '1.9.1';

	const PAYPAL_SLUG = 'woocommerce-paypal-payments';
	const STRIPE_SLUG = 'woocommerce-gateway-stripe';

	/**
	 * @var array
	 */
	protected $plugins = [];

	/**
	 * Setup.
	 */
	public function setup() {
		$this->addHooks();
		$this->registerStripe();
		$this->registerPayPal();
	}

	/**
	 * Register actions.
	 */
	protected function getActions() {
		return [
			[ 'wp_ajax_' . self::AJAX_INSTALL_ACTION, [ $this, 'ajax__install_activate_plugins' ] ],
			[ 'wp_ajax_' . self::AJAX_OAUTH_PROPS_ACTION, [ $this, 'ajax__oauth_props' ] ],
			[ 'wp_ajax_' . self::AJAX_STARTED_ACTION, [ $this, 'ajaxStarted' ] ],
			[ 'wp_ajax_' . self::AJAX_COMPLETED_ACTION, [ $this, 'ajaxCompleted' ] ],
		];
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

		do_action( 'wme_event_wizard_started', sprintf( 'payment-%s', $plugin_slug ) );

		return wp_send_json_success();
	}

	/**
	 * AJAX action to register telemetry that wizard completed.
	 */
	public function ajaxCompleted() {
		if ( empty( $_REQUEST['_wpnonce'] ) || empty( $_REQUEST['plugin_slug'] ) ) {
			return wp_send_json_error( 'Missing required parameters.', 400 );
		}

		if ( ! wp_verify_nonce( $_REQUEST['_wpnonce'], self::AJAX_COMPLETED_ACTION ) ) {
			return wp_send_json_error( 'Nonce is invalid.', 403 );
		}

		$plugin_slug = sanitize_text_field( $_REQUEST['plugin_slug'] );

		if ( ! array_key_exists( $plugin_slug, $this->plugins ) ) {
			return wp_send_json_error( 'Plugin is not registered.' );
		}

		do_action( 'wme_event_wizard_completed', sprintf( 'payment-%s', $plugin_slug ) );

		return wp_send_json_success();
	}

	/**
	 * Register Stripe payment gateway.
	 *
	 * @link https://wordpress.org/plugins/woocommerce-gateway-stripe/
	 */
	protected function registerStripe() {
		$plugin_file = self::STRIPE_SLUG . '/woocommerce-gateway-stripe.php';

		if ( $this->isPluginInstalled( $plugin_file ) && ! $this->isPluginVersion( $plugin_file, self::SUPPORTED_STRIPE_VERSION ) ) {
			return;
		}

		$admin_url = admin_url( 'admin.php?page=wc-settings&tab=checkout&section=stripe' );

		add_action( 'wp_ajax_mapps_get_stripe_keys', [ $this, 'ajax__get_stripe_keys' ] );
		add_action( 'woocommerce_init', [ $this, 'action__woocommerce_init' ] );
		add_filter( 'wp_redirect', [ $this, 'filter__wp_redirect' ] );

		$this->plugins[ self::STRIPE_SLUG ] = [
			'active'     => (bool) $this->isPluginActive( $plugin_file ),
			'installed'  => (bool) $this->isPluginInstalled( $plugin_file ),
			'admin_url'  => $admin_url,
			'version'    => self::SUPPORTED_STRIPE_VERSION,
			'oauth_urls' => [],
			'connected'  => false,
			'keys'       => [],
			'card'       => [
				'row'           => [
					'id'          => 'payments-stripe',
					'type'        => 'action',
					'title'       => __( 'Connect Stripe', 'nexcess-mapps' ),
					'intro'       => __( 'Charge credit cards and pay low merchant fees.', 'nexcess-mapps' ),
					'icon'        => 'setup-icon-stripe.png',
					'disableText' => __( 'Manage Stripe', 'nexcess-mapps' ),
					'adminUrl'    => $admin_url,
					'connected'   => false,
					'button'      => [
						'label'           => __( 'Connect Stripe', 'nexcess-mapps' ),
						'backgroundColor' => '#645FF3',
					],
				],
				'footerMessage' => [
					'title'    => __( 'WP 101: Stripe', 'nexcess-mapps' ),
					'url'      => 'wp101:woocommerce-stripe',
					'target'   => '_self',
					'dashicon' => '',
				],
			],
		];
	}

	/**
	 * Register PayPal payment gateway.
	 *
	 * @link https://wordpress.org/plugins/woocommerce-paypal-payments/
	 */
	protected function registerPayPal() {
		$plugin_file = self::PAYPAL_SLUG . '/woocommerce-paypal-payments.php';

		if ( $this->isPluginInstalled( $plugin_file ) && ! $this->isPluginVersion( $plugin_file, self::SUPPORTED_PAYPAL_VERSION ) ) {
			return;
		}

		$admin_url = admin_url( 'admin.php?page=wc-settings&tab=checkout&section=ppcp-gateway' );

		add_action( 'wp_ajax_mapps_get_paypal_keys', [ $this, 'ajax__get_paypal_keys' ] );
		add_action( 'woocommerce_paypal_payments_built_container', [ $this, 'action__woocommerce_paypal_payments_built_container' ] );
		add_filter( 'woocommerce_paypal_payments_onboarding_redirect_url', [ $this, 'filter__woocommerce_paypal_payments_onboarding_redirect_url' ] );

		$this->plugins[ self::PAYPAL_SLUG ] = [
			'active'     => (bool) $this->isPluginActive( $plugin_file ),
			'installed'  => (bool) $this->isPluginInstalled( $plugin_file ),
			'admin_url'  => $admin_url,
			'version'    => self::SUPPORTED_PAYPAL_VERSION,
			'oauth_urls' => [],
			'connected'  => false,
			'keys'       => [],
			'card'       => [
				'row'           => [
					'id'          => 'payments-paypal',
					'type'        => 'action',
					'title'       => __( 'Connect PayPal', 'nexcess-mapps' ),
					'intro'       => __( 'Receive payments via PayPal.', 'nexcess-mapps' ),
					'icon'        => 'setup-icon-paypal.png',
					'disableText' => __( 'Manage PayPal', 'nexcess-mapps' ),
					'adminUrl'    => $admin_url,
					'connected'   => false,
					'button'      => [
						'label'           => __( 'Connect PayPal', 'nexcess-mapps' ),
						'backgroundColor' => '#172C70',
					],
				],
				'footerMessage' => [
					'title'    => __( 'WP 101: Paypal', 'nexcess-mapps' ),
					'url'      => 'wp101:woocommerce-paypal-standard',
					'target'   => '_self',
					'dashicon' => '',
				],
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
	 * Action: woocommerce_init.
	 *
	 * - Capture the oAuth URL for Stripe.
	 * - Identify if Stripe is connected and set two properties.
	 * - Capture Stripe API keys.
	 */
	public function action__woocommerce_init() {
		if ( 'woocommerce_init' !== current_action() ) {
			return;
		}

		if ( ! $this->plugins[ self::STRIPE_SLUG ]['active'] ) {
			return;
		}

		if ( ! function_exists( 'woocommerce_gateway_stripe' ) || ! isset( woocommerce_gateway_stripe()->connect ) || ! method_exists( woocommerce_gateway_stripe()->connect, 'is_connected' ) ) {
			return;
		}

		$stripe    = woocommerce_gateway_stripe();
		$connect   = $stripe->connect;
		$connected = $connect->is_connected();
		$oauth_url = $connect->get_oauth_url();
		$options   = get_option( WC_Stripe_Connect::SETTINGS_OPTION, [] );

		if ( is_wp_error( $oauth_url ) ) {
			$oauth_url = '';
		}

		$options = wp_parse_args( $options, [
			'publishable_key' => '',
			'secret_key'      => '',
		] );

		$this->plugins[ self::STRIPE_SLUG ]['oauth_urls']['default']    = $oauth_url;
		$this->plugins[ self::STRIPE_SLUG ]['connected']                = $connected;
		$this->plugins[ self::STRIPE_SLUG ]['card']['row']['connected'] = $connected;
		$this->plugins[ self::STRIPE_SLUG ]['keys']                     = [
			'publishable' => trim( $options['publishable_key'] ),
			'secret'      => trim( $options['secret_key'] ),
		];
	}

	/**
	 * Action: woocommerce_paypal_payments_built_container.
	 *
	 * - Capture the oAuth URL for PayPal.
	 * - Identify if PayPal is connected and set two properties.
	 * - Capture PayPal API keys.
	 *
	 * @param \Psr\Container\ContainerInterface $container
	 *
	 * @see WooCommerce\PayPalCommerce\WcGateway\Settings\SettingsListener::listen_for_merchant_id()
	 * @see WooCommerce\PayPalCommerce\WcGateway\Settings\SettingsListener::read_active_credentials_from_settings()
	 */
	public function action__woocommerce_paypal_payments_built_container( $container ) {
		if ( 'woocommerce_paypal_payments_built_container' !== current_action() ) {
			return;
		}

		$onboarding = $container->get( 'onboarding.render' );
		$settings   = $container->get( 'wcgateway.settings' );
		$connected  = ( $settings->has( 'client_id' ) && ! empty( $settings->get( 'client_id' ) ) );

		$this->plugins[ self::PAYPAL_SLUG ]['oauth_urls']['advanced']   = $onboarding->get_signup_link( true, [ 'PPCP' ] );
		$this->plugins[ self::PAYPAL_SLUG ]['oauth_urls']['standard']   = $onboarding->get_signup_link( true, [ 'EXPRESS_CHECKOUT' ] );
		$this->plugins[ self::PAYPAL_SLUG ]['connected']                = $connected;
		$this->plugins[ self::PAYPAL_SLUG ]['card']['row']['connected'] = $connected;
		$this->plugins[ self::PAYPAL_SLUG ]['keys']                     = [
			'email_address' => $settings->has( 'merchant_email_production' ) ? $settings->get( 'merchant_email_production' ) : '',
			'merchant_id'   => $settings->has( 'merchant_id_production' ) ? $settings->get( 'merchant_id_production' ) : '',
			'client_id'     => $settings->has( 'client_id_production' ) ? $settings->get( 'client_id_production' ) : '',
		];
	}

	/**
	 * AJAX: mapps_get_stripe_keys.
	 *
	 * Provide Stripe keys as JSON in AJAX reponse.
	 */
	public function ajax__get_stripe_keys() {
		if ( empty( $_POST['_mapps_nonce'] ) || ! wp_verify_nonce( $_POST['_mapps_nonce'], 'mapps-get-stripe-keys' ) ) {
			return wp_send_json_error( new WP_Error(
				'mapps-nonce-failure',
				__( 'The security nonce has expired or is invalid. Please refresh the page and try again.', 'nexcess-mapps' )
			), 400 );
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			return wp_send_json_error( new WP_Error(
				'mapps-capabilities-failure',
				__( 'You do not have permission to perform this action. Please contact a site administrator.', 'nexcess-mapps' )
			), 403 );
		}

		return wp_send_json_success( $this->plugins[ self::STRIPE_SLUG ]['keys'] );
	}

	/**
	 * AJAX: mapps_get_paypal_keys.
	 *
	 * Provide PayPal keys as JSON in AJAX reponse.
	 */
	public function ajax__get_paypal_keys() {
		if ( empty( $_POST['_mapps_nonce'] ) || ! wp_verify_nonce( $_POST['_mapps_nonce'], 'mapps-get-paypal-keys' ) ) {
			return wp_send_json_error( new WP_Error(
				'mapps-nonce-failure',
				__( 'The security nonce has expired or is invalid. Please refresh the page and try again.', 'nexcess-mapps' )
			), 400 );
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			return wp_send_json_error( new WP_Error(
				'mapps-capabilities-failure',
				__( 'You do not have permission to perform this action. Please contact a site administrator.', 'nexcess-mapps' )
			), 403 );
		}

		return wp_send_json_success( $this->plugins[ self::PAYPAL_SLUG ]['keys'] );
	}

	/**
	 * Filter: wp_redirect.
	 *
	 * Redirect completion of Stripe oAuth workflow back into wizard.
	 *
	 * @see WC_Stripe_Connect::maybe_handle_redirect()
	 *
	 * @param string $location
	 *
	 * @return string
	 */
	public function filter__wp_redirect( $location ) {
		if ( 'wp_redirect' !== current_filter() ) {
			return $location;
		}

		// Not Stripe oAuth workflow completion.
		if ( ! isset( $_GET['wcs_stripe_code'], $_GET['wcs_stripe_state'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return $location;
		}

		return add_query_arg( [
			'page'   => 'storebuilderapp',
			'wizard' => 'payments-stripe',
			'step'   => 'account-keys',
		], admin_url( 'admin.php' ) );
	}

	/**
	 * Filter: woocommerce_paypal_payments_onboarding_redirect_url.
	 *
	 * Redirect completion of PayPal oAuth workflow back into wizard.
	 *
	 * @param string $location
	 *
	 * @return string
	 */
	public function filter__woocommerce_paypal_payments_onboarding_redirect_url( $location ) {
		if ( 'woocommerce_paypal_payments_onboarding_redirect_url' !== current_filter() ) {
			return $location;
		}

		return add_query_arg( [
			'page'   => 'storebuilderapp',
			'wizard' => 'payments-paypal',
			'step'   => 'account-keys',
		], admin_url( 'admin.php' ) );
	}

	/**
	 * AJAX action to install and activate requested plugins.
	 */
	public function ajax__install_activate_plugins() {
		if ( empty( $_POST['_mapps_nonce'] ) || ! wp_verify_nonce( $_POST['_mapps_nonce'], self::AJAX_INSTALL_ACTION ) ) {
			return wp_send_json_error( new WP_Error(
				'mapps-nonce-failure',
				__( 'The security nonce has expired or is invalid. Please refresh the page and try again.', 'nexcess-mapps' )
			), 400 );
		}

		if ( ! current_user_can( 'install_plugins' ) ) {
			return wp_send_json_error( new WP_Error(
				'mapps-capabilities-failure',
				__( 'You do not have permission to perform this action. Please contact a site administrator.', 'nexcess-mapps' )
			), 403 );
		}

		$requested_plugins = filter_input( INPUT_POST, 'paymentGateways', FILTER_DEFAULT, FILTER_REQUIRE_ARRAY );

		if ( is_null( $requested_plugins ) ) {
			return wp_send_json_error( new WP_Error(
				'mapps-missing-parameter',
				__( 'Required "paymentGateways" parameter is missing. Please check your spelling and try again.', 'nexcess-mapps' )
			), 422 );
		}

		$requested_plugin = array_pop( $requested_plugins );
		$install_plugin   = '';

		if ( isset( $this->plugins[ $requested_plugin ] ) && empty( $this->plugins[ $requested_plugin ]['active'] ) ) {
			$install_plugin = $requested_plugin;
		}

		if ( empty( $install_plugin ) ) {
			return wp_send_json_error( new WP_Error(
				'mapps-missing-parameter',
				__( 'Required "paymentGateways" parameter was populated, but not actionable.', 'nexcess-mapps' )
			), 422 );
		}

		$response = $this->makeCommand( 'wp plugin install', [
			$install_plugin,
			'--activate',
			sprintf( '--version=%s', $this->plugins[ $requested_plugin ]['version'] ),
			'--force',
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

		return wp_send_json_success( null, 200 );
	}

	/**
	 * AJAX action to get oAuth properties for specified plugin.
	 */
	public function ajax__oauth_props() {
		if ( empty( $_POST['_mapps_nonce'] ) || ! wp_verify_nonce( $_POST['_mapps_nonce'], self::AJAX_INSTALL_ACTION ) ) {
			return wp_send_json_error( new WP_Error(
				'mapps-nonce-failure',
				__( 'The security nonce has expired or is invalid. Please refresh the page and try again.', 'nexcess-mapps' )
			), 400 );
		}

		if ( ! current_user_can( 'install_plugins' ) ) {
			return wp_send_json_error( new WP_Error(
				'mapps-capabilities-failure',
				__( 'You do not have permission to perform this action. Please contact a site administrator.', 'nexcess-mapps' )
			), 403 );
		}

		$requested_plugins = filter_input( INPUT_POST, 'paymentGateways', FILTER_DEFAULT, FILTER_REQUIRE_ARRAY );

		if ( is_null( $requested_plugins ) ) {
			return wp_send_json_error( new WP_Error(
				'mapps-missing-parameter',
				__( 'Required "paymentGateways" parameter is missing. Please check your spelling and try again.', 'nexcess-mapps' )
			), 422 );
		}

		$requested_plugin = array_pop( $requested_plugins );

		if ( ! isset( $this->plugins[ $requested_plugin ] ) || empty( $this->plugins[ $requested_plugin ]['active'] ) ) {
			return wp_send_json_error( new WP_Error(
				'mapps-missing-parameter',
				__( 'Required "paymentGateways" parameter was populated, but not actionable.', 'nexcess-mapps' )
			), 422 );
		}

		$data = [
			$requested_plugin => $this->plugins[ $requested_plugin ]['oauth_urls'],
		];

		/**
		 * Include PayPay's onboarding nonce.
		 */
		if ( self::PAYPAL_SLUG === $requested_plugin ) {
			$data[ $requested_plugin ]['onboarding_nonce'] = wp_create_nonce( \WooCommerce\PayPalCommerce\Onboarding\Endpoint\LoginSellerEndpoint::nonce() );
		}

		return wp_send_json_success( $data, 200 );
	}

}
