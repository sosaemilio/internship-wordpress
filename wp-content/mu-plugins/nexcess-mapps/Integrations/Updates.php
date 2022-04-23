<?php

/**
 * Control how we handle WordPress core updates.
 */

namespace Nexcess\MAPPS\Integrations;

use Nexcess\MAPPS\Concerns\HasHooks;
use Nexcess\MAPPS\Settings;
use Nexcess\MAPPS\Support\AdminNotice;
use Nexcess\MAPPS\Support\Branding;

class Updates extends Integration {
	use HasHooks;

	/**
	 * @var \Nexcess\MAPPS\Settings
	 */
	protected $settings;

	/**
	 * @param \Nexcess\MAPPS\Settings $settings
	 */
	public function __construct( Settings $settings ) {
		$this->settings = $settings;
	}

	/**
	 * Perform any necessary setup for the integration.
	 *
	 * This method is automatically called as part of Plugin::loadIntegration(), and is the
	 * entry-point for all integrations.
	 */
	public function setup() {
		$this->addHooks();

		// Disable the "Try Gutenberg" dashboard widget (WP < 5.x only).
		remove_action( 'try_gutenberg_panel', 'wp_try_gutenberg_panel' );
	}

	/**
	 * Retrieve all actions for the integration.
	 *
	 * @return array[]
	 */
	protected function getActions() {
		// phpcs:disable WordPress.Arrays
		return [
			[ 'admin_init',                              [ $this, 'removeUpdateNag'        ] ],
			[ 'after_core_auto_updates_settings_fields', [ $this, 'renderAutoUpdateNotice' ] ],
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
			// Control which automatic updates are permitted by default.
			[ 'allow_dev_auto_core_updates',   '__return_false', 1 ],
			[ 'allow_minor_auto_core_updates', '__return_true' , 1 ],
			[ 'allow_major_auto_core_updates', '__return_false', 1 ],
		];

		// Change behavior based on whether or not MAPPS is responsible for core updates.
		if ( $this->settings->mapps_core_updates_enabled ) {
			$filters = array_merge( $filters, [
				// Don't email site owners about core updates.
				[ 'auto_core_update_send_email',         '__return_false', 1 ],
				[ 'send_core_update_notification_email', '__return_false', 1 ],
			] );
		}

		// Disable auto plugin updates if handled by MAPPS.
		if ( $this->settings->mapps_plugin_updates_enabled ) {
			$filters[] = [ 'auto_update_plugin',          '__return_false', 1 ];
			$filters[] = [ 'plugins_auto_update_enabled', '__return_false', 1 ];
		}

		// phpcs:enable WordPress.Arrays
		return $filters;
	}

	/**
	 * Remove the "WordPress X.X is available! Please notify the site administrator" nags.
	 *
	 * @see update_nag()
	 */
	public function removeUpdateNag() {
		if ( $this->settings->mapps_core_updates_enabled ) {
			remove_action( 'admin_notices', 'update_nag', 3 );
		}
	}

	/**
	 * Render a notice reminding users that core updates are currently being handled by MAPPS.
	 */
	public function renderAutoUpdateNotice() {
		if ( ! $this->settings->mapps_core_updates_enabled ) {
			return;
		}

		$message = sprintf(
			/* Translators: %1$s is the brand name. */
			__( 'WordPress core is currently being automatically updated by %1$s.', 'nexcess-mapps' ),
			Branding::getCompanyName()
		);

		$notice = new AdminNotice( $message, 'info', false, 'auto-core-updates-handled-by-platform' );
		$notice->setInline( true );
		$notice->output();

		// Disable the checkbox.
		wp_add_inline_script(
			'updates',
			'document.getElementById(\'core-auto-updates-major\').setAttribute(\'disabled\', true);'
		);
	}
}
