<?php

namespace Nexcess\MAPPS\Integrations\StoreBuilder;

use Nexcess\MAPPS\Concerns\HasWordPressDependencies;
use Nexcess\MAPPS\Integrations\DomainChanges;

use const Nexcess\MAPPS\PLUGIN_URL;

class Setup {
	use HasWordPressDependencies;

	/**
	 * @var StoreBuilderFTC
	 */
	private $storebuilder_ftc;

	/**
	 * @var LookAndFeel
	 */
	private $look_and_feel;

	/**
	 * @var ShippingPlugins Supported shipping plugins.
	 */
	protected $shipping_plugins;

	/**
	 * @var PaymentPlugins
	 */
	private $payment_plugins;

	/**
	 * Setup any dependencies that are needed for the class.
	 *
	 * @param StoreBuilderFTC $storebuilder_ftc
	 * @param LookAndFeel     $look_and_feel
	 * @param ShippingPlugins $shipping_plugins
	 * @param PaymentPlugins  $payment_plugins
	 */
	public function __construct( StoreBuilderFTC $storebuilder_ftc, LookAndFeel $look_and_feel, ShippingPlugins $shipping_plugins, PaymentPlugins $payment_plugins ) {
		$this->storebuilder_ftc = $storebuilder_ftc;
		$this->look_and_feel    = $look_and_feel;
		$this->shipping_plugins = $shipping_plugins;
		$this->payment_plugins  = $payment_plugins;
	}

	/**
	 * Creates the Setup Pros based in the cards definitions.
	 *
	 * @return array The Setup Props.
	 */
	public function getSetupProps() {
		$cards = [
			$this->getFtcCard(),
			$this->getLookAndFeelCard(),
			$this->getPaymentGatewayCard(),
			$this->getManageProductsCard(),
			$this->getShippingConfigurationCard(),
			$this->getLaunchDomainCard(),
		];

		// Remove any cards that are empty.
		$cards = array_filter( $cards );

		// Necessary to re-index the array, otherwise JS interprets as an object.
		$cards = array_values( $cards );

		return [
			'title' => __( 'Setup your store', 'nexcess-mapps' ),
			'intro' => __( 'Our set up wizard will help you get the most out of your store.', 'nexcess-mapps' ),
			'cards' => $cards,
		];
	}

	/**
	 * Get card details for the "First Time Configuration".
	 *
	 * @return array The FTC card details.
	 */
	public function getFtcCard() {
		$ftc_complete = $this->storebuilder_ftc->isFtcComplete();
		return [
			'id'        => 'ftc',
			'title'     => __( 'Set up your site', 'nexcess-mapps' ),
			'intro'     => __( 'This is where the fun begins.', 'nexcess-mapps' ),
			'completed' => $ftc_complete,
			'time'      => __( '5 Minutes', 'nexcess-mapps' ),
			'rows'      => [
				[
					'id'      => 'ftc-wizard',
					'type'    => 'task',
					'taskCta' => __( 'Get Started', 'nexcess-mapps' ),
					'title'   => __( 'Site Name, Logo & Store Details', 'nexcess-mapps' ),
					'intro'   => __( 'Tell us a little bit about your site.', 'nexcess-mapps' ),
					'icon'    => 'setup-icon-setup.png',
				],
			],
		];
	}

	/**
	 * Get card details for the "Look and Feel".
	 *
	 * @return array The card details.
	 */
	public function getLookAndFeelCard() {
		$lf_complete = $this->look_and_feel->isComplete();

		return [
			'id'        => 'look-and-feel',
			'title'     => __( 'Update your global styles', 'nexcess-mapps' ),
			'intro'     => __( "It's all about appearances.", 'nexcess-mapps' ),
			'completed' => $lf_complete,
			'time'      => __( '3 Minutes', 'nexcess-mapps' ),
			'rows'      => $this->getLookAndFeelRows( $lf_complete ),
			'footers'   => $this->getLookAndFeelFooters( $lf_complete ),
		];
	}

