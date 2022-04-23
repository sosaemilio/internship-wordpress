<?php

/**
 * Integrations within the WP Admin area.
 */

namespace Nexcess\MAPPS\Integrations;

use Nexcess\MAPPS\Concerns\HasAssets;
use Nexcess\MAPPS\Concerns\HasCronEvents;
use Nexcess\MAPPS\Exceptions\InstallationException;
use Nexcess\MAPPS\Exceptions\MappsApiException;
use Nexcess\MAPPS\Services\AdminBar;
use Nexcess\MAPPS\Services\Installer;
use Nexcess\MAPPS\Services\Logger;
use Nexcess\MAPPS\Services\Options;
use Nexcess\MAPPS\Settings;
use Nexcess\MAPPS\Support\AdminNotice;
use Nexcess\MAPPS\Support\Branding;

use const Nexcess\MAPPS\PLUGIN_URL;

class Admin extends Integration {
	use HasAssets;
	use HasCronEvents;

	/**
	 * @var \Nexcess\MAPPS\Services\AdminBar
	 */
	protected $adminBar;

	/**
	 * @var \Nexcess\MAPPS\Services\Installer
	 */
	protected $installer;

	/**
	 * @var \Nexcess\MAPPS\Services\Logger
	 */
	protected $logger;

	/**
	 * @var \Nexcess\MAPPS\Services\Options
	 */
	protected $options;

	/**
	 * @var \Nexcess\MAPPS\Settings
	 */
	protected $settings;

	/**
	 * Action hook for dismissing a notification.
	 */
	const HOOK_DISMISSED_NOTICE = 'mapps_dismissed_notice';

	/**
	 * @param \Nexcess\MAPPS\Settings           $settings
	 * @param \Nexcess\MAPPS\Services\AdminBar  $admin_bar
	 * @param \Nexcess\MAPPS\Services\Installer $installer
	 * @param \Nexcess\MAPPS\Services\Logger    $logger
	 * @param \Nexcess\MAPPS\Services\Options   $options
	 */
	public function __construct( Settings $settings, AdminBar $admin_bar, Installer $installer, Logger $logger, Options $options ) {
		$this->settings  = $settings;
		$this->adminBar  = $admin_bar;
		$this->installer = $installer;
		$this->logger    = $logger;
		$this->options   = $options;
	}

	/**
	 * Retrieve all actions for the integration.
	 *
	 * @return array[]
	 */
	protected function getActions() {
		// phpcs:disable WordPress.Arrays
		return [
			[ 'admin_notices',         [ $this, 'renderAdminNotices'    ], 10 ],
			[ 'admin_enqueue_scripts', [ $this, 'enqueueScripts'        ], 1  ],
			[ 'admin_footer_text',     [ $this, 'adminFooterText'       ]     ],

			// Register the admin bar.
			[ 'init', [ $this->adminBar, 'register' ], PHP_INT_MAX ],

			// Register the options page.
			[ 'init', [ $this->options, 'register' ], PHP_INT_MAX ],

			// Ajax callbacks.
			[ 'wp_ajax_' . self::HOOK_DISMISSED_NOTICE, [ $this, 'dismissNotice' ] ],

			// Cron events.
			[ 'nexcess_mapps_preinstall_plugins', [ $this, 'preInstallPlugins' ] ],
		];
		// phpcs:enable WordPress.Arrays
	}

	/**
	 * Register and/or enqueue custom scripts and styles.
	 */
	public function enqueueScripts() {
		wp_register_script(
			'nexcess-mapps-admin',
			$this->getAssetSource( 'admin.js' ),
			[
				'jquery',
				'wp-hooks',
			],
			$this->getAssetVersion(),
			true
		);
		$script = sprintf(
			'window.MAPPS = {
				supportUrl: %1$s
			}',
			wp_json_encode( Branding::getSupportUrl() )
		);
		wp_add_inline_script( 'nexcess-mapps-admin', $script, 'before' );

		$this->enqueueStyle( 'nexcess-mapps-admin', 'admin.css', [ 'dashicons' ] );

