<?php

/**
 * Integration for the WooCommerce Automated Testing system.
 */

namespace Nexcess\MAPPS\Integrations;

use Nexcess\MAPPS\Concerns\HasAdminPages;
use Nexcess\MAPPS\Concerns\HasCronEvents;
use Nexcess\MAPPS\Concerns\HasWordPressDependencies;
use Nexcess\MAPPS\Concerns\MakesHttpRequests;
use Nexcess\MAPPS\Concerns\ManagesGroupedOptions;
use Nexcess\MAPPS\Modules\Telemetry;
use Nexcess\MAPPS\Routes\WooCommerceAutomatedTestingRoute;
use Nexcess\MAPPS\Services\Managers\RouteManager;
use Nexcess\MAPPS\Services\Options;
use Nexcess\MAPPS\Settings;
use Nexcess\MAPPS\Support\CacheRemember;
use WC_Customer;
use WC_Data_Store;
use WC_Order;
use WC_Product;

class WooCommerceAutomatedTesting extends Integration {
	use HasAdminPages;
	use HasCronEvents;
	use HasWordPressDependencies;
	use MakesHttpRequests;
	use ManagesGroupedOptions;

	/**
	 * @var \Nexcess\MAPPS\Services\Managers\RouteManager
	 */
	protected $routeManager;

	/**
	 * @var \Nexcess\MAPPS\Settings
	 */
	protected $settings;

	/**
	 * @var \Nexcess\MAPPS\Services\Options
	 */
	protected $options;

	/**
	 * The test mode cookie name.
	 */
	const COOKIE_NAME = 'nexcess_mapps_wc_automated_testing';

	/**
	 * Cron hook for rotating the test user's credentials.
	 */
	const CREDENTIAL_ROTATION_HOOK = 'nexcess_mapps_wc_automated_testing_rotate_credentials';

	/**
	 * The key used in the wp_options table.
	 */
	const OPTION_NAME = 'nexcess_mapps_woocommerce_automated_testing';

	/**
	 * Cache key for recent results.
	 */
	const RESULTS_CACHE_KEY = 'nexcess_mapps_wc_automated_testing_results';

	/**
	 * The key used in the telemetry report which contains the relevant integration info.
	 */
	const TELEMETRY_FEATURE_KEY = 'woocommerce_automated_testing';

	/**
	 * @param \Nexcess\MAPPS\Settings                       $settings
	 * @param \Nexcess\MAPPS\Services\Managers\RouteManager $route_manager
	 * @param \Nexcess\MAPPS\Services\Options               $options
	 */
	public function __construct( Settings $settings, RouteManager $route_manager, Options $options ) {
		$this->settings     = $settings;
		$this->routeManager = $route_manager;
		$this->options      = $options;
	}

	/**
	 * Determine whether or not this site is eligible to run this integration.
	 *
	 * This has a lot of overlap with the purpose of (and is consumed by) shouldLoadIntegration(),
	 * but only covers whether or not this site _could_ be registered.
	 *
	 * @return bool True if the site is eligible for WCAT, false otherwise.
	 */
	public function eligibleForAutomatedTesting() {
		return $this->settings->is_production_site
			&& $this->isPluginActive( 'woocommerce/woocommerce.php' );
	}

	/**
	 * Determine whether or not this integration should be loaded.
	 *
	 * @return bool Whether or not this integration should be loaded in this environment.
	 */
	public function shouldLoadIntegration() {
		if ( function_exists( 'is_multisite' ) && is_multisite() ) {
			return false;
		}

		return $this->eligibleForAutomatedTesting()
			&& $this->registered();
	}

	/**
	 * Perform any necessary setup for the integration.
	 *
	 * This method is automatically called as part of Plugin::loadIntegration(), and is the
	 * entry-point for all integrations.
	 */
	public function setup() {
		$this->addHooks();
		$this->routeManager->addRoute( WooCommerceAutomatedTestingRoute::class );
		$this->registerOptionPageSetting();
	}