	/**
	 * Get card details for the "Payment Gateway Card".
	 *
	 * @return array The card details.
	 */
	public function getPaymentGatewayCard() {
		$payment_gateways = [];
		$footer_messages  = [];

		foreach ( $this->payment_plugins->getPlugins() as $plugin ) {
			$payment_gateways[] = $plugin['card']['row'];
			$footer_messages[]  = $plugin['card']['footerMessage'];
		}

		$payment_gateways = array_filter( $payment_gateways );
		$footer_messages  = array_filter( $footer_messages );

		// Hide setup card if no plugins are available.
		if ( empty( $payment_gateways ) ) {
			return [];
		}

		return [
			'id'        => 'payment-gateways',
			'title'     => __( 'Configure payment', 'nexcess-mapps' ),
			'intro'     => __( 'Don\'t leave money on the table.', 'nexcess-mapps' ),
			'completed' => false,
			'time'      => '',
			'rows'      => $payment_gateways,
			'footers'   => [
				[
					'id'       => 'gateway-help',
					'type'     => 'help',
					'title'    => __( 'Need help with payments?', 'nexcess-mapps' ),
					'message'  => '',
					'messages' => $footer_messages,
				],
			],
		];
	}

	/**
	 * Get card details for the "Manage Products".
	 *
	 * @return array The card details.
	 */
	public function getManageProductsCard() {
		return [
			'id'        => 'manage-products',
			'title'     => __( 'Manage your products', 'nexcess-mapps' ),
			'intro'     => __( 'Give the people what they want.', 'nexcess-mapps' ),
			'completed' => false,
			'time'      => '',
			'rows'      => [
				[
					'id'      => 'manage-products-row-1',
					'type'    => 'columns',
					'title'   => '',
					'intro'   => '',
					'columns' => [
						[
							'title' => __( 'Add Products', 'nexcess-mapps' ),
							'links' => [
								[
									'icon'   => 'Add',
									'title'  => __( 'Add a new Product', 'nexcess-mapps' ),
									'url'    => admin_url( 'post-new.php?post_type=product' ),
									'target' => '_self',
								],
								[
									'icon'   => 'LocalLibrary',
									'title'  => __( 'WooCommerce: Managing Products', 'nexcess-mapps' ),
									'url'    => 'https://woocommerce.com/document/managing-products/',
									'target' => '_blank',
								],
							],
						],
						[
							'title' => __( 'Import Products', 'nexcess-mapps' ),
							'links' => [
								[
									'icon'   => 'Upload',
									'title'  => __( 'Import products via CSV', 'nexcess-mapps' ),
									'url'    => admin_url( 'edit.php?post_type=product&page=product_importer' ),
									'target' => '_self',
								],
								[
									'icon'   => 'School',
									'title'  => __( 'Tutorial: Product CSV', 'nexcess-mapps' ),
									'url'    => 'https://woocommerce.com/document/product-csv-importer-exporter/',
									'target' => '_blank',
								],
								[
									'icon'   => 'Downloading',
									'title'  => __( 'Download sample CSV file', 'nexcess-mapps' ),
									'url'    => 'https://github.com/woocommerce/woocommerce/blob/master/sample-data/sample_products.csv',
									'target' => '_blank',
								],
							],
						],
						[
							'title' => __( 'Setting Up Taxes', 'nexcess-mapps' ),
							'links' => [
								[
									'icon'   => 'Add',
									'title'  => __( 'Set Up Tax Rates', 'nexcess-mapps' ),
									'url'    => admin_url( 'admin.php?page=wc-settings&tab=tax' ),
									'target' => '_self',
								],
								[
									'icon'   => 'School',
									'title'  => __( 'WP 101: Tax Settings', 'nexcess-mapps' ),
									'url'    => 'wp101:woocommerce-tax-settings',
									'target' => '_self',
								],
								[
									'icon'   => 'Downloading',
									'title'  => __( 'Sample Tax Rate Table', 'nexcess-mapps' ),
									'url'    => 'https://github.com/woocommerce/woocommerce/blob/master/sample-data/sample_tax_rates.csv',
									'target' => '_blank',
								],
							],
						],
					],
				],
				[
					'id'              => 'learn-product-types',
					'type'            => 'learn-product-types',
					'exampleProducts' => $this->get_example_products(),
				],
			],
		];
	}

	/**
	 * Queries WC for a specific product type.
	 *
	 * Queries the latest product stored in the database.
	 *
	 * @param string $title The Link name.
	 * @param string $type  The WC Product type. Defaults are simple, variable, grouped and external.
	 *
	 * @return array The WC Product if found. Empty otherwise.
	 */
	private function get_wc_product_type( $title, $type ) {
		if ( ! function_exists( 'wc_get_product_types' ) ) {
			return [];
		}

		$args    = [
			'type'  => $type,
			'limit' => '1',
			'order' => 'DESC',
		];
		$product = wc_get_products( $args );
		if ( count( $product ) > 0 ) {
			return [
				'title' => $title,
				'url'   => admin_url( "post.php?post={$product[0]->get_id()}&action=edit" ),
			];
		}
		return [];
	}

