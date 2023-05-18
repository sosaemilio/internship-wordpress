<?php

namespace Nexcess\MAPPS\Commands;

use Kadence_Plugin_API_Manager;
use Kadence_Pro_API_Manager;
use Nexcess\MAPPS\Concerns\MakesHttpRequests;
use Nexcess\MAPPS\Integrations\StoreBuilder as Integration;
use Nexcess\MAPPS\Settings;
use StellarWP\PluginFramework\Exceptions\IngestionException;
use StellarWP\PluginFramework\Exceptions\RequestException;
use StellarWP\PluginFramework\Exceptions\WPErrorException;

/**
 * Commands specific to StoreBuilder sites.
 */
class StoreBuilder extends Command {
	use MakesHttpRequests;

	/**
	 * @var \Nexcess\MAPPS\Integrations\StoreBuilder
	 */
	private $integration;

	/**
	 * @var \Nexcess\MAPPS\Settings
	 */
	private $settings;

	/**
	 * @var bool
	 */
	public $force;

	/**
	 * Create a new instance of the command.
	 *
	 * @param \Nexcess\MAPPS\Settings                  $settings    The settings object.
	 * @param \Nexcess\MAPPS\Integrations\StoreBuilder $integration The StoreBuilder integration.
	 */
	public function __construct( Settings $settings, Integration $integration ) {
		$this->settings    = $settings;
		$this->integration = $integration;
	}

	/**
	 * Build out the site based on details from the StoreBuilder app.
	 *
	 * This command will also install and activate the Kadence theme (if it isn't already the current theme).
	 *
	 * ## OPTIONS
	 *
	 * [--force]
	 * : Ingest content, even if the ingestion lock has already been set and/or the store has received orders.
	 *
	 * [--noversion]
	 * : Skip setting the StoreBuilder version in the database.
	 *
	 * [--noplugins]
	 * : Don't install any plugins.
	 *
	 * [--nothemes]
	 * : Don't install the theme.
	 *
	 * [--notheme]
	 * : Don't install the theme.
	 *
	 * [--nooptions]
	 * : Don't install the options.
	 *
	 * [--noupdates]
	 * : Don't update plugins.
	 *
	 * [--nocontent]
	 * : Don't ingest the content.
	 *
	 *
	 * ## EXAMPLES
	 *
	 *   # Build the store
	 *   wp nxmapps storebuilder build
	 *
	 *   # Build the store, re-ingesting content if the ingestion lock has already been set.
	 *   wp nxmapps storebuilder build --force
	 *
	 * @param string[] $args    Positional arguments.
	 * @param string[] $options Associative arguments.
	 */
	public function build( array $args, array $options ) {
		// Let the StoreBuilder integration know that it's being initialized during a build.
		! defined( 'STOREBUILDER_SETUP' ) && define( 'STOREBUILDER_SETUP', true );

		if ( ! is_plugin_active( 'woocommerce/woocommerce.php' ) ) {
			return $this->warning( 'Unable to configure StoreBuilder, as WooCommerce is not active on this site.' );
		}

		$this->force = ! empty( $options['force'] ) ? (bool) $options['force'] : false;

		if ( ! $this->integration->mayIngestContent() && ! $this->force ) {
			return $this->error( 'StoreBuilder has already been run for this site. You can re-run it with the --force option.', 1 );
		}

		$deps = [];

		try {
			if ( empty( $options['noplugins'] ) && empty( $options['nothemes'] ) ) {
				$deps = $this->getDependencies();
			}
		} catch ( RequestException $e ) {
			return $this->error( sprintf( 'Unable to get StoreBuilder dependencies: %s', $e->getMessage() ), 1 );
		}

		// Time to build up the site!
		// For all of the following, you can pass a `--no<whatever>` to skip that step.
		if ( empty( $options['noversion'] ) ) {
			$this->setStorebuilderVersion();
		}

		if ( empty( $options['noplugins'] ) ) {
			$this->setUpPlugins( $deps );
		}

		if ( empty( $options['nothemes'] ) && empty( $options['notheme'] ) ) {
			$this->setUpTheme( $deps );
		}

		if ( empty( $options['nooptions'] ) ) {
			$this->setUpOptions();
		}

		if ( empty( $options['noupdates'] ) ) {
			$this->updatePlugins();
		}

		if ( empty( $options['nocontent'] ) ) {
			$this->ingestContent();
		}

		$this->success( 'Site has been built successfully!' );
	}

