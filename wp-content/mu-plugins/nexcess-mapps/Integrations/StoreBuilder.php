<?php
/**
 * Dynamically generate a new store for customers.
 */

namespace Nexcess\MAPPS\Integrations;

use Nexcess\MAPPS\Concerns\CollectsTelemetryData;
use Nexcess\MAPPS\Concerns\HasAdminPages;
use Nexcess\MAPPS\Concerns\HasAssets;
use Nexcess\MAPPS\Concerns\HasHooks;
use Nexcess\MAPPS\Concerns\HasWordPressDependencies;
use Nexcess\MAPPS\Concerns\MakesHttpRequests;
use Nexcess\MAPPS\Concerns\QueriesWooCommerce;
use Nexcess\MAPPS\Modules\Telemetry;
use Nexcess\MAPPS\Services\Importers\WooCommerceProductImporter;
use Nexcess\MAPPS\Settings;
use Nexcess\MAPPS\Support\Helpers;

use StellarWP\PluginFramework\Exceptions\ContentOverwriteException;
use Tribe\WME\Sitebuilder\Container as SitebuilderContainer;
use Tribe\WME\Sitebuilder\Contracts\ManagesDomain;
use Tribe\WME\Sitebuilder\Modules\StoreDetails;
use Tribe\WME\Sitebuilder\Wizards\FirstTimeConfiguration;
use Tribe\WME\Sitebuilder\Wizards\LookAndFeel;
use Tribe\WME\Sitebuilder\Wizards\StoreSetup;

use const Nexcess\MAPPS\PLUGIN_DIR;
use const Nexcess\MAPPS\PLUGIN_URL;
use const Nexcess\MAPPS\PLUGIN_VERSION;

class StoreBuilder extends Integration {
	use CollectsTelemetryData;
	use HasAdminPages;
	use HasAssets;
	use HasHooks;
	use HasWordPressDependencies;
	use MakesHttpRequests;
	use QueriesWooCommerce;

	/**
	 * @var WooCommerceProductImporter
	 */
	protected $productImporter;

	/**
	 * @var Settings
	 */
	protected $settings;

	/**
	 * @var ManagesDomain
	 */
	private $domain_service;

	/**
	 * @var array[]
	 */
	protected $admin_pointers = [];

	/**
	 * The option that gets set once we've already ingested once.
	 */
	const INGESTION_LOCK_OPTION_NAME = '_storebuilder_created_on';

	/**
	 * The option name used for the Telemetry Data Store for wizard start and completion times.
	 */
	const TELEMETRY_DATA_STORE_NAME = 'nexcess_mapps_storebuilder_telemetry';

	/**
	 * The version of StoreBuilder for the current site. Used to determine if data migrations or other actions
	 * should be taken when the version changes.
	 */
	const CURRENT_STOREBUILDER_VERSION = 'nexcess_mapps_storebuilder_version';

	/**
	 * The version of StoreBuilder when the site was created.
	 */
	const INITIAL_STOREBUILDER_VERSION = 'nexcess_mapps_storebuilder_initial_version';

	/**
	 * The latest version of StoreBuilder defined using the Semantic Version format.
	 *
	 * @link https://semver.org/
	 */
	const VERSION = '4.1';

	/**
	 * @param Settings                   $settings
	 * @param WooCommerceProductImporter $product_importer
	 * @param ManagesDomain              $domain_service
	 */
	public function __construct(
		Settings $settings,
		WooCommerceProductImporter $product_importer,
		ManagesDomain $domain_service
	) {
		$this->settings        = $settings;
		$this->productImporter = $product_importer;
		$this->domain_service  = $domain_service;
	}

	/**
	 * Determine whether or not this integration should be loaded.
	 *
	 * @return bool Whether or not this integration be loaded in this environment.
	 */
	public function shouldLoadIntegration() {
		return ( $this->settings->is_storebuilder || get_option( self::INGESTION_LOCK_OPTION_NAME, false ) )
		&& $this->isPluginActive( 'woocommerce/woocommerce.php' )
		&& ( ! defined( 'NEXCESS_MAPPS_DISABLE_STOREBUILDER' ) || ! NEXCESS_MAPPS_DISABLE_STOREBUILDER );
	}

