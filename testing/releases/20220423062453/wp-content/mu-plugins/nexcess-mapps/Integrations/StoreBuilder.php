<?php
/**
 * Dynamically generate a new store for customers.
 */

namespace Nexcess\MAPPS\Integrations;

use Nexcess\MAPPS\Concerns\HasAdminPages;
use Nexcess\MAPPS\Concerns\HasAssets;
use Nexcess\MAPPS\Concerns\HasHooks;
use Nexcess\MAPPS\Concerns\HasWordPressDependencies;
use Nexcess\MAPPS\Concerns\MakesHttpRequests;
use Nexcess\MAPPS\Concerns\ManagesGroupedOptions;
use Nexcess\MAPPS\Concerns\QueriesWooCommerce;
use Nexcess\MAPPS\Exceptions\ContentOverwriteException;
use Nexcess\MAPPS\Services\AdminBar;
use Nexcess\MAPPS\Services\Importers\AttachmentImporter;
use Nexcess\MAPPS\Services\Importers\WooCommerceProductImporter;
use Nexcess\MAPPS\Services\Options;
use Nexcess\MAPPS\Settings;
use Nexcess\MAPPS\Support\Helpers;

use const Nexcess\MAPPS\PLUGIN_DIR;
use const Nexcess\MAPPS\PLUGIN_VERSION;

class StoreBuilder extends Integration {
	use ManagesGroupedOptions;
	use HasAdminPages;
	use HasAssets;
	use HasHooks;
	use HasWordPressDependencies;
	use MakesHttpRequests;
	use QueriesWooCommerce;

	/**
	 * @var mixed[]
	 */
	protected $contentPlaceholders = [];

	/**
	 * @var \Nexcess\MAPPS\Services\AdminBar
	 */
	protected $adminBar;

	/**
	 * @var \Nexcess\MAPPS\Services\Importers\AttachmentImporter
	 */
	protected $attachmentImporter;

	/**
	 * @var \Nexcess\MAPPS\Services\Importers\WooCommerceProductImporter
	 */
	protected $productImporter;

	/**
	 * @var \Nexcess\MAPPS\Settings
	 */
	protected $settings;

	/**
	 * @var \Nexcess\MAPPS\Services\Options
	 */
	protected $options;

	/**
	 * The option that gets set once we've already ingested once.
	 */
	const INGESTION_LOCK_OPTION_NAME = '_storebuilder_created_on';

	/**
	 * The post meta key that gets set on generated content.
	 */
	const GENERATED_AT_POST_META_KEY = '_storebuilder_generated_at';

	/**
	 * The grouped setting option name.
	 */
	const OPTION_NAME = '_nexcess_quickstart';

	/**
	 * @param \Nexcess\MAPPS\Settings                                      $settings
	 * @param \Nexcess\MAPPS\Services\Importers\AttachmentImporter         $attachment_importer
	 * @param \Nexcess\MAPPS\Services\Importers\WooCommerceProductImporter $product_importer
	 * @param \Nexcess\MAPPS\Services\AdminBar                             $admin_bar
	 * @param \Nexcess\MAPPS\Services\Options                              $options
	 */
	public function __construct(
		Settings $settings,
		AttachmentImporter $attachment_importer,
		WooCommerceProductImporter $product_importer,
		AdminBar $admin_bar,
		Options $options
	) {
		$this->settings           = $settings;
		$this->attachmentImporter = $attachment_importer;
		$this->productImporter    = $product_importer;
		$this->adminBar           = $admin_bar;
		$this->options            = $options;
	}

	/**
	 * Determine whether or not this integration should be loaded.
	 *
	 * @return bool Whether or not this integration be loaded in this environment.
	 */
	public function shouldLoadIntegration() {
		return ( $this->settings->is_storebuilder || get_option( self::INGESTION_LOCK_OPTION_NAME, false ) ) && $this->isPluginActive( 'woocommerce/woocommerce.php' );
	}

	/**
	 * Perform any necessary setup for the integration.
	 *
	 * This method is automatically called as part of Plugin::registerIntegration(), and is the
	 * entry-point for all integrations.
	 */
	public function setup() {
		$this->addHooks();
		$this->removeDefaultHooks();
	}

	/**
	 * Retrieve all actions for the integration.
	 *
	 * @return array[]
	 */
	protected function getActions() {
		// phpcs:disable WordPress.Arrays
		return [
			[ 'admin_enqueue_scripts', [ $this, 'enqueueScripts'                ] ],
			[ 'admin_menu',            [ $this, 'removeMenuPages'               ], 999 ],
			[ 'admin_notices',         [ $this, 'renderWelcomePanel'            ], 100 ],
			[ 'wp_dashboard_setup',    [ $this, 'registerWidgets'               ] ],
			[ 'plugins_loaded',        [ $this, 'filterSpotlightUpsells'        ] ],
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
			// Kadence configuration.
			[ 'kadence_theme_options_defaults', [ $this, 'setKadenceDefaults' ] ],

			// Filters for metaboxes.
			[ 'postbox_classes_dashboard_mapps-storebuilder-support', [ $this, 'filterMetaboxClasses' ] ],
			[ 'default_hidden_meta_boxes',                            [ $this, 'filterMetaboxDefaults' ], 199 ],
			[ 'get_user_option_meta-box-order_dashboard',             [ $this, 'filterMetaboxOrder' ] ],

			// Set up the simple admin menu.
			[ 'pre_option__nexcess_simple_admin_menu', [ $this, 'setUpSimpleAdminMenu' ] ],

			// Filter the admin bar environment colors.
			[ 'where_env_styles', [ $this, 'filterEnvironmentColors' ] ],

			// Add the launch content to the Go Live widget.
			[ 'Nexcess\MAPPS\DomainChange\After',  [ $this, 'renderLaunchContentAfter' ] ],

			[ 'Nexcess\MAPPS\SimpleAdminMenu\ShowWelcomeNotice', '__return_false' ],

			// Filter the kadence license.
			[ 'pre_option_kt_api_manager_kadence_gutenberg_pro_data', [ $this, 'filterKadenceLicense' ] ],
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
	 * Enqueue scripts.
	 */
	public function enqueueScripts() {
		$this->enqueueScript( 'nexcess-mapps-storebuilder', 'storebuilder.js' );
	}

	/**
	 * Add inline styles.
	 */
	public function addInlineStyles() {
		wp_add_inline_style(
			'nexcess-mapps-storebuilder',
			'.woocommerce-stats-overview__install-jetpack-promo {
				display: none !important;
				visibility: hidden !important;
			}

			.wp-submenu .fs-submenu-item.pricing.upgrade-mode {
				color: unset;
			}

			body.appearance_page_kadence .license-section {
				display: none;
			}'
		);
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
			'side'    => 'mapps-change-domain',
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
	 * Render content inside the launch widget.
	 */
	public function renderLaunchContentAfter() {
		$this->renderTemplate( 'widgets/storebuilder-launch-after' );
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
			'__storebuilderapp',
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
	 * Retrieve content from the app and ingest it into WordPress.
	 *
	 * @param bool $force Optional. Whether or not to run the ingestion regardless of
	 *                    mayIngestContent(). Default is false.
	 *
	 * @throws \Nexcess\MAPPS\Exceptions\ContentOverwriteException If ingesting content would cause
	 *                                                             content to be overwritten.
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
}