	/**
	 * Install the plugins for the site.
	 *
	 * @param array $deps The dependencies.
	 */
	protected function setUpPlugins( $deps ) {
		$this->step( 'Installing plugins...' );

		$plugins = apply_filters( 'Nexcess\\Mapps\\StoreBuilder\\PluginsToInstall', [
			'kadence-blocks',
			'/usr/local/plugins/wordpress/5/kadence-starter-templates.zip',
			// 'kadence-starter-templates',
			'spotlight-social-photo-feeds',
			'woocommerce', // It's going to be there, but we want to make sure we force all our dependencies.
			'wp101',
		] );

		// Looping through and installing individually so that a failed install
		// doesn't prevent the others from installing.
		foreach ( $plugins as $plugin ) {
			$this->wp( 'plugin install --activate ' . $plugin );
		}

		if ( isset( $deps['wp101_api_key'] ) ) {
			$wp101_key = $deps['wp101_api_key'];
		} elseif ( isset( $deps['wp101'], $deps['wp101']['key'] ) ) {
			$wp101_key = $deps['wp101']['key'];
		} else {
			$wp101_key = false;
		}

		if ( $wp101_key ) {
			update_option( 'wp101_api_key', sanitize_text_field( $wp101_key ) );
		}

		$this->step( 'Plugins installed.' );
	}

	/**
	 * Updates the plugins for the site. This is to cover for the API giving
	 * out of date plugins. Also update the WooCommerce database.
	 */
	protected function updatePlugins() {
		$this->step( 'Updating plugins...' );

		$this->wp( 'plugin update --all' );
		$this->wp( 'wc update' ); // Update the WooCommerce database.

		$this->step( 'Plugins updated.' );
	}

	/**
	 * Install and license the suite of Kadence theme + plugins.
	 *
	 * @param array $deps The dependencies.
	 */
	protected function setUpTheme( $deps ) {
		$this->step( 'Setting up theme...' );

		$email   = ! empty( $deps['kadence']['email'] ) ? sanitize_text_field( $deps['kadence']['email'] ) : null;
		$license = ! empty( $deps['kadence']['key'] ) ? sanitize_text_field( $deps['kadence']['key'] ) : null;

		// The Kadence theme.
		$this->step( 'Installing and activating Kadence' )
			->wp( 'theme install --activate kadence' );

		// Install the premium plugins.
		if ( isset( $deps['kadence']['zip'] ) ) {
			$this->wp( 'plugin install --activate ' . implode( ' ', array_map( 'escapeshellarg', (array) $deps['kadence']['zip'] ) ) );

			$this->licenseKadence( $email, $license );
			$this->licenseKadenceBlocks( $email, $license );
		}

		$this->step( 'Theme installed.' );
	}

	/**
	 * License Kadence Blocks Pro.
	 *
	 * @param string|null $email   Email address.
	 * @param string|null $license License key.
	 */
	protected function licenseKadenceBlocks( $email, $license ) {
		$this->step( 'Licensing Kadence Blocks...' );

		try {
			$file = WP_CONTENT_DIR . '/plugins/kadence-blocks-pro/kadence-classes/kadence-activation/class-kadence-plugin-api-manager.php';

			if ( ! file_exists( $file ) ) {
				return;
			}

			require_once $file;

			$instance = Kadence_Plugin_API_Manager::get_instance();
			$instance->on_init();
			$instance->activate( [
				'email'       => $email,
				'licence_key' => $license,
				'product_id'  => 'kadence_gutenberg_pro',
			] );

			update_option( 'kt_api_manager_kadence_gutenberg_pro_data', [
				'api_key'    => $license,
				'api_email'  => $email,
				'product_id' => 'kadence_gutenberg_pro',
			] );

			update_option( 'kadence_gutenberg_pro_activation', 'Activated' );
		} catch ( \Exception $e ) {
			$this->warning( 'Kadence Blocks Pro has been installed, but was not licensed.' );
		}

		$this->step( 'Kadence Blocks licensed.' );
	}

	/**
	 * License Kadence Pro (the theme add-on plugin).
	 *
	 * @param string|null $email   Email address.
	 * @param string|null $license License key.
	 */
	protected function licenseKadence( $email, $license ) {
		$this->step( 'Licensing Kadence...' );

		try {
			$file = WP_CONTENT_DIR . '/plugins/kadence-pro/dist/dashboard/class-kadence-pro-dashboard.php';

			if ( ! file_exists( $file ) ) {
				return;
			}

			require_once $file;

			$instance = Kadence_Pro_API_Manager::instance(
				'kadence_pro_activation',
				'kadence_pro_api_manager_instance',
				'kadence_pro_api_manager_activated',
				'kadence_pro',
				'Kadence Pro'
			);

			// Define a manager instance ID, if we don't already have one.
			$manager_instance = get_option( 'kadence_pro_api_manager_instance', '' );

			if ( ! $manager_instance ) {
				$manager_instance = wp_generate_password( 12, false );
				update_option( 'kadence_pro_api_manager_instance', $manager_instance );
			}

			/*
			* Kadence Pro won't fully set up unless `is_admin()` is true, so we'll set some
			* properties ourselves.
			*/
			$instance->kt_instance_id = $manager_instance;
			$instance->kt_domain      = str_ireplace( [ 'http://', 'https://' ], '', home_url() );
			$instance->version        = wp_get_theme()->get( 'Version' );

			// Finally, attempt activation.
			$instance->activate( [
				'email'       => $email,
				'licence_key' => $license, // "licence" is intentionally misspelled.
			] );

			update_option( 'ktp_api_manager', [
				'ktp_api_key'      => $license,
				'activation_email' => $email,
			] );

			update_option( 'kadence_pro_api_manager_activated', 'Activated' );
		} catch ( \Exception $e ) {
			$this->warning( 'Kadence Pro (theme add-on) has been installed, but was not licensed.' );
		}

		$this->step( 'Kadence licensed.' );
	}