	/**
	 * Builds links to WC default product types.
	 *
	 * @return array[] Array with WC Product Examples.
	 */
	private function get_example_products() {
		return array_filter( [
			$this->get_wc_product_type( __( 'Simple', 'nexcess-mapps' ), 'simple' ),
			$this->get_wc_product_type( __( 'Variable', 'nexcess-mapps' ), 'variable' ),
			$this->get_wc_product_type( __( 'Grouped', 'nexcess-mapps' ), 'grouped' ),
			$this->get_wc_product_type( __( 'External', 'nexcess-mapps' ), 'external' ),
		], [ $this, 'validate_empty_arrays' ] );
	}

	/**
	 * If the given array is empty then it will return null.
	 *
	 * @param array $array Array with information.
	 *
	 * @return array|null Array if not empty, null otherwise.
	 */
	public function validate_empty_arrays( $array ) {
		if ( is_array( $array ) ) {
			return count( $array ) > 0 ? $array : null;
		}
		return null;
	}

	/**
	 * Get card details for the "Shipping Configuration".
	 *
	 * @return array The card details.
	 */
	public function getShippingConfigurationCard() {
		// Flat rate is built-in to WooCommerce.
		$rows = [
			[
				'id'          => 'flat-rate',
				'type'        => 'task',
				'taskCta'     => __( 'Flat Rate Settings', 'nexcess-mapps' ),
				'title'       => __( 'Flat Rate Shipping', 'nexcess-mapps' ),
				'intro'       => __( 'Charge a fixed rate of your choosing for shipping.', 'nexcess-mapps' ),
				'icon'        => 'setup-icon-shipping.png',
				'disabled'    => false,
				'disableText' => '',
				'url'         => admin_url( 'admin.php?page=wc-settings&tab=shipping' ),
			],
		];

		// Check our supported shipping plugins.
		foreach ( $this->shipping_plugins->getPlugins() as $plugin ) {
			if ( empty( $plugin['active'] ) || empty( $plugin['card'] ) ) {
				continue;
			}

			$rows[] = $plugin['card'];
		}

		// If there are additional options, make wizard available.
		if ( 1 === count( $rows ) && 0 < count( $this->shipping_plugins->getPlugins() ) ) {
			$rows[] = [
				'id'   => 'shipping-wizard',
				'type' => 'launch-shipping-wizard',
			];
		}

		return [
			'id'        => 'shipping-configuration',
			'title'     => __( 'Configure shipping', 'nexcess-mapps' ),
			'intro'     => __( 'Offer flat rate shipping and/or set up ShipStation to offer multiple rates.', 'nexcess-mapps' ),
			'completed' => false,
			'time'      => '',
			'rows'      => $rows,
			'footers'   => [
				[
					'id'    => 'learning-shipping',
					'type'  => 'accordion',
					'title' => __( 'Learn more about Shipping', 'nexcess-mapps' ),
					'rows'  => [
						[
							'id'   => 'learn-shipping',
							'type' => 'learn-shipping',
						],
						[
							'id'      => 'manage-shipping-row-1',
							'type'    => 'columns',
							'title'   => '',
							'intro'   => '',
							'columns' => [
								[
									'title' => __( 'Shipping Zones', 'nexcess-mapps' ),
									'links' => [
										[
											'icon'   => 'Add',
											'title'  => __( 'Set up Shipping Zones', 'nexcess-mapps' ),
											'url'    => admin_url( 'admin.php?page=wc-settings&tab=shipping' ),
											'target' => '_self',
										],
										[
											'icon'   => 'LocalLibrary',
											'title'  => __( 'WooCommerce: Shipping Zones Docs', 'nexcess-mapps' ),
											'url'    => 'https://woocommerce.com/document/setting-up-shipping-zones/',
											'target' => '_blank',
										],
									],
								],
								[
									'title' => __( 'Shipping Classes', 'nexcess-mapps' ),
									'links' => [
										[
											'icon'   => 'Add',
											'title'  => __( 'Set up Shipping Classes', 'nexcess-mapps' ),
											'url'    => admin_url( 'admin.php?page=wc-settings&tab=shipping&section=classes' ),
											'target' => '_self',
										],
										[
											'icon'   => 'School',
											'title'  => __( 'Tutorial: Shipping Classes', 'nexcess-mapps' ),
											'url'    => 'wp101:woocommerce-shipping-classes',
											'target' => '_self',
										],
										[
											'icon'   => 'LocalLibrary',
											'title'  => __( 'WooCommerce: Shipping Classes Docs', 'nexcess-mapps' ),
											'url'    => 'https://woocommerce.com/document/product-shipping-classes/',
											'target' => '_blank',
										],
									],
								],
								[
									'title' => __( 'Shipping Calculations', 'nexcess-mapps' ),
									'links' => [
										[
											'icon'   => 'Add',
											'title'  => __( 'Set Flat Rate shipping calculations', 'nexcess-mapps' ),
											'url'    => admin_url( 'admin.php?page=wc-settings&tab=shipping&section=options' ),
											'target' => '_blank',
										],
										[
											'icon'   => 'School',
											'title'  => __( 'Tutorial: Flat Rate Shipping', 'nexcess-mapps' ),
											'url'    => 'wp101:woocommerce-flat-rate-shipping',
											'target' => '_self',
										],
									],
								],
							],
						],
					],
				],
			],
		];
	}