	/**
	 * Retrieve all actions for the integration.
	 *
	 * @return array[]
	 */
	protected function getActions() {
		return [
			[ 'admin_init', [ $this, 'registerAutomatedTestingSection' ], 200 ], // 200 = second functionality tab.
			[ 'init', [ $this, 'watchForAutomatedTestRequests' ] ],
			[ self::CREDENTIAL_ROTATION_HOOK, [ $this, 'rotateTestUserCredentials' ] ],
			[ Maintenance::WEEKLY_MAINTENANCE_CRON_ACTION, [ $this, 'updateSite' ] ],
			[ 'Nexcess\MAPPS\Options\Update', [ $this, 'enableOrDisable' ], 10, 3 ],
			[ 'wp_head', [ $this, 'noIndexForTestProduct' ] ],
			[ 'nexcess_mapps_wcat_enabled', [ $this, 'addTestProductAdminUser' ] ],
			[ 'nexcess_mapps_wcat_disabled', [ $this, 'deleteTestProductAdminUser' ] ],
		];
	}

	/**
	 * Retrieve all filters for the integration.
	 *
	 * @return array[]
	 */
	protected function getFilters() {
		return [
			[ Telemetry::REPORT_DATA_FILTER, [ $this, 'addFeatureToTelemetry' ] ],
			[ 'wpseo_exclude_from_sitemap_by_post_ids', [ $this, 'wpseoHideTestProductFromSitemap' ] ],
			[ 'wp_sitemaps_posts_query_args', [ $this, 'wphideTestProductFromSitemap' ] ],
			// This filter is here to catch the case where a customer might unpublish and republish the WCAT product.
			// We never want the WCAT test product to be publicized, ever.
			[ 'publicize_should_publicize_published_post', [ $this, 'hideTestProductFromJetPack' ], 10, 2 ],
		];
	}

	/**
	 * Retrieve details about the current site.
	 *
	 * This content is ingested by the WooCommerce Automated Testing platform just before
	 * executing its tests.
	 *
	 * @return mixed[]
	 */
	public function getSiteInfo() {
		$product = $this->getTestProduct();

		return [
			'credentials' => $this->getTestCredentials(),
			'testCookie'  => [
				'name'  => self::COOKIE_NAME,
				'value' => $this->getTestCookie(),
			],
			'urls'        => [
				'cart'      => get_permalink( get_option( 'woocommerce_cart_page_id' ) ),
				'checkout'  => get_permalink( get_option( 'woocommerce_checkout_page_id' ) ),
				'home'      => site_url( '/' ),
				'myAccount' => get_permalink( get_option( 'woocommerce_myaccount_page_id' ) ),
				'product'   => $product ? get_permalink( $product->get_id() ) : false,
				'shop'      => get_permalink( get_option( 'woocommerce_shop_page_id' ) ),
			],
		];
	}

	/**
	 * Register the "WooCommerce Automated Testing" settings section.
	 */
	public function registerAutomatedTestingSection() {
		if ( ! $this->getOption()->get( 'enable_wcat', true ) ) {
			return;
		}

		add_settings_section(
			'woocommerce-automated-testing',
			_x( 'WooCommerce Automated Testing', 'settings section', 'nexcess-mapps' ),
			function () {
				$this->renderTemplate( 'woocommerce-automated-testing', [
					'results' => $this->getRecentResults(),
				] );
			},
			Dashboard::ADMIN_MENU_SLUG
		);
	}

	/**
	 * Add a setting to enable/disable.
	 */
	public function registerOptionPageSetting() {
		$this->options->addOption(
			[ self::OPTION_NAME, 'enable_wcat' ],
			'checkbox',
			__( 'Enable WooCommerce Automated Testing', 'nexcess-mapps' ),
			[
				'description' => __( 'Automatically perform a series of tests to ensure the entire customer flow works correctly.', 'nexcess-mapps' ),
				'default'     => false,
			]
		);
	}