		// Now we are going to add the inline image CSS.
		$image  = Branding::getCompanyImage();
		$inline = '
				.mapps-wrap .nexcess-page-title {
					background-image: url("' . esc_url( $image ) . '");
				}
				.mapps-nexcess-icon::before {
					background-image: url("' . esc_url( PLUGIN_URL . '/nexcess-mapps/assets/img/nexcess-icon.svg' ) . '");
				}
				.mapps-kadence-icon::before {
					background-image: url("' . esc_url( PLUGIN_URL . '/nexcess-mapps/assets/img/kadence-icon.png' ) . '");
				}
				.mapps-rcp-icon::before {
					background-image: url("' . esc_url( PLUGIN_URL . '/nexcess-mapps/assets/img/restrict-content-pro-icon.svg' ) . '");
				}';
		wp_add_inline_style( 'nexcess-mapps-admin', $inline );
	}

	/**
	 * Pre-install plugins for the site.
	 *
	 * If we were unable to install the plugins during the initial site setup, try once more on a
	 * cron right after the site is configured.
	 */
	public function preInstallPlugins() {
		$first_run = $this->settings->getFlag( 'preinstall_plugins', false );
		$this->settings->setFlag( 'preinstall_plugins', true );

		try {
			$plugins = $this->installer->getPreinstallPlugins();
		} catch ( MappsApiException $e ) {
			// Re-schedule this for 5min from now, as the site may not be fully set up yet.
			if ( ! $first_run ) {
				$this->registerCronEvent( 'nexcess_mapps_preinstall_plugins', null, current_datetime()->add( new \DateInterval( 'PT5M' ) ) )
					->scheduleEvents();
			}

			return;
		}

		foreach ( $plugins as $plugin ) {
			try {
				$this->installer->install( $plugin->id );

				if ( 'none' !== $plugin->license_type ) {
					$this->installer->license( $plugin->id );
				}
			} catch ( InstallationException $e ) {
				$this->logger->error( sprintf( 'Installer error: %1$s', $e->getMessage() ), [
					'exception' => $e,
				] );
			}
		}
	}

	/**
	 * Render any admin notices we have queued up.
	 */
	public function renderAdminNotices() {
		$notices = array_merge(
			$this->adminBar->getNotices(),
			AdminNotice::getPersistentNotices()
		);

		if ( empty( $notices ) ) {
			return;
		}

		foreach ( $notices as $notice ) {
			// Only show non-dismissible notices once.
			if ( $notice->is_persistent && ! $notice->is_dismissible ) {
				$notice->forget();
			}

			if ( ! $notice->userHasDismissedNotice() ) {
				$notice->output();
			}
		}

		// Enqueue the admin scripting, if it isn't already.
		wp_enqueue_script( 'nexcess-mapps-admin' );
	}

	/**
	 * Ajax callback for dismissed admin notices.
	 */
	public function dismissNotice() {
		if ( empty( $_POST['notice'] ) || empty( $_POST['_wpnonce'] ) ) {
			return wp_send_json_error( 'Required fields missing.', 422 );
		}

		if ( ! wp_verify_nonce( $_POST['_wpnonce'], self::HOOK_DISMISSED_NOTICE ) ) {
			return wp_send_json_error( 'Nonce validation failed.', 403 );
		}

		// If this is a persistent notice, simply forget it and move on.
		$persistent = AdminNotice::getPersistentNotices();

		if ( isset( $persistent[ $_POST['notice'] ] ) ) {
			$persistent[ $_POST['notice'] ]->forget();

			return wp_send_json_success();
		}

		AdminNotice::dismissNotice( get_current_user_id(), sanitize_text_field( $_POST['notice'] ) );

		return wp_send_json_success();
	}

	/**
	 * Replace the default "Thank you for creating with WordPress" link in the WP-Admin footer.
	 *
	 * @param string $text The content that will be printed.
	 *
	 * @return string The filtered $text.
	 */
	public function adminFooterText( $text ) {
		return sprintf(
			/* translators: %1$s is https://wordpress.org/ */
			__( 'Thank you for creating with <a href="%1$s">WordPress</a> and <a href="https://nexcess.net">Nexcess</a>.', 'nexcess-mapps' ),
			// phpcs:ignore WordPress.WP.I18n.MissingArgDomain
			__( 'https://wordpress.org/' )
		);
	}
}
