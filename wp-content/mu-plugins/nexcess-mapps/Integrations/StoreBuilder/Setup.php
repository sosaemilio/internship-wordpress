<?php

namespace Nexcess\MAPPS\Integrations\StoreBuilder;

use Nexcess\MAPPS\Concerns\HasWordPressDependencies;

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
	 * Setup any dependencies that are needed for the class.
	 *
	 * @param StoreBuilderFTC $storebuilder_ftc
	 * @param LookAndFeel     $look_and_feel
	 */
	public function __construct( StoreBuilderFTC $storebuilder_ftc, LookAndFeel $look_and_feel ) {
		$this->storebuilder_ftc = $storebuilder_ftc;
		$this->look_and_feel    = $look_and_feel;
	}

	/**
	 * Creates the Setup Pros based in the cards definitions.
	 *
	 * @return array The Setup Props.
	 */
	public function getSetupProps() {
		return [
			'title' => __( 'Setup your store', 'nexcess-mapps' ),
			'intro' => __( 'Our set up wizard will help you get the most out of your store.', 'nexcess-mapps' ),
			'cards' => [
				$this->getFtcCard(),
				$this->getLookAndFeelCard(),
				$this->getPaymentGatewayCard(),
				$this->getManageProductsCard(),
				$this->getShippingConfigurationCard(),
			],
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
					'id'    => 'ftc-wizard',
					'type'  => 'task',
					'title' => __( 'Site Name, Logo & Store Details', 'nexcess-mapps' ),
					'intro' => __( 'Tell us a little bit about your site.', 'nexcess-mapps' ),
					'icon'  => 'setup-icon-setup.png',
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
			'title'     => __( 'Design your site', 'nexcess-mapps' ),
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

		if ( $this->isPluginActive( 'woocommerce-gateway-stripe/woocommerce-gateway-stripe.php' ) ) {
			$payment_gateways[] = $this->getStripeRow();
		}

		if ( $this->isPluginActive( 'woocommerce-paypal-payments/woocommerce-paypal-payments.php' ) ) {
			$payment_gateways[] = $this->getPaypalRow();
		}

		return [
			'id'        => 'payment-gateways',
			'title'     => __( 'Configure payment', 'nexcess-mapps' ),
			'intro'     => __( 'Dont leave money on the table.', 'nexcess-mapps' ),
			'completed' => false,
			'time'      => '',
			'rows'      => $payment_gateways,
			'footers'   => [
				[
					'id'       => 'gateway-help',
					'type'     => 'help',
					'title'    => __( 'Need help with payments?', 'nexcess-mapps' ),
					'message'  => '',
					'messages' => [
						[
							'title'    => __( 'WP 101: Stripe', 'nexcess-mapps' ),
							'url'      => 'wp101:woocommerce-stripe',
							'target'   => '_self',
							'dashicon' => '',
						],
						[
							'title'    => __( 'WP 101: Paypal', 'nexcess-mapps' ),
							'url'      => 'wp101:woocommerce-paypal-standard',
							'target'   => '_self',
							'dashicon' => '',
						],
					],
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
		$shipping_url = admin_url( 'admin.php?page=wc-settings&tab=shipping' );
		return [
			'id'        => 'shipping-configuration',
			'title'     => __( 'Configure shipping', 'nexcess-mapps' ),
			'intro'     => __( 'Offer flat rate shipping and/or set up ShipStation to offer multiple rates.', 'nexcess-mapps' ),
			'completed' => false,
			'time'      => '',
			'rows'      => [
				[
					'id'          => 'flat-rate',
					'type'        => 'task',
					'title'       => __( 'Flat Rate Shipping', 'nexcess-mapps' ),
					'intro'       => __( 'Charge a fixed rate of your choosing for shipping.', 'nexcess-mapps' ),
					'icon'        => 'setup-icon-shipping.png',
					'disabled'    => false,
					'disableText' => '',
					'url'         => $shipping_url,
				],
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
					'id'    => 'fonts-colors-wizard',
					'type'  => 'task',
					'title' => __( 'Fonts & Colors', 'nexcess-mapps' ),
					'intro' => __( 'Further customize the look of your site.', 'nexcess-mapps' ),
					'icon'  => 'setup-icon-palette.png',
					'url'   => $customizer_url,
				],
			];
		}

		return [
			[
				'id'    => 'look-and-feel-wizard',
				'type'  => 'task',
				'title' => __( 'Select A Starter Template', 'nexcess-mapps' ),
				'intro' => __( 'Choose a design to start with and customize.', 'nexcess-mapps' ),
				'icon'  => 'setup-icon-design.png',
			],
		];
	}

	/**
	 * If the card is completed then a footer section is added.
	 *
	 * @param bool $lf_complete True if the look and feel card is completed, false otherwise.
	 *
	 * @return array|array[] Array with information. Empty array otherwise.
	 */
	private function getLookAndFeelFooters( $lf_complete ) {
		if ( $lf_complete ) {
			return [
				[
					'id'       => 'look-and-feel-wizard',
					'type'     => 'status',
					'title'    => __( 'Selected Template:', 'nexcess-mapps' ),
					'message'  => $this->look_and_feel->getTemplate(),
					'messages' => [],
				],
			];
		}

		return [];
	}

	/**
	 * Get Stripe details for Payment Gateways Card.
	 */
	private function getStripeRow() {
		$stripe_url = admin_url( 'admin.php?page=wc-settings&tab=checkout&section=stripe' );

		if ( false === $this->isStripeEnabled() ) {
			$stripe_status = [
				'disabled'  => false,
				'connected' => false,
			];
		} else {
			$stripe_status = [
				'disabled'  => false,
				'connected' => true,
			];
		}

		$defaults = [
			'id'          => 'stripe',
			'type'        => 'task',
			'title'       => __( 'Set Up Stripe', 'nexcess-mapps' ),
			'intro'       => __( 'Charge credit cards and pay low merchant fees.', 'nexcess-mapps' ),
			'icon'        => 'setup-icon-stripe.png',
			'disableText' => __( 'Manage', 'nexcess-mapps' ),
			'button'      => [
				'label'           => __( 'Connect Stripe', 'nexcess-mapps' ),
				'url'             => $stripe_url,
				'backgroundColor' => '#645FF3',
			],
		];
		return array_merge( $defaults, $stripe_status );
	}

	/**
	 * Get PayPal details for Payment Gateways Card.
	 */
	private function getPaypalRow() {
		$paypal_url = admin_url( 'admin.php?page=wc-settings&tab=checkout&section=ppcp-gateway' );

		if ( false === $this->isPaypalConnected() ) {
			$paypal_status = [
				'disabled'  => false,
				'connected' => false,
			];
		} else {
			$paypal_status = [
				'disabled'  => false,
				'connected' => true,
			];
		}

		$defaults = [
			'id'          => 'paypal',
			'type'        => 'task',
			'title'       => __( 'Set Up PayPal', 'nexcess-mapps' ),
			'intro'       => __( 'Receive payments via PayPal.', 'nexcess-mapps' ),
			'icon'        => 'setup-icon-paypal.png',
			'disableText' => __( 'Manage', 'nexcess-mapps' ),
			'button'      => [
				'label'           => __( 'Connect PayPal', 'nexcess-mapps' ),
				'url'             => $paypal_url,
				'backgroundColor' => '#172C70',
			],
		];
		return array_merge( $defaults, $paypal_status );
	}

	/**
	 * Check if the Stripe Integrations is enabled.
	 *
	 * @return bool If integration is not enabled.
	 */
	private function isStripeEnabled() {
		$gateways = $this->isStripeGatewayInstalled();
		if ( is_array( $gateways ) ) {
			return 'yes' === $gateways['stripe']->enabled;
		}
		return false;
	}

	/**
	 * Check if the PayPal Integrations is enabled.
	 *
	 * @return bool If integration is not enabled.
	 */
	private function isPaypalConnected() {
		$gateways = $this->isPaypalGatewayInstalled();
		if ( is_array( $gateways ) ) {
			return 'yes' === $gateways['ppcp-gateway']->enabled;
		}
		return false;
	}

	/**
	 * Check if the Stripe Gateway is installed.
	 *
	 * The Woocommerce Stripe plugin needs to be installed. When the Stripe
	 * plugin is active not only 1 gateway is available, there a more Stripe
	 * gateways, but if the `stripe` key exists then more gateways might exist.
	 * We just need to check for the main gateway.
	 *
	 * @return mixed The gateways array, false otherwise
	 */
	public function isStripeGatewayInstalled() {
		if ( ! class_exists( 'WC' ) ) {
			return false;
		}

		$gateways = WC()->payment_gateways()->payment_gateways();
		if ( array_key_exists( 'stripe', $gateways ) ) {
			return $gateways;
		}
		return false;
	}

	/**
	 * Check if the PayPal Gateway is installed.
	 *
	 * The Woocommerce PayPal plugin needs to be installed. When the PayPal
	 * plugin is active not only 1 gateway is available, there a more PayPal
	 * gateways, but if the `ppcp-gateway` key exists then more gateways might exist.
	 * We just need to check for the main gateway.
	 *
	 * @return mixed The gateways array, false otherwise
	 */
	public function isPaypalGatewayInstalled() {
		if ( ! class_exists( 'WC' ) ) {
			return false;
		}

		$gateways = WC()->payment_gateways()->payment_gateways();
		if ( array_key_exists( 'ppcp-gateway', $gateways ) ) {
			return $gateways;
		}
		return false;
	}

	/**
	 * Return an array with the Look and Feel props.
	 */
	public function getLookAndFeelProps() {
		return [
			'canBeClosed' => true,
			'ajaxUrl'     => admin_url( 'admin-ajax.php' ),
			'autoLaunch'  => false,
		];
	}

	/**
	 * Return an array with the FTC props.
	 */
	public function getFtcProps() {
		$args = [
			'autoLaunch'  => false,
			'canBeClosed' => true,
		];

		if ( ! $this->storebuilder_ftc->isFtcComplete() ) {
			$args['canBeClosed'] = false;
		}

		return $args;
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
			'assets_url'       => PLUGIN_URL . '/nexcess-mapps/assets/',
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
			'wp101_api_key'    => $this->checkWp101ApiKey(),
		];
	}
}