	/**
	 * Perform any necessary setup for the integration.
	 *
	 * This method is automatically called as part of Plugin::registerIntegration(), and is the
	 * entry-point for all integrations.
	 */
	public function setup() {
		$this->admin_pointers = [
			'toplevel_page_sitebuilder'               => [
				'slug'       => 'storebuilder-setup-store-pointer',
				'action'     => 'dismiss-storebuilder-setup-store-pointer',
				'target'     => 'toplevel_page_sitebuilder-store-details',
				'header'     => __( 'Get the most out of your store', 'nexcess-mapps' ),
				'text'       => __( 'Now that you’ve set up your site, move on to setting up your store.', 'nexcess-mapps' ),
				'conditions' => [
					SitebuilderContainer::getInstance()->get( FirstTimeConfiguration::class )->isComplete(),
					SitebuilderContainer::getInstance()->get( LookAndFeel::class )->isComplete(),
					! SitebuilderContainer::getInstance()->get( StoreSetup::class )->isComplete(),
				],
			],
			'toplevel_page_sitebuilder-store-details' => [
				'slug'       => 'storebuilder-site-setup-pointer',
				'action'     => 'dismiss-storebuilder-site-setup-pointer',
				'target'     => 'toplevel_page_sitebuilder',
				'header'     => __( 'Make a change?', 'nexcess-mapps' ),
				'text'       => esc_html__( 'Need to adjust how your store looks, or set up a domain? Go back to site setup.', 'nexcess-mapps' ),
				'conditions' => [
					SitebuilderContainer::getInstance()->get( StoreSetup::class )->isComplete(),
				],
			],
		];

		$this->addHooks();
		$this->removeDefaultHooks();

		SitebuilderContainer::getInstance()->extend( ManagesDomain::class, $this->domain_service );

		$this->loadPlugin( 'moderntribe/wme-sitebuilder/wme-sitebuilder.php' );
	}

