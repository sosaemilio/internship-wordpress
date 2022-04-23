<?php

namespace Nexcess\MAPPS\Commands;

use Nexcess\MAPPS\Concerns\HasCronEvents;
use Nexcess\MAPPS\Concerns\ManagesPermalinks;
use Nexcess\MAPPS\Exceptions\InstallationException;
use Nexcess\MAPPS\Exceptions\MappsApiException;
use Nexcess\MAPPS\Integrations\Telemetry;
use Nexcess\MAPPS\Integrations\WooCommerce;
use Nexcess\MAPPS\Services\Installer as InstallerService;
use Nexcess\MAPPS\Settings;

/**
 * Commands for preparing a new Nexcess Managed Apps site.
 */
class Setup extends Command {
	use HasCronEvents;
	use ManagesPermalinks;

	/**
	 * @var bool
	 */
	protected $isProvisioning = false;

	/**
	 * @var \Nexcess\MAPPS\Services\Installer
	 */
	private $installer;

	/**
	 * @var \Nexcess\MAPPS\Settings
	 */
	private $settings;

	/**
	 * @var \Nexcess\MAPPS\Integrations\WooCommerce
	 */
	private $woocommerce;

	/**
	 * Create a new instance of the Setup command class.
	 *
	 * @param \Nexcess\MAPPS\Settings                 $settings
	 * @param \Nexcess\MAPPS\Services\Installer       $installer
	 * @param \Nexcess\MAPPS\Integrations\WooCommerce $woocommerce
	 */
	public function __construct( Settings $settings, InstallerService $installer, WooCommerce $woocommerce ) {
		$this->settings    = $settings;
		$this->installer   = $installer;
		$this->woocommerce = $woocommerce;
	}

	/**
	 * Set up a new Nexcess Managed Apps site.
	 *
	 * This command should automatically detect the type of Nexcess Managed Apps environment and apply the
	 * appropriate setup commands.
	 *
	 * ## OPTIONS
	 *
	 * [--provision]
	 * : Signal that this is being run as part of the initial site provisioning.
	 *
	 * @synopsis [--provision]
	 *
	 * @param mixed[] $args       Positional arguments.
	 * @param mixed[] $assoc_args Associative arguments.
	 */
	public function setup( $args, $assoc_args ) {
		$this->isProvisioning = ! empty( $assoc_args['provision'] );
		$this->verifyIsMappsSite();

		// Run minimal setup for staging + regression sites.
		if ( $this->settings->is_staging_site || $this->settings->is_regression_site ) {
			$this->step( 'Reconfiguring caches' );

			// Disable all caches in regression environments.
			if ( $this->settings->is_regression_site ) {
				$this->wp( 'nxmapps cache disable --all' );
			}

			// Flush all caches.
			$this->wp( 'nxmapps cache flush --all' );

			// Return early to prevent the rest of the commands from running.
			return;
		}

		$this->step( 'Ensuring WordPress core and all existing plugins and themes are up-to-date' );
		$this->wp( 'core update' );
		$this->wp( 'core update-db', [
			'launch' => true,
		] );
		$this->wp( 'plugin update --all --format=summary' );
		$this->wp( 'theme update --all --format=summary' );
		touch( ABSPATH . '/favicon.ico' );

		// Some plugins depend on a proper permalink structure, so set it early.
		$this->step( 'Verifying that a permalink structure is set' );
		$this->setDefaultPermalinkStructure();

		// Pre-install plugins, based on the current site's plan.
		$this->preInstallPlugins();

		// WP QuickStart (excluding StoreBuilder).
		if ( $this->settings->is_quickstart && ! $this->settings->is_storebuilder ) {
			$this->wp( 'nxmapps quickstart build' );
		}

		// Managed WooCommerce.
		if ( $this->settings->is_mwch_site ) {
			$this->woocommerce();
		}

		// Enable all caching.
		$this->wp( 'nxmapps cache enable --all' );

		// Register a cron event to pre-install plugins, as they may not have been available earlier.
		$this->registerCronEvent( 'nexcess_mapps_preinstall_plugins', null, current_datetime() )
			->scheduleEvents();

		// Plugin Performance Monitor.
		$this->wp( 'nxmapps performance-monitor enable' );

		// Send initial telemetry data.
		do_action( Telemetry::REPORT_CRON_ACTION );
	}