	/**
	 * When the settings are saved, update the WCAT remote api with our new status.
	 *
	 * @param string|array $key  The key of the option being saved.
	 * @param mixed        $new  New value.
	 * @param mixed        $prev Previous value, most likely true or false.
	 */
	public function enableOrDisable( $key, $new, $prev ) {
		// Only apply to our option.
		if ( ! $this->options->verifyOptionKey( $key, [ self::OPTION_NAME, 'enable_wcat' ] ) ) {
			return;
		}

		// If nothing changed, do nothing.
		if ( $prev === $new ) {
			return;
		}

		$this->forceEnableOrDisable( (bool) $new );
	}

	/**
	 * Force enable or disable the option.
	 *
	 * @param bool $state State to set the option to.
	 */
	public function forceEnableOrDisable( $state ) {
		$this->updateSite( [ 'is_active' => (bool) $state ] );
		$this->getOption()->set( 'enable_wcat', (bool) $state )->save();

		do_action( 'nexcess_mapps_wcat_' . ( $state ? 'enabled' : 'disabled' ) );
	}

	/**
	 * Check whether or not the site is currently registered with the SaaS.
	 *
	 * @return bool True if registered, false otherwise.
	 */
	public function registered() {
		return ! empty( $this->getOption()->get( 'api_key' ) );
	}

	/**
	 * Register the current site within the SaaS.
	 */
	public function registerSite() {
		if ( $this->registered() ) {
			return;
		}

		// Register the site, then decode the response containing the site ID + API key.
		$response = wp_remote_post( $this->settings->wc_automated_testing_url . '/api/sites', [
			'timeout' => 30,
			'headers' => [
				'Accept'            => 'application/json',
				'X-MAPPS-API-TOKEN' => $this->settings->managed_apps_token,
			],
			'body'    => [
				'url' => get_site_url(),
			],
		] );

		$body = json_decode( $this->validateHttpResponse( $response, 201 ) );

		// Store the results.
		$this->getOption()
			->set( 'api_key', $body->api_key ?: null )
			->set( 'site_id', $body->site_id ?: null )
			->set( 'enable_wcat', $body->api_key && $body->site_id )
			->save();

		do_action( 'nexcess_mapps_wcat_enabled' );
	}

	/**
	 * Rotate the credentials for the test customer.
	 *
	 * This method should be called some amount of time after credentials have been shared with the
	 * test runner.
	 */
	public function rotateTestUserCredentials() {
		add_filter( 'send_password_change_email', '__return_false', PHP_INT_MAX );

		$customer = $this->getTestCustomer();

		if ( ! $customer ) {
			$customer = $this->createTestCustomer();
		}

		$customer->set_password( wp_generate_password() );
		$customer->save();
	}

	/**
	 * Update site details within the WooCommerce Automated Testing platform.
	 *
	 * @param Array<string,mixed> $options {
	 *
	 *   Optional. Options that can be updated within the SaaS. Default is empty.
	 *
	 *   @type bool $is_active Whether or not to treat the site as active.
	 * }
	 */
	public function updateSite( array $options = [] ) {
		// Don't do anything with cron if the setting is disabled.
		if ( wp_doing_cron() && ! $this->getOption()->get( 'enable_wcat', false ) ) {
			return;
		}

		$url  = sprintf( '%s/api/sites/%s', $this->settings->wc_automated_testing_url, $this->getOption()->site_id );
		$body = [
			'url' => get_site_url(),
		];

		if ( isset( $options['is_active'] ) ) {
			$body['is_active'] = (bool) $options['is_active'];
		}

		// Register the site, then decode the response containing the site ID + API key.
		$response = wp_remote_request( $url, [
			'method'  => 'PATCH',
			'timeout' => 30,
			'headers' => [
				'Accept'        => 'application/json',
				'Authorization' => sprintf( 'Bearer %s', $this->getOption()->api_key ),
			],
			'body'    => $body,
		] );

		$this->validateHttpResponse( $response, 200 );
	}