	/**
	 * Retrieve all actions for the integration.
	 *
	 * @return array[]
	 */
	protected function getActions() {
		// phpcs:disable WordPress.Arrays
		return [
			[ 'init',                       [ $this, 'maybeUpgradeStoreBuilder'   ], 999 ],
			[ 'admin_init',                 [ $this, 'registerAdminColorScheme'   ] ],
			[ 'admin_init',                 [ $this, 'dismissPointer' ] ],
			[ 'in_admin_header',            [ $this, 'loadPointerScripts' ] ],
			[ 'user_register',              [ $this, 'setDefaultAdminColorScheme' ] ],
			[ 'admin_menu',                 [ $this, 'removeMenuPages'            ], 999 ],
			[ 'admin_notices',              [ $this, 'renderWelcomePanel'         ], 100 ],
			[ 'wp_dashboard_setup',         [ $this, 'registerWidgets'            ] ],
			[ 'plugins_loaded',             [ $this, 'filterSpotlightUpsells'     ] ],
			[ 'wme_event_wizard_started',   [ $this, 'captureWizardStarted'       ] ],
			[ 'wme_event_wizard_completed', [ $this, 'captureWizardCompleted'     ] ],
			[ 'wme_event_wizard_telemetry', [ $this, 'captureWizardEvents'        ], 10, 3 ],
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
		$filters = [
			// Telemetry Report Data Collection.
			[ Telemetry::REPORT_DATA_FILTER, [ $this, 'collectStoreBuilderSetupData' ] ],
			[ Telemetry::REPORT_DATA_FILTER, [ $this, 'collectWooCommerceSetupData' ] ],

			// Filter Kadence configuration and the kadence license.
			[ 'kadence_theme_options_defaults', [ $this, 'setKadenceDefaults' ] ],
			[ 'pre_option_kt_api_manager_kadence_gutenberg_pro_data', [ $this, 'filterKadenceLicense' ] ],

			// Filters for metaboxes.
			[ 'postbox_classes_dashboard_mapps-storebuilder-support', [ $this, 'filterMetaboxClasses' ] ],
			[ 'default_hidden_meta_boxes',                            [ $this, 'filterMetaboxDefaults' ], 199 ],
			[ 'get_user_option_meta-box-order_dashboard',             [ $this, 'filterMetaboxOrder' ] ],

			// Set up the simple admin menu.
			[ 'pre_option__nexcess_simple_admin_menu', [ $this, 'setUpSimpleAdminMenu' ] ],
			[ 'Nexcess\MAPPS\SimpleAdminMenu\ShowWelcomeNotice', '__return_false' ],

			// Filter the admin bar environment colors.
			[ 'where_env_styles', [ $this, 'filterEnvironmentColors' ] ],

			// Sitebuilder Configuration.
			[ 'wme_sitebuilder_image_asset_path', [ $this, 'filterSitebuilderImageAssetPath' ] ],
			[ 'wme_sitebuilder_autolaunch_wizard', [ $this, 'filterAutoLaunchWizard' ] ],
			[ 'wme_sitebuilder_next_url', [ $this, 'filterSitebuilderNextUrl' ], 10, 3 ],
			[ 'wme_sitebuilder_golive_purchase_url', [ $this, 'filterSitebuilderPurchaseUrl' ] ],

		];
		// phpcs:enable WordPress.Arrays

		// Add the filters. They're broken out for readability.
		return array_merge( $filters, $this->getWCFilters() );
	}

	/**
	 * Remove hooks.
	 */
	protected function removeDefaultHooks() {
		// Remove the default WordPress welcome panel.
		remove_action( 'welcome_panel', 'wp_welcome_panel' );

		// Remove WooCommerce tracking for the site admin.
		if ( class_exists( 'WC_Site_Tracking' ) ) {
			remove_action( 'init', [ 'WC_Site_Tracking', 'init' ] );
		}
	}

	/**
	 * Modify WooCommerce.
	 *
	 * @return array The filters.
	 */
	protected function getWCFilters() {
		// phpcs:disable WordPress.Arrays
		$filters = [
			// Filter different parts of WooCommerce.
			[ 'woocommerce_admin_features',           [ $this, 'filterWCFeatures' ], PHP_INT_MAX ],
			[ 'woocommerce_admin_get_feature_config', [ $this, 'filterWCFeaturesConfig' ], PHP_INT_MAX ],

			// Diable tracking and upsells.
			[ 'pre_option_woocommerce_allow_tracking',               [ Helpers::class, 'returnNo' ], PHP_INT_MAX ],
			[ 'pre_option_woocommerce_merchant_email_notifications', [ Helpers::class, 'returnNo' ] ],
			[ 'pre_option_woocommerce_show_marketplace_suggestions', [ Helpers::class, 'returnNo' ] ],

			// Disable more tracking and upsells.
			[ 'woocommerce_allow_payment_recommendations', '__return_false' ],
			[ 'woocommerce_allow_marketplace_suggestions', '__return_false' ],
			[ 'woocommerce_apply_tracking',                '__return_false' ],
			[ 'woocommerce_apply_user_tracking',           '__return_false' ],
			[ 'woocommerce_show_addons_page',              '__return_false' ],
			[ 'woocommerce_admin_onboarding_themes', '__return_empty_array' ],
		];
		// phpcs:enable WordPress.Arrays

		if ( class_exists( 'WC_Site_Tracking' ) ) {
			// Remove user tracking in wp-admin.
			$filters = array_merge( $filters, [ [ 'admin_footer', [ 'WC_Site_Tracking', 'add_tracking_function' ], 24 ] ] );
		}

		return $filters;
	}

	/**
	 * Filter the admin bar colors.
	 *
	 * @param array $envs Environment settings.
	 *
	 * @return array Environment settings.
	 */
	public function filterEnvironmentColors( array $envs ) {
		$envs['production']['color'] = '#0073aa';

		return $envs;
	}

	/**
	 * Dynamically hide the license information for the Kadence settings page.
	 *
	 * @param null $option The option.
	 *
	 * @return mixed Filtered array or passed in option.
	 */
	public function filterKadenceLicense( $option ) {
		// If we're on license activation page, then don't show the actual license.
		if ( isset( $_GET['page'] ) && ( 'kadence' === $_GET['page'] || 'kadence_plugin_activation' === $_GET['page'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			return [
				'api_key'   => '******',
				'api_email' => '******',
			];
		}

		return $option;
	}

	/**
	 * Add .mapps-storebuilder-widget to our custom widgets.
	 *
	 * @param array $classes Classes to be applied to the post boxes.
	 *
	 * @return array The filtered $classes array.
	 */
	public function filterMetaboxClasses( array $classes ) {
		$classes[] = 'mapps-storebuilder-widget';

		return $classes;
	}

	/**
	 * Filter the default dashboard meta boxes shown to users.
	 *
	 * Once a user shows or hides a meta box, their selections are saved and will be used for
	 * subsequent page loads.
	 *
	 * @param array $meta_boxes An array of meta box keys that should be hidden by default.
	 *
	 * @return array The filtered $meta_boxes array.
	 */
	public function filterMetaboxDefaults( array $meta_boxes ) {
		return array_unique( array_merge( $meta_boxes, [
			'dashboard_activity',
			'dashboard_primary',
			'dashboard_rediscache',
			'dashboard_quick_press',
			'dashboard_right_now',
			'dashboard_site_health',
			'woocommerce_dashboard_recent_reviews',
			'wc_admin_dashboard_setup',
		] ) );
	}

	/**
	 * Filter the default ordering of dashboard meta boxes unless the user has set their own.
	 *
	 * @param array $order A user-defined ordering, in the form of "column_name:id1,id2".
	 *
	 * @return string[] Either the $order array or an array of reasonable defaults if $order is empty.
	 */
	public function filterMetaboxOrder( $order ) {
		if ( ! empty( $order ) ) {
			return $order;
		}

		return [
			'normal'  => 'mapps-storebuilder-advanced-steps,mapps-storebuilder-support',
			'column3' => 'woocommerce_dashboard_status',
		];
	}

	/**
	 * Modify some of the freemius opt-in and upselling.
	 */
	public function filterSpotlightUpsells() {
		// phpcs:disable WordPress.NamingConventions.ValidVariableName
		global  $sliFreemius;

		if ( ! isset( $sliFreemius ) ) {
			return;
		}

		$sliFreemius->add_filter( 'is_extensions_tracking_allowed', '__return_false' );
		$sliFreemius->add_filter( 'redirect_on_activation', '__return_false' );
		$sliFreemius->add_filter( 'show_admin_notice', '__return_false' );
		$sliFreemius->add_filter( 'show_customizer_upsell', '__return_false' );
		$sliFreemius->add_filter( 'show_deactivation_feedback_form', '__return_false' );
		$sliFreemius->add_filter( 'show_trial', '__return_false' );
		// phpcs:enable WordPress.NamingConventions.ValidVariableName
	}

	/**
	 * Determine if the criteria are met to AutoLaunch the wizard.
	 *
	 * @param bool $auto_launch
	 *
	 * @return bool
	 */
	public function filterAutoLaunchWizard( $auto_launch ) {
		// Only launch the First Time Configuration Wizard if this store doesn't have any orders.
		return ! $this->storeHasOrders();
	}

	/**
	 * Process and return the asset path for Sitebuilder/Store Details Images.
	 *
	 * @param string $asset_path
	 *
	 * @return string Sitebuilder Asset Path
	 */
	public function filterSitebuilderImageAssetPath( $asset_path ) {
		return PLUGIN_URL . '/nexcess-mapps/assets/';
	}

	/**
	 * Return the URL for use in the sidebar of final wizard screens.
	 *
	 * @param null|string $url
	 * @param string      $admin_page_slug
	 * @param string      $wizard_slug
	 *
	 * @return null|string
	 */
	public function filterSitebuilderNextUrl( $url, $admin_page_slug, $wizard_slug ) {
		if ( 'sitebuilder' === $admin_page_slug && 'look-and-feel' === $wizard_slug ) {
			return SitebuilderContainer::getInstance()->get( StoreDetails::class )->getPageUrl();
		}

		return $url;
	}

	/**
	 * Return the URL for sending the user to purchase selected domains.
	 *
	 * @param string $url
	 *
	 * @return string
	 */
	public function filterSitebuilderPurchaseUrl( $url ) {
		$domain = 'my.nexcess.net';

		if ( $this->settings->is_qa_environment ) {
			$domain = 'my.qa.nxswd.net';
		}

		return sprintf( 'https://%s/external/login?id={UUID}&theme=storebuilder', $domain );
	}

	/**
	 * Get the link for the 'Design Invoices' link.
	 *
	 * @return string Link url.
	 */
	public static function getDesignInvoicesLink() {
		// If the Invoices plugin is active, link to that.
		if ( is_plugin_active( 'woocommerce-pdf-invoices-packing-slips/woocommerce-pdf-invoices-packing-slips.php' ) ) {
			return admin_url( 'admin.php?page=wpo_wcpdf_options_page' );
		}

		return admin_url( 'admin.php?page=wc-settings&tab=email&section=wc_email_customer_invoice' );
	}

	/**
	 * Get the URL for the "Page performance" link.
	 *
	 * @return string The link for page speed.
	 */
	public static function getPageSpeedLink() {
		// If the performance monitor integration loaded, then we can point to that page.
		if ( did_action( 'Nexcess\MAPPS\Plugin\Loaded\Nexcess\MAPPS\Integrations\PerformanceMonitor' ) ) {
			return admin_url( 'admin.php?page=nexcess-mapps#priority-pages' );
		}

		// Fall back to the page cache.
		return admin_url( 'admin.php?page=mapps-page-cache' );
	}

	/**
	 * Modify feature flags of WooCommerce.
	 *
	 * @param array $features WC Features.
	 *
	 * @return array Features.
	 */
	public function filterWCFeatures( $features ) {
		unset(
			$features['marketing'],
			$features['mobile-app-banner'],
			$features['onboarding'],
			$features['payment-gateway-suggestions'],
			$features['remote-extensions-list'],
			$features['remote-inbox-notifications']
		);

		return $features;
	}

	/**
	 * Modify feature flags of WooCommerce.
	 *
	 * @param array $features WC Features.
	 *
	 * @return array Features.
	 */
	public function filterWCFeaturesConfig( $features ) {
		$features['mobile-app-banner']           = false;
		$features['payment-gateway-suggestions'] = false;
		$features['remote-extensions-list']      = false;
		$features['remote-inbox-notifications']  = false;

		return $features;
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
	 * Modify menu pages in the admin.
	 */
	public function removeMenuPages() {
		remove_submenu_page( 'wp101', 'wp101-settings' );
		remove_submenu_page( 'wp101', 'wp101-addons' );
		remove_submenu_page( 'options-general.php', 'kadence_plugin_activation' );
	}

	/**
	 * Register the StoreBuilder widgets.
	 */
	public function registerWidgets() {
		// No need to pass any additional args, as we filter order of widgets anyway.
		wp_add_dashboard_widget(
			'mapps-storebuilder-support',
			_x( 'StoreBuilder Support', 'widget title', 'nexcess-mapps' ),
			function () {
				$this->renderTemplate( 'widgets/storebuilder-support' );
			}
		);

		wp_add_dashboard_widget(
			'mapps-storebuilder-advanced',
			_x( 'Advanced Actions & Tools', 'widget title', 'nexcess-mapps' ),
			[ $this, 'renderAdvancedActionsTools' ]
		);
	}

	/**
	 * Display the "Advanced Actions & Tools" widget content.
	 */
	public function renderAdvancedActionsTools() {
		$this->renderTemplate( 'icon-link-list', [
			'before'     => '<div class="mapps-col">' . sprintf( '<h3>%1$s</h3>', esc_html__( 'For your store', 'nexcess-mapps' ) ),
			'after'      => '</div>',
			'list_class' => 'mapps-icon-link-list--mini',
			'icon_links' => [
				[
					'icon' => 'dashicons-chart-area',
					'href' => admin_url( 'admin.php?page=wc-admin&path=/analytics/overview' ),
					'text' => __( 'Store Analytics', 'nexcess-mapps' ),
				],
				[
					'icon' => 'dashicons-tickets-alt',
					'href' => admin_url( 'edit.php?post_type=shop_coupon' ),
					'text' => __( 'Create Coupons', 'nexcess-mapps' ),
				],
				[
					'icon' => 'dashicons-email-alt',
					'href' => admin_url( 'admin.php?page=wc-settings&tab=email' ),
					'text' => __( 'Configure store emails', 'nexcess-mapps' ),
				],
				[
					'icon' => 'dashicons-text-page',
					'href' => self::getDesignInvoicesLink(),
					'text' => __( 'Design invoices', 'nexcess-mapps' ),
				],
			],
		] );

		$this->renderTemplate( 'icon-link-list', [
			'before'     => '<div class="mapps-col">' . sprintf( '<h3>%1$s</h3>', esc_html__( 'For your site', 'nexcess-mapps' ) ),
			'after'      => '</div>',
			'list_class' => 'mapps-icon-link-list--mini',
			'icon_links' => [
				[
					'icon' => 'dashicons-groups',
					'href' => admin_url( 'users.php' ),
					'text' => __( 'Create logins for your team', 'nexcess-mapps' ),
				],
				[
					'icon' => 'dashicons-performance',
					'href' => self::getPageSpeedLink(),
					'text' => __( 'Monitor your page speed', 'nexcess-mapps' ),
				],
			],
		] );
	}

	/**
	 * Render the welcome panel on the WP Admin dashboard.
	 */
	public function renderWelcomePanel() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		// Only want to show on the main dashboard page.
		$screen = get_current_screen();

		if ( isset( $screen->id ) && 'dashboard' === $screen->id ) {
			$this->renderTemplate( 'widgets/storebuilder-welcome' );
		}
	}

	/**
	 * Set up the simple admin menu.
	 *
	 * @param array $option Option value.
	 *
	 * @return array Option value.
	 */
	public function setUpSimpleAdminMenu( $option ) {
		// phpcs:disable WordPress.Arrays
		$option['menuSections'] = Helpers::makeSimpleAdminMenu( [
			'dashboard',
			'__nexcess-mapps',
			'__sitebuilder',
			'__sitebuilder-store-details',
			[ __( 'Content', 'nexcess-mapps' ), 'admin-page', [
				'posts',
				'media',
				'pages'
			] ],
			[ __( 'Store', 'nexcess-mapps' ), 'cart', [
				'posts-product',
				[ '__woo-better-reviews', _x( 'Reviews', 'Dashboard sidebar menu', 'nexcess-mapps' ),  'admin-comments' ],
				'__wc-admin&path=/analytics/overview',
				[ '__woocommerce',        _x( 'Settings', 'Dashboard sidebar menu', 'nexcess-mapps' ), 'admin-generic' ],
			] ],
			[ __( 'Site', 'nexcess-mapps' ), 'cover-image', [
				'appearance',
				'plugins',
				'users',
			] ],
			'__wp101',
		] );
		// phpcs:enable WordPress.Arrays

		return $option;
	}

	/**
	 * Loads the scripts required for the pointer to show on the Admin
	 * Dashboard. The should_load_integration() method loads too early
	 * in the life cylce to test if we are on the dashboard page so we
	 * are doing it before enqueing and displaying the scripts.
	 */
	public function loadPointerScripts() {
		$screen = get_current_screen();

		if ( ! isset( $screen ) || ! key_exists( $screen->id, $this->admin_pointers ) || ! current_user_can( 'administrator' ) ) {
			return;
		}

		if ( in_array( false, $this->admin_pointers[ $screen->id ]['conditions'], true ) ) {
			return;
		}

		wp_enqueue_script( 'jquery' );
		wp_enqueue_style( 'wp-pointer' );
		wp_enqueue_script( 'wp-pointer' );

		if ( ! get_user_meta( get_current_user_id(), $this->admin_pointers[ $screen->id ]['action'], true ) ) {
			$pointer_content = sprintf(
				'<h3>%s</h3><p>%s</p>',
				esc_html( $this->admin_pointers[ $screen->id ]['header'] ),
				esc_html( $this->admin_pointers[ $screen->id ]['text'] )
			);
			?>

			<script type="text/javascript">
				//<![CDATA[
				jQuery( function () {
					jQuery( '#adminmenu #<?php echo esc_attr( $this->admin_pointers[ $screen->id ]['target'] ); ?>' ).pointer( {
						content: '<?php echo wp_kses_post( $pointer_content ); ?>',
						position: {
							edge: 'left',
							align: 'center'
						},
						show: function( event, t ){
							t.pointer.css( {
								position: 'fixed'
							} );
						},
						close: function() {
							jQuery.post( ajaxurl, {
								screen: '<?php echo esc_js( $screen->id ); ?>',
								pointer: '<?php echo esc_js( $this->admin_pointers[ $screen->id ]['slug'] ); ?>',
								action: '<?php echo esc_js( $this->admin_pointers[ $screen->id ]['action'] ); ?>',
								_nonce: '<?php echo esc_js( wp_create_nonce( $this->admin_pointers[ $screen->id ]['slug'] ) ); ?>'
							});
						}
					} ).pointer( 'open' );
				} );
				//]]>
			</script>
			<?php
		}
	}

	/**
	 * Catches the AJAX request to dismiss the pointer and sets the user meta
	 * to not show the pointer again.
	 */
	public function dismissPointer() {
		if ( ! isset( $_REQUEST['screen'] ) || ! isset( $this->admin_pointers[ $_REQUEST['screen'] ] ) || ! isset( $_REQUEST['action'] ) || ! isset( $_REQUEST['_nonce'] ) ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$pointer = $this->admin_pointers[ $_REQUEST['screen'] ];

		if ( ! wp_verify_nonce( $_REQUEST['_nonce'], $pointer['slug'] ) ) {
			wp_send_json_error( __( 'Invalid nonce.', 'nexcess-mapps' ) );
		}

		if ( $pointer['action'] === $_REQUEST['action'] ) {
			update_user_meta( get_current_user_id(), $pointer['action'], true, true );
			wp_send_json_success();
		}

		wp_send_json_error( __( 'Invalid screen and action combination.', 'nexcess-mapps' ) );
	}

	/**
	 * Retrieve content from the app and ingest it into WordPress.
	 *
	 * @param bool $force Optional. Whether or not to run the ingestion regardless of
	 *                    mayIngestContent(). Default is false.
	 *
	 * @throws ContentOverwriteException If ingesting content would cause content to be overwritten.
	 */
	public function ingestContent( $force = false ) {
		if ( ! $this->mayIngestContent() && ! $force ) {
			throw new ContentOverwriteException(
				__( 'StoreBuilder layouts have already been imported for this store, abandoning in order to prevent overwriting content.', 'nexcess-mapps' )
			);
		}

		$this->productImporter->importFromLocalFile( PLUGIN_DIR . 'assets/snippets/storebuilder-demo-products.csv' );

		// Fire off an action to allow for other integrations to easily hook in.
		do_action( 'Nexcess\MAPPS\StoreBuilder\IngestContent' );

		// Prevent the StoreBuilder from being run again.
		update_option( self::INGESTION_LOCK_OPTION_NAME, [
			'mapps_version' => PLUGIN_VERSION,
			'timestamp'     => time(),
		] );
	}

	/**
	 * Determine if the store is eligible to ingest content.
	 *
	 * @return bool True if the store is allowed to ingest content, false otherwise.
	 */
	public function mayIngestContent() {
		return ! get_option( self::INGESTION_LOCK_OPTION_NAME, false )
			&& ! $this->storeHasOrders();
	}

	/**
	 * Overwrite some default settings for Kadence.
	 *
	 * @param mixed[] $options Default theme options for Kadence.
	 *
	 * @return mixed[] The filtered $options array.
	 */
	public function setKadenceDefaults( $options ) {
		// Overwrite the default Kadence footer.
		$options['footer_html_content'] = sprintf(
			'{copyright} {year} {site-title} — %s',
			_x( 'StoreBuilder by Nexcess', 'StoreBuilder theme footer', 'nexcess-mapps' )
		);

		// Use un-boxed styles by default.
		$options['page_content_style'] = 'unboxed';

		// Set default header assignments.
		if ( ! isset( $options['header_desktop_items']['main'] ) ) {
			$options['header_desktop_items']['main'] = [];
		}

		$options['header_desktop_items']['main'] = array_merge( $options['header_desktop_items']['main'], [
			'main_left'  => [
				'logo',
			],
			'main_right' => [
				'navigation',
			],
		] );

		return $options;
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
	 * @param array $report
	 *
	 * @return array
	 */
	public function collectStoreBuilderSetupData( $report = [] ) {
		$report['setup']['storebuilder']['versions'] = [
			'initial' => get_option( self::INITIAL_STOREBUILDER_VERSION ),
			'current' => get_option( self::CURRENT_STOREBUILDER_VERSION ),
		];

		// Limiting this to just the wizards for now until we have a reason to
		// capture dynamic data points.
		if ( ! empty( $this->getTelemetryData()->get( 'wizards' ) ) ) {
			$report['setup']['storebuilder']['wizards'] = $this->getTelemetryData()->get( 'wizards' );
		}

		return $report;
	}

	/**
	 * Add WooCommerce telemetry data to telemetry report.
	 *
	 * @param array $report
	 *
	 * @return array
	 */
	public function collectWooCommerceSetupData( $report = [] ) {
		// We only load StoreBuilder if WooCommerce is installed, so grabbing the option with the namespace/constant should be safe.
		$wc_profile = get_option( \Automattic\WooCommerce\Internal\Admin\Onboarding\OnboardingProfile::DATA_OPTION, [ 'product_types' => [] ] );

		if ( array_key_exists( 'product_types', $wc_profile ) ) {
			$product_types = $wc_profile['product_types'];
		} else {
			$store_setup   = SitebuilderContainer::getInstance()->get( StoreSetup::class );
			$product_types = $store_setup->getProductTypes();
		}

		if ( ! empty( $product_types ) ) {
			// Use the setup section of the reports since this shouldn't be stored with each daily report.
			$report['setup']['woocommerce']['product_types'] = $product_types;
		}

		return $report;
	}

	/**
	 * Check if we're updating to a new version of StoreBuilder and run upgrades as necessary.
	 */
	public function maybeUpgradeStoreBuilder() {
		// No data migrations should be performed during the initial StoreBuilder setup.
		if ( defined( 'STOREBUILDER_SETUP' ) && STOREBUILDER_SETUP ) {
			return;
		}

		// If we don't have any StoreBuilder or SiteBuilder data stored we don't have anything to migrate.
		$storebuilder_ftc = get_option( '_storebuilder_ftc' );
		$sitebuilder_ftc  = get_option( '_sitebuilder_ftc' );

		if ( false === $storebuilder_ftc && false === $sitebuilder_ftc ) {
			return;
		}

		// @todo: Retrieve the initial StoreBuilder version without attempting to clean after v1.34.0 is
		// released and the Temporary Data Migration section is removed.
		$initial_storebuilder_version = get_option( self::INITIAL_STOREBUILDER_VERSION );
		if ( false === $initial_storebuilder_version ) {
			$initial_storebuilder_version = $this->v4_migrateSiteBuilderVersion();
		}
		$current_storebuilder_version = get_option( self::CURRENT_STOREBUILDER_VERSION, $initial_storebuilder_version );

		// With all the migrations completed, update the site's current StoreBuilder version.
		if ( version_compare( $current_storebuilder_version, self::VERSION, '<' ) ) {
			if ( version_compare( $current_storebuilder_version, '4.0', '<' ) ) {
				// Version 3.0 was the first tracked implementation of StoreBuilder that may require migrations.
				// Migrate all data to the expected format of StoreBuilder 4.0.
				$this->v4_migrateSiteBuilderFTCData();
				$this->v4_migrateSiteBuilderLookAndFeelData();
				$this->v4_migrateSiteBuilderGoLiveData();
			}

			if ( version_compare( $current_storebuilder_version, '4.1', '<' ) ) {
				// A new rewrite rule was introduced in Sitebuilder v0.4.0 which matches up to StoreBuilder v4.1.
				flush_rewrite_rules();
			}

			update_option( self::CURRENT_STOREBUILDER_VERSION, self::VERSION, true );
		}
	}

	/**
	 * Migrate StoreBuilder Version from the existing v2/v3 option to one which follows
	 * the `nexcess_mapps_` naming scheme.
	 *
	 * @param string $initial_storebuilder_version
	 *
	 * @return string
	 */
	protected function v4_migrateSiteBuilderVersion( $initial_storebuilder_version = '' ) {
		$storebuilder_version = get_option( 'storebuilder_version' );
		if ( false !== $storebuilder_version ) {
			$initial_storebuilder_version = (string) $storebuilder_version;
			if ( update_option( self::INITIAL_STOREBUILDER_VERSION, $storebuilder_version, true ) ) {
				delete_option( 'storebuilder_version' );
			}
		} elseif ( empty( $initial_storebuilder_version ) ) {
			// If no initial StoreBuilder version is set, then we're dealing with one of the earliest
			// sites (StoreBuilder v2.0) that did not store a tracked version in the options table.
			$initial_storebuilder_version = '2.0';
			update_option( self::INITIAL_STOREBUILDER_VERSION, $initial_storebuilder_version, true );
		}

		return $initial_storebuilder_version;
	}

	/**
	 * Migrate StoreBuilder FTC data to the sitebuilder_ftc and sitebuilder_store_details options.
	 */
	protected function v4_migrateSiteBuilderFTCData() {
		$_storebuilder_ftc = get_option( '_storebuilder_ftc' );

		if ( false !== $_storebuilder_ftc && is_array( $_storebuilder_ftc ) ) {
			$_sitebuilder_store_setup = [];

			if ( isset( $_storebuilder_ftc['producttype'] ) ) {
				$_sitebuilder_store_setup['producttype'] = $_storebuilder_ftc['producttype'];
				unset( $_storebuilder_ftc['producttype'] );
			}

			if ( isset( $_storebuilder_ftc['productcount'] ) ) {
				$_sitebuilder_store_setup['productcount'] = $_storebuilder_ftc['productcount'];
				unset( $_storebuilder_ftc['productcount'] );
			}

			if ( isset( $_storebuilder_ftc['ftc_complete'] ) ) {
				$_sitebuilder_store_setup['complete'] = $_storebuilder_ftc['ftc_complete'];
				$_storebuilder_ftc['complete']        = $_storebuilder_ftc['ftc_complete'];
				unset( $_storebuilder_ftc['ftc_complete'] );
			} else {
				$_sitebuilder_store_setup['complete'] = false;
				$_storebuilder_ftc['complete']        = false;
			}

			$_ftc_updated = update_option( '_sitebuilder_ftc', $_storebuilder_ftc, false );
			$_store_setup = update_option( '_sitebuilder_store_setup', $_sitebuilder_store_setup, false );

			if ( $_ftc_updated && $_store_setup ) {
				delete_option( '_storebuilder_ftc' );
			}
		} else {
			$_sitebuilder_store_setup = [
				'producttype'  => '',
				'productcount' => '',
				'complete'     => false,
			];
			update_option( '_sitebuilder_store_setup', $_sitebuilder_store_setup, false );
		}
	}

	/**
	 * Migrate the StoreBuilder Look And Feel Data to the SiteBuilder Look And Feel data option.
	 */
	protected function v4_migrateSiteBuilderLookAndFeelData() {
		$_storebuilder_look_and_feel = get_option( '_storebuilder_look_and_feel' );

		if ( false !== $_storebuilder_look_and_feel ) {
			$_storebuilder_look_and_feel['complete'] = count( $_storebuilder_look_and_feel ) > 0;
			if ( update_option( '_sitebuilder_look_and_feel', $_storebuilder_look_and_feel, false ) ) {
				delete_option( '_storebuilder_look_and_feel' );
			}
		}
	}

	/**
	 * Migrate the StoreBuilder GoLive Data to the SiteBuilder GoLive data option.
	 */
	protected function v4_migrateSiteBuilderGoLiveData() {
		$_storebuilder_go_live          = get_option( '_storebuilder_go_live' );
		$_storebuilder_verifying_domain = get_option( '_storebuilder_verifying_domain' );

		$_sitebuilder_go_live = [
			'complete' => $_storebuilder_go_live,
		];

		if ( false !== $_storebuilder_verifying_domain ) {
			$_sitebuilder_go_live['verifying_domain'] = $_storebuilder_verifying_domain;
		}

		if ( update_option( '_sitebuilder_go_live', $_sitebuilder_go_live, false ) ) {
			delete_option( '_storebuilder_go_live' );

			if ( false !== $_storebuilder_verifying_domain ) {
				delete_option( '_storebuilder_verifying_domain' );
			}
		}
	}
}