	/**
	 * Set up a new Managed WooCommerce site.
	 */
	public function woocommerce() {
		$this->verifyIsMappsSite();

		$this->step( 'Configuring WooCommerce...' );

		if ( ! is_plugin_active( 'woocommerce/woocommerce.php' ) ) {
			return $this->warning( 'Unable to configure WooCommerce, as WooCommerce is not active on this site.' );
		}

		// Set default options.
		$this->log( '- Setting default options' );
		foreach ( $this->woocommerce->getDefaultOptions() as $key => $value ) {
			update_option( $key, $value );
		}

		if ( $this->settings->is_storebuilder ) {
			$this->line( '- Ingesting content from StoreBuilder' )
				->wp( 'nxmapps storebuilder build' );
		} elseif ( ! $this->settings->is_quickstart ) {
			$this->wp( 'theme install --activate kadence' );
		}

		$this->log( '- Creating default pages' );
		$this->woocommerce->createDefaultPages();

		// Set default user meta.
		$this->log( '- Setting default user meta' );
		add_user_meta( 1, 'is_disable_paypal_marketing_solutions_notice', true );

		// Disable cart fragments.
		$this->log( '- Disabling cart fragments' );
		$this->wp( 'nxmapps wc cart-fragments disable' );

		// Create default shipping zones.
		$this->log( '- Creating default shipping zones' );
		$this->woocommerce->createDefaultShippingZones();

		// Enable WooCommerce automated testing.
		$this->log( '- Enabling automated testing' );
		$this->wp( 'nxmapps wc automated-testing enable' );

		$this->newline()->success( 'Default WooCommerce configurations have been applied.' );
	}

	/**
	 * Pre-install and license plugins that should be present based on the site's plan.
	 */
	public function preInstallPlugins() {
		$this->verifyIsMappsSite();

		$this->step( 'Pre-installing plugins for the site\'s plan...' );
		$errors = false;

		try {
			$plugins = $this->installer->getPreinstallPlugins();
		} catch ( MappsApiException $e ) {
			$this->error( 'Error getting pre-install plugins from the MAPPS API: ' . $e->getMessage(), false );
			return;
		}

		foreach ( $plugins as $plugin ) {
			try {
				$this->log( sprintf( '- Installing %1$s', $plugin->identity ) );
				$this->installer->install( $plugin->id );

				if ( 'none' !== $plugin->license_type ) {
					$this->debug( sprintf(
						'- Licensing %1$s (%2$s)',
						$plugin->identity,
						$plugin->license_type
					) );
					$this->installer->license( $plugin->id );
				}
			} catch ( InstallationException $e ) {
				$this->warning( $e->getMessage() );
				$errors = true;
			}
		}

		if ( $errors ) {
			$this->error( 'One or more plugins could not be pre-installed.', false );
		}

		/*
		 * Eventually all partner plugins will be moved into the plugin API, but until that's
		 * fully-available we'll need to handle installation here instead.
		 */
		$this->preInstallPartnerPlugins();
	}

	/**
	 * Pre-install partner plugins, depending on the plan.
	 */
	protected function preInstallPartnerPlugins() {
		/**
		 * WPForms Lite should be pre-installed on Managed WordPress sites, excluding WP QuickStart.
		 *
		 * @link https://wordpress.org/plugins/wpforms-lite/
		 */
		if ( ! $this->settings->is_mwch_site && ! $this->settings->is_quickstart ) {
			$this->wp( 'plugin install wpforms-lite' );
		}
	}

	/**
	 * Verify that this is being run on a MAPPS site.
	 *
	 * If not, a message will be displayed and the script will exit with the given code.
	 *
	 * @param int $code The exit code to use if the check fails.
	 */
	protected function verifyIsMappsSite( $code = 0 ) {
		if ( $this->settings->is_mapps_site ) {
			return;
		}

		$this->warning( 'This does not appear to be a MAPPS site, aborting.' )
			->halt( $code );
	}
}