	/**
	 * Watch incoming requests for those coming from the WooCommerce Automated Testing platform.
	 */
	public function watchForAutomatedTestRequests() {
		if ( ! empty( $_COOKIE[ self::COOKIE_NAME ] ) && $this->getTestCookie() === $_COOKIE[ self::COOKIE_NAME ] ) {
			$this->enableTestMode();
		}
	}

	/**
	 * Put the store into test mode if the request is coming from the Automated Testing platform.
	 */
	protected function enableTestMode() {
		// Short-circuit all WooCommerce emails.
		add_filter( 'woocommerce_mail_callback', function () {
			return '__return_null';
		}, PHP_INT_MAX );

		// Force Stripe into testing mode.
		add_filter( 'option_woocommerce_stripe_settings', function ( $option ) {
			if ( ! is_array( $option ) ) {
				$option = (array) $option;
			}

			$option['testmode'] = 'yes';

			return $option;
		}, PHP_INT_MAX );

		// Make the test product visible in the catalog.
		add_filter( 'woocommerce_product_is_visible', function ( $visible, $product_id ) {
			$product = $this->getTestProduct();

			if ( ! $product ) {
				return false;
			}

			return $product_id === $product->get_id() ? true : $visible;
		}, PHP_INT_MAX, 2 );

		// Force-delete orders made during test mode upon hitting the "thank you" screen.
		add_action( 'woocommerce_thankyou', function ( $order_id ) {
			$order = new WC_Order( $order_id );
			$order->delete( true );
		} );
	}

	/**
	 * Retrieve (and cache) recent results from the SaaS.
	 *
	 * @return array[] Recent results, grouped by test name.
	 */
	protected function getRecentResults() {
		return CacheRemember::remember_transient( self::RESULTS_CACHE_KEY, function () {
			$url = sprintf(
				'%s/api/sites/%s',
				$this->settings->wc_automated_testing_url,
				$this->getOption()->site_id
			);

			$results = wp_remote_get( $url, [
				'headers' => [
					'Accept'        => 'application/json',
					'Authorization' => sprintf( 'Bearer %s', $this->getOption()->api_key ),
				],
			] );

			$json = $this->validateHttpResponse( $results, 200 );
			$body = json_decode( $json, true );

			return isset( $body['data']['results'] ) ? $body['data']['results'] : null;
		}, HOUR_IN_SECONDS );
	}

	/**
	 * Generate a short-lived test cookie value.
	 *
	 * In order to prevent fraudulent tests, generate a cookie value that must be present in order
	 * to enable test mode, which will only remain valid for a short time.
	 */
	protected function getTestCookie() {
		return CacheRemember::remember_transient( 'nexcess_mapps_wc_automated_testing_cookie_value', function () {
			return wp_generate_password();
		}, 15 * MINUTE_IN_SECONDS );
	}

	/**
	 * Get credentials for the test user.
	 *
	 * It's important to note that every time this method is called the password for the test user
	 * will be reset. This is to prevent passwords from being stored in plain-text anywhere.
	 *
	 * @return string[] {
	 *
	 *   Credentials for the test user.
	 *
	 *   @type string $email    The test user's email address.
	 *   @type string $username The test user's username.
	 *   @type string $password The newly-generated password for the test user.
	 * }
	 */
	protected function getTestCredentials() {
		add_filter( 'send_password_change_email', '__return_false', PHP_INT_MAX );

		$password = wp_generate_password();
		$customer = $this->getTestCustomer();

		if ( ! $customer ) {
			$customer = $this->createTestCustomer();
		}

		$customer->set_password( $password );
		$customer->save();

		// Schedule the password to be rotated 15min from now.
		$this->registerCronEvent(
			self::CREDENTIAL_ROTATION_HOOK,
			null,
			current_datetime()->add( new \DateInterval( 'PT15M' ) )
		);

		return [
			'email'    => $customer->get_email(),
			'username' => $customer->get_username(),
			'password' => $password,
		];
	}