	/**
	 * Get card details for the "Launch with a Custom Domain".
	 *
	 * @return array The card details.
	 */
	public function getLaunchDomainCard() {
		$completed = (bool) get_option( DomainChanges::COMPLETED_OPTION_NAME, false );

		$details = [
			'id'        => 'launch-domain',
			'title'     => __( 'Go Live with a domain', 'nexcess-mapps' ),
			'intro'     => __( 'Go live with a custom domain, whether you purchased with Nexcess of elsewhere.', 'nexcess-mapps' ),
			'completed' => $completed,
			'time'      => $completed ? 'complete' : '',
			'rows'      => [
				[
					'id'   => 'launch-domain-status',
					'type' => 'launch-domain-status',
				],
			],
		];

		if ( ! $completed ) {
			$details['rows'][] = [
				'id'      => 'site-domain-wizard',
				'type'    => 'task',
				'title'   => __( 'Publish your site with a custom domain', 'nexcess-mapps' ),
				'intro'   => __( 'Update your store URL with a custom domain you own', 'nexcess-mapps' ),
				'icon'    => 'setup-icon-launch.png',
				'taskCta' => __( 'Get Started', 'nexcess-mapps' ),
			];
		}

		return $details;
	}

	/**
	 * Builds a footer if the domain is connected.
	 *
	 * @param bool $connected The connected status.
	 *
	 * @return array[] Footer with help.
	 */
	private function getLaunchDomainFooters( $connected ) {
		if ( $connected ) {
			// If connected, return this array.
			return [
				[
					'id'       => 'custom-domain-help-disconnect',
					'type'     => 'help',
					'title'    => __( 'Want to disconnect this domain?', 'nexcess-mapps' ),
					'message'  => '',
					'messages' => [
						[
							'title'    => __( 'We\'re happy to help.', 'nexcess-mapps' ),
							'url'      => 'https://www.nexcess.net/support/',
							'target'   => '_self',
							'dashicon' => '',
						],
					],
				],
			];
		}

		return [
			[
				'id'       => 'custom-domain-help',
				'type'     => 'help',
				'title'    => __( 'Need help?', 'nexcess-mapps' ),
				'message'  => '',
				'messages' => [
					[
						'title'    => __( 'Check out our guide on going live.', 'nexcess-mapps' ),
						'url'      => 'https://www.nexcess.net/storebuilder/resources/going-live-with-your-store/',
						'target'   => '_self',
						'dashicon' => '',
					],
				],
			],
		];
	}

	/**
	 * Build the Card rows base in the completed state.
	 *
	 * @param bool $lf_complete True if the look and feel card is completed, false otherwise.
	 *
	 * @return array[] The card rows.
	 */
	private function getLookAndFeelRows( $lf_complete = false ) {

		if ( $lf_complete ) {
			$customizer_url = add_query_arg( [
				'return' => admin_url( 'admin.php?page=storebuilderapp' ),
			], admin_url( 'customize.php' ));
			return [
				[
					'id'      => 'fonts-colors-wizard',
					'type'    => 'task',
					'taskCta' => __( 'Get Started', 'nexcess-mapps' ),
					'title'   => __( 'Fonts & Colors', 'nexcess-mapps' ),
					'intro'   => __( 'Further customize the look of your site.', 'nexcess-mapps' ),
					'icon'    => 'setup-icon-palette.png',
					'url'     => $customizer_url,
				],
			];
		}

		return [
			[
				'id'      => 'look-and-feel-wizard',
				'type'    => 'task',
				'taskCta' => __( 'Get Started', 'nexcess-mapps' ),
				'title'   => __( 'Select A Starter Template', 'nexcess-mapps' ),
				'intro'   => __( 'Choose a design to start with and customize.', 'nexcess-mapps' ),
				'icon'    => 'setup-icon-design.png',
			],
		];
	}