	/**
	 * Set up default options for the StoreBuilder site.
	 */
	protected function setUpOptions() {
		$this->step( 'Setting options...' );

		/**
		 * Set a bunch of default options, including WooCommerce onboarding settings.
		 *
		 * @link https://woocommerce.github.io/woocommerce-admin/#/features/onboarding/
		 */
		update_option( 'woocommerce_allow_tracking', 'no' );
		update_option( 'woocommerce_analytics_enabled', 'yes' );
		update_option( 'woocommerce_demo_store', 'no' );
		update_option( 'woocommerce_calc_taxes', 'yes' );
		update_option( 'woocommerce_marketing_overview_welcome_hidden', 'yes' );
		update_option( 'woocommerce_merchant_email_notifications', 'no' );
		update_option( 'woocommerce_show_marketplace_suggestions', 'no' );
		update_option( 'woocommerce_extended_task_list_hidden', 'yes' );
		update_option( 'woocommerce_task_list_appearance_complete', true );
		update_option( 'woocommerce_task_list_complete', 'yes' );
		update_option( 'woocommerce_task_list_hidden', 'yes' );
		update_option( 'woocommerce_task_list_prompt_shown', true );
		update_option( 'woocommerce_task_list_welcome_modal_dismissed', 'yes' );

		update_option( 'woocommerce_task_list_tracked_completed_tasks', [
			'store_details',
			'products',
			'payments',
			'tax',
			'shipping',
			'appearance',
		] );

		update_option( 'woocommerce_onboarding_profile', [
			'business_extensions' => [],
			'completed'           => true,
			'setup_client'        => false,
			'industry'            => [ [ 'slug' => 'other' ] ],
			'product_types'       => [ 'physical' ],
			'product_count'       => '0',
			'selling_venues'      => 'no',
			'theme'               => 'kadence',
		] );

		$this->step( 'Options set.' );
	}

	/**
	 * Retrieve dependencies from the StoreBuilder API.
	 *
	 * @throws \StellarWP\PluginFramework\Exceptions\RequestException If the dependencies can't be retrieved.
	 *
	 * @return array[] Details necessary to install all StoreBuilder plugins.
	 */
	protected function getDependencies() {
		$this->step( 'Getting dependencies and licenses...' );

		$response = wp_remote_get( $this->settings->quickstart_app_url . '/api/dependencies', [
			'headers' => [
				'Authorization' => 'Bearer ' . $this->settings->managed_apps_token,
				'Accept'        => 'application/json',
			],
		] );

		try {
			$json = $this->validateHttpResponse( $response );
		} catch ( WPErrorException $e ) {
			throw new RequestException( $e->getMessage(), $e->getCode(), $e );
		}

		$body = json_decode( $json, true );

		if ( ! is_array( $body ) ) {
			throw new RequestException(
				sprintf( 'Received an unexpected response body from the StoreBuilder app: %s', (string) $json )
			);
		}

		$this->step( 'Got dependencies and licenses.' );

		return $body;
	}

	/**
	 * Ingest content.
	 *
	 * @throws IngestionException If the content can't be ingested.
	 */
	protected function ingestContent() {
		$this->step( 'Ingesting StoreBuilder content...' );

		try {
			$this->integration->ingestContent( $this->force );
		} catch ( IngestionException $e ) {
			return $this->error( $e->getMessage(), 1 );
		}

		$this->step( 'Ingestion complete.' );
	}

	/**
	 * Adds the Storebuilder Version as an option for future reference.
	 */
	protected function setStorebuilderVersion() {
		// Intentionally using `add_option` to avoid overwriting any previously defined values.
		add_option( Integration::INITIAL_STOREBUILDER_VERSION, Integration::VERSION );
		add_option( Integration::CURRENT_STOREBUILDER_VERSION, Integration::VERSION );

		$this->step( sprintf( 'StoreBuilder version: %s', Integration::VERSION ) );
	}
}