	/**
	 * Retrieve the test customer.
	 *
	 * If the test user does not yet exist, return false.
	 *
	 * @return WC_Customer|false
	 */
	protected function getTestCustomer() {
		$customer_id = $this->getOption()->customer_id;

		if ( $customer_id ) {
			$customer = new WC_Customer( $customer_id );

			// WC_Customer will return a new customer with an ID of 0 if
			// one could not be found with the given ID.
			if ( is_a( $customer, 'WC_Customer' ) && 0 !== $customer->get_id() ) {
				return $customer;
			}
		}

		return false;
	}

	/**
	 * Creates a new test customer if one does not exist. Avoids flooding the DB with test customers.
	 *
	 * @return WC_Customer
	 */
	protected function createTestCustomer() {
		$customer = $this->getTestCustomer();

		if ( false === $customer ) {
			$customer = new WC_Customer();
			$customer->set_username( uniqid( 'nexcess_wc_automated_testing_' ) );
			$customer->set_password( wp_generate_password() );
			$customer->set_email( uniqid( 'wc-automated-testing+' ) . '@nexcess.net' );
			$customer->set_display_name( 'Nexcess WooCommerce Automated Testing User' );

			$customer_id = $customer->save();

			$this->getOption()->set( 'customer_id', $customer_id )->save();
		}

		return $customer;
	}

	/**
	 * Retrieve the test product.
	 *
	 * If the test product does not yet exist, it will be created.
	 *
	 * @return WC_Product|false
	 */
	protected function getTestProduct() {
		$product_id = $this->getOption()->product_id;

		if ( $product_id ) {
			try {
				$product = new WC_Product( $product_id );

				// In case WC_Product returns a new customer with an ID of 0 if
				// one could not be found with the given ID.
				if ( is_a( $product, 'WC_Product' ) && 0 !== $product->get_id() ) {
					return $product;
				}
			// phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch
			} catch ( \Exception $e ) {
				// The given test product was not valid, so we should fallback to the
				// default response if one was not found in the first place.
			}
		}

		return false;
	}

	/**
	 * Creates test product if one does not exist. Avoids flooding the DB with test products.
	 *
	 * @return WC_Product
	 */
	protected function createTestProduct() {
		$product = $this->getTestProduct();

		if ( ! $product ) {
			$product = new WC_Product();
			$product->set_status( 'publish' );
			$product->set_name( 'WooCommerce Automated Testing Product' );
			$product->set_short_description( 'An example product for automated testing.' );
			$product->set_description( 'This is a placeholder product used for automatically testing your WooCommerce store. It\'s designed to be hidden from all customers.' );
			$product->set_regular_price( '1.00' );
			$product->set_price( '1.00' );
			$product->set_stock_status( 'instock' );
			$product->set_stock_quantity( 5 );
			$product->set_catalog_visibility( 'hidden' );

			// This filter is added here to prevent the WCAT test product from being publicized on creation.
			add_filter( 'publicize_should_publicize_published_post', '__return_false' );

			$product_id = $product->save();

			$this->getOption()->set( 'product_id', $product_id )->save();
		}

		return $product;
	}

	/**
	 * Get the appropriate icon for the given check result.
	 *
	 * @param string $status The result status.
	 *
	 * @return string The icon markup corresponding to $status.
	 */
	public static function getStatusIcon( $status ) {
		if ( 'success' === $status ) {
			return sprintf(
				'<span class="dashicons dashicons-yes-alt mapps-status-success" title="%s"><span class="screen-reader-text">%s</span></span>',
				__( 'Check completed successfully', 'nexcess-mapps' ),
				_x( 'Success', 'WooCommerce automated testing check status', 'nexcess-mapps' )
			);
		}

		if ( 'skipped' === $status ) {
			return sprintf(
				'<span class="dashicons dashicons-marker mapps-status-warning" title="%s"><span class="screen-reader-text">%s</span></span>',
				__( 'This check was skipped', 'nexcess-mapps' ),
				_x( 'Skipped', 'WooCommerce automated testing check status', 'nexcess-mapps' )
			);
		}

		return sprintf(
			'<span class="dashicons dashicons-dismiss mapps-status-error" title="%s"><span class="screen-reader-text">%s</span></span>',
			__( 'This check did not complete successfully', 'nexcess-mapps' ),
			_x( 'Failure', 'WooCommerce automated testing check status', 'nexcess-mapps' )
		);
	}