	/**
	 * If the card is completed then a footer section is added.
	 *
	 * @param bool $lf_complete True if the look and feel card is completed, false otherwise.
	 *
	 * @return array|array[] Array with information.
	 */
	private function getLookAndFeelFooters( $lf_complete ) {
		$footer_messages = [
			[
				'title'    => __( 'Edit specific Pages', 'nexcess-mapps' ),
				'url'      => add_query_arg( 'post_type', 'page', admin_url( 'edit.php' ) ),
				'target'   => '_self',
				'dashicon' => '',
			],
		];

		if ( $lf_complete ) {
			$footer_messages[] = [
				'title'    => __( 'Pick a different template', 'nexcess-mapps' ),
				'url'      => '',
				'target'   => '',
				'dashicon' => '',
			];
		}

		return [
			[
				'id'       => 'look-and-feel-wizard',
				'type'     => 'look-and-feel-footer',
				'messages' => $footer_messages,
			],
		];
	}

	/**
	 * Return an array with the Look and Feel props.
	 */
	public function getLookAndFeelProps() {
		return array_merge(
			[
				'canBeClosed'   => true,
				'ajaxUrl'       => admin_url( 'admin-ajax.php' ),
				'ajaxNonce'     => wp_create_nonce( 'kadence-ajax-verification' ),
				'autoLaunch'    => false,
				'theme'         => wp_get_theme()->name,
				'ajaxTelemetry' => [
					'started' => [
						'action' => LookAndFeel::AJAX_STARTED_ACTION,
						'nonce'  => wp_create_nonce( LookAndFeel::AJAX_STARTED_ACTION ),
					],
				],
			],
			get_option( '_storebuilder_look_and_feel', [] )
		);
	}

	/**
	 * Return an array with the Site Domain props.
	 */
	public function getSiteDomainProps() {
		return [
			'canBeClosed'           => true,
			'ajaxUrl'               => admin_url( 'admin-ajax.php' ),
			'autoLaunch'            => false,
			'domainRegistrationUrl' => esc_url( 'https://www.nexcess.net/domain-registration/' ),
			'verifyDomainNonce'     => wp_create_nonce( 'mapps-verify-domain' ),
			'changeDomainNonce'     => wp_create_nonce( 'mapps-change-domain' ),
			'verifyingUrl'          => get_option( DomainChanges::VERIFYING_OPTION_NAME, '' ),
			'ajaxTelemetry'         => [
				'started' => [
					'action' => DomainChanges::AJAX_STARTED_ACTION,
					'nonce'  => wp_create_nonce( DomainChanges::AJAX_STARTED_ACTION ),
				],
			],
		];
	}

	/**
	 * Return an array with the FTC props.
	 */
	public function getFtcProps() {
		$args = [
			'autoLaunch'    => false,
			'canBeClosed'   => true,
			'ajaxTelemetry' => [
				'started' => [
					'action' => StoreBuilderFTC::AJAX_STARTED_ACTION,
					'nonce'  => wp_create_nonce( StoreBuilderFTC::AJAX_STARTED_ACTION ),
				],
			],
		];

		if ( ! $this->storebuilder_ftc->isFtcComplete() ) {
			$args['canBeClosed'] = false;
		}

		return $args;
	}

	/**
	 * Return an array with the Shipping Configuration props.
	 */
	public function getShippingConfigurationProps() {
		$providers = [];

		foreach ( $this->shipping_plugins->getPlugins() as $plugin_slug => $plugin ) {
			$providers[ $plugin_slug ] = [
				'active' => $plugin['active'],
			];
		}

		return [
			'canBeClosed'         => true,
			'ajaxUrl'             => admin_url( 'admin-ajax.php' ),
			'ajaxAction'          => ShippingPlugins::AJAX_ACTION,
			'autoLaunch'          => false,
			'providers'           => $providers,
			'shippingConfigNonce' => wp_create_nonce( ShippingPlugins::AJAX_ACTION ),
			'ajaxTelemetry'       => [
				'started' => [
					'action' => ShippingPlugins::AJAX_STARTED_ACTION,
					'nonce'  => wp_create_nonce( ShippingPlugins::AJAX_STARTED_ACTION ),
				],
			],
		];
	}

