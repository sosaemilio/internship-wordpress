<?php

namespace Nexcess\MAPPS\Modules;

use Nexcess\MAPPS\Concerns\HasAdminPages;
use Nexcess\MAPPS\Concerns\HasAssets;
use Nexcess\MAPPS\Integrations\Dashboard;
use Nexcess\MAPPS\Support\Helpers;
use StellarWP\PluginFramework\Support\VisualRegressionUrl;

class VisualComparison extends \StellarWP\PluginFramework\Modules\VisualComparison {

	use HasAdminPages;
	use HasAssets;

	/**
	 * The settings group.
	 */
	const SETTINGS_GROUP = 'nexcess_mapps_visual_comparison';

	/**
	 * The option name used to store custom URLs.
	 */
	const SETTING_NAME = 'nexcess_mapps_visual_regression_urls';

	/**
	 * Perform any necessary setup for the integration.
	 *
	 * This method is automatically called as part of Plugin::loadIntegration(), and is the
	 * entry-point for all integrations.
	 */
	public function setup() {
		parent::setup();

		add_action( 'admin_init', [ $this, 'registerDashboardSection' ], 100 );
		add_action( 'admin_init', [ $this, 'registerSetting' ] );
	}

	/**
	 * Register the Visual Comparison settings section on the MAPPS dashboard.
	 */
	public function registerDashboardSection() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		add_settings_section(
			'priority-pages',
			_x( 'Priority Pages', 'settings section', 'nexcess-mapps' ),
			function () {
				// Prepare the React component.
				$this->enqueueScript( 'nexcess-mapps-visual-comparison', 'visual-comparison.js', [
					'nexcess-mapps-admin',
					'wp-element',
				] );

				$this->injectScriptData( 'nexcess-mapps-visual-comparison', 'visualComparison', [
					'baseUrl' => Helpers::truncate( mb_substr( site_url( '', 'https' ), 8 ), 15, 6 ),
					'limit'   => self::MAXIMUM_URLS,
					'setting' => self::SETTING_NAME,
					'urls'    => $this->getUrls(),
				] );

				$this->renderTemplate( 'priority-pages', [ 'settings' => $this->settings ] );
			},
			Dashboard::ADMIN_MENU_SLUG
		);
	}

	/**
	 * Register the SETTING_NAME setting.
	 */
	public function registerSetting() {
		register_setting( self::SETTINGS_GROUP, self::SETTING_NAME, [
			'sanitize_callback' => [ $this, 'sanitizeSetting' ],
		] );
	}

	/**
	 * Sanitize the URLs submitted via the Settings API.
	 *
	 * @param mixed $value The value being sanitized.
	 *
	 * @return string|false A JSON-encoded string of visual regression URLs or FALSE on error.
	 */
	public function sanitizeSetting( $value ) {
		$value        = (array) $value;
		$urls         = [];
		$paths        = [];
		$descriptions = [];

		if ( ! isset( $value['path'], $value['description'] ) ) {
			return false;
		}

		// Loop through the rows and assemble VisualRegressionUrl objects.
		foreach ( (array) $value['path'] as $index => $path ) {
			$description = ! empty( $value['description'][ $index ] )
				? trim( sanitize_text_field( $value['description'][ $index ] ) )
				: '';

			// If duplicate, non-empty descriptions are provided, they must be incremented.
			if ( ! empty( $description ) && in_array( $description, $descriptions, true ) ) {
				$i = 2;

				while ( in_array( $description . " ($i)", $descriptions, true ) ) {
					$i ++;
				}

				$description .= " ($i)";
			}

			$url  = new VisualRegressionUrl(
				sanitize_text_field( $path ),
				$description
			);
			$path = $url->getPath();

			// If we already have this path, move on.
			if ( in_array( $path, $paths, true ) ) {
				continue;
			}

			$paths[] = $path;
			$urls[]  = $url->withoutId();

			if ( ! empty( $description ) ) {
				$descriptions[] = $description;
			}
		}

		// Apply limits to the number of URLs.
		if ( count( $urls ) > self::MAXIMUM_URLS ) {
			$message = sprintf(
			/* Translators: %1$d is the maximum number of URLs permitted. */
				__( 'In order to provide timely feedback, visual comparison runs are limited to %1$d URLs.', 'nexcess-mapps' ),
				self::MAXIMUM_URLS
			);

			$message .= '<br><br>' . __( 'The following URLs will not be processed:', 'nexcess-mapps' );

			foreach ( array_slice( $urls, self::MAXIMUM_URLS ) as $url ) {
				$message .= sprintf( '<br>- %1$s (%2$s)', $url->getPath(), $url->getDescription() );
			}

			add_settings_error( self::SETTING_NAME, 'mapps-visual-comparison-too-many-urls', $message );

			// Finally, ensure only the permitted values are saved for new settings.
			// If a user has already saved more than the limit, let them still
			// save that many, so that they don't lose data, even if those urls
			// are not actually being processed.
			if ( ! get_option( self::SETTING_NAME, false ) ) {
				$urls = array_slice( $urls, 0, self::MAXIMUM_URLS );
			}
		}

		return wp_json_encode( $urls );
	}
}