	/**
	 * Remove the WCAT test user and test product if WCAT is disabled.
	 */
	public function deleteTestProductAdminUser() {
		$customer = $this->getTestCustomer();
		$product  = $this->getTestProduct();

		if ( $customer ) {
			$customer->delete();
		}

		if ( $product ) {
			try {
				$data_store = WC_Data_Store::load( 'product' );
				$data_store->delete( $product, [ 'force_delete' => true ] );
			// phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch
			} catch ( \Exception $e ) {
				esc_attr_e( 'No product to delete. Have you enabled WCAT previously?', 'nexcess-mapps' );
			}
		}

		// We always want to be able to remove these from the wp_option in case user deletes product or customer
		// manually from DB instead of using enable/disable.
		$this->getOption()->delete( 'customer_id' )->delete( 'product_id' )->save();

		$this->updateSite( [ 'is_active' => false ] );
	}

	/**
	 * Add the WCAT test user and test product back in if WCAT is re-enabled.
	 */
	public function addTestProductAdminUser() {
		if ( ! $this->getTestCustomer() ) {
			$this->createTestCustomer();
		}

		if ( ! $this->getTestProduct() ) {
			$this->createTestProduct();
		}
	}

	/**
	 * Check if product id exists.
	 *
	 * @return int|bool
	 */
	public function getProductId() {
		$product = $this->getTestProduct();

		if ( $product ) {
			$product_id = $product->get_id();

			return $product_id;
		}

		return false;
	}

	/**
	 * Add noindex to the test product.
	 */
	public function noIndexForTestProduct() {
		$product_id = $this->getProductId();

		if ( is_int( $product_id ) && 0 !== $product_id && is_single( $product_id ) ) {
			echo '<meta name="robots" content="noindex, nofollow"/>';
		}
	}

	/**
	 * Hide test product from Yoast sitemap. Takes $excluded_post_ids if any set, adds our $product_id to the array and
	 * returns the array.
	 *
	 * @param array $excluded_posts_ids
	 *
	 * @return array[]
	 */
	public function wpseoHideTestProductFromSitemap( $excluded_posts_ids = [] ) {
		$product_id = $this->getProductId();

		if ( $product_id ) {
			array_push( $excluded_posts_ids, $product_id );
		}

		return $excluded_posts_ids;
	}

	/**
	 * Hide test product from WordPress' sitemap.
	 *
	 * @param array $args
	 *
	 * @return array
	 */
	public function wpHideTestProductFromSiteMap( $args ) {
		$product_id = $this->getProductId();

		if ( $product_id ) {
			$args['post__not_in']   = isset( $args['post__not_in'] ) ? $args['post__not_in'] : [];
			$args['post__not_in'][] = $product_id;
		}

		return $args;
	}

	/**
	 * Hide test product from JetPack's Publicize module and from Jetpack Social.
	 *
	 * @param bool     $should_publicize
	 * @param \WP_Post $post
	 *
	 * @return bool|array
	 */
	public function hideTestProductFromJetPack( $should_publicize, $post ) {
		if ( $post ) {
			$product_id = $this->getProductId();

			if ( $product_id === $post->ID ) {
				return false;
			}
		}

		return $should_publicize;
	}

	/**
	 * Adds feature integration information to the telemetry report.
	 *
	 * @param array[] $report The gathered report data.
	 *
	 * @return array[] The $report array.
	 */
	public function addFeatureToTelemetry( array $report ) {
		$report['features'][ self::TELEMETRY_FEATURE_KEY ] = $this->getOption()->get( 'enable_wcat' );

		return $report;
	}
}