	/**
	 * Get the WP101 API Key.
	 *
	 * Validates if the WP101 API Class exists and return the API key.
	 *
	 * @return string The WP101 API Key.
	 */
	public function checkWp101ApiKey() {
		if ( class_exists( \WP101\API::class ) ) {
			return \WP101\API::get_instance()->get_public_api_key();
		}
		return '';
	}

	/**
	 * Return an array with Payments props.
	 */
	public function getPaymentProps() {
		$data = [
			'oauth_urls' => [],
			'admin_urls' => [],
		];

		foreach ( $this->payment_plugins->getPlugins() as $plugin_slug => $plugin ) {
			$data[ $plugin_slug ]['oauth_urls'] = $plugin['oauth_urls'];
			$data[ $plugin_slug ]['admin_url']  = $plugin['admin_url'];
			$data[ $plugin_slug ]['connected']  = $plugin['connected'];
		}

		$data['stripe-nonce']           = wp_create_nonce( 'mapps-get-stripe-keys' );
		$data['paypal-nonce']           = wp_create_nonce( 'mapps-get-paypal-keys' );
		$data['install-plugins-url']    = add_query_arg( 'action', PaymentPlugins::AJAX_INSTALL_ACTION, admin_url( 'admin-ajax.php' ) );
		$data['install-plugins-nonce']  = wp_create_nonce( PaymentPlugins::AJAX_INSTALL_ACTION );
		$data['install-plugins-action'] = PaymentPlugins::AJAX_INSTALL_ACTION;
		$data['oauth-props-url']        = add_query_arg( 'action', PaymentPlugins::AJAX_OAUTH_PROPS_ACTION, admin_url( 'admin-ajax.php' ) );
		$data['oauth-props-nonce']      = wp_create_nonce( PaymentPlugins::AJAX_OAUTH_PROPS_ACTION );
		$data['oauth-props-action']     = PaymentPlugins::AJAX_OAUTH_PROPS_ACTION;

		$data['paypal-onboarding-nonce'] = '';

		if ( class_exists( '\WooCommerce\PayPalCommerce\Onboarding\Endpoint\LoginSellerEndpoint' ) ) {
			$data['paypal-onboarding-nonce'] = wp_create_nonce( \WooCommerce\PayPalCommerce\Onboarding\Endpoint\LoginSellerEndpoint::nonce() );
		}

		$data['ajaxTelemetry'] = [
			'started'   => [
				'action' => PaymentPlugins::AJAX_STARTED_ACTION,
				'nonce'  => wp_create_nonce( PaymentPlugins::AJAX_STARTED_ACTION ),
			],
			'completed' => [
				'action' => PaymentPlugins::AJAX_COMPLETED_ACTION,
				'nonce'  => wp_create_nonce( PaymentPlugins::AJAX_COMPLETED_ACTION ),
			],
		];

		return $data;
	}

	/**
	 * Returns an array with the config for the app first render.
	 *
	 * @return array The UI Data information.
	 */
	public function getUIData() {
		return [
			'app_name'         => __( 'Store Builder', 'nexcess-mapps' ),
			'logo'             => 'storebuilderapp-logo.svg',
			'api_url'          => rest_url( 'nexcess/v1/storebuilderapp' ),
			'site_url'         => site_url(),
			'logout_url'       => wp_logout_url(),
			'assets_url'       => PLUGIN_URL . '/nexcess-mapps/assets/',
			'support_url'      => esc_url( 'https://www.nexcess.net/support/' ),
			'storebuilder_url' => admin_url( 'admin.php?page=storebuilderapp' ),
			'setup'            => [
				'props' => $this->getSetupProps(),
			],
			'ftc'              => [
				'props' => $this->getFtcProps(),
			],
			'look_and_feel'    => [
				'props' => $this->getLookAndFeelProps(),
			],
			'site_domain'      => [
				'props' => $this->getSiteDomainProps(),
			],
			'shipping'         => [
				'props' => $this->getShippingConfigurationProps(),
			],
			'wp101_api_key'    => $this->checkWp101ApiKey(),
			'payments'         => [
				'props' => $this->getPaymentProps(),
			],
		];
	}
}
