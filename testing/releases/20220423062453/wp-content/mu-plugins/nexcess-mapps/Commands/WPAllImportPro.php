<?php

namespace Nexcess\MAPPS\Commands;

use PMXI_Plugin;
use WP_CLI;

/**
 * Integration with WP All Import Pro by Soflyy.
 */
class WPAllImportPro {

	/**
	 * The key used in wp_options.
	 */
	const OPTION_KEY = 'PMXI_Plugin_Options';

	/**
	 * Activate a WP All Import Pro license for the site.
	 *
	 * ## OPTIONS
	 *
	 * <license>
	 * : The WP All Import Pro license key.
	 *
	 * ## EXAMPLES
	 *
	 *   $ wp nxmapps wp-all-import-pro activate <license-key>
	 *
	 * @param mixed[] $args Positional arguments.
	 */
	public function activate( $args ) {
		$license_key = $args[0];

		// Verify that the PMXI_Plugin class is present.
		if ( ! class_exists( 'PMXI_Plugin' ) ) {
			return WP_CLI::error( __(
				'The PMXI_Plugin class is undefined. Is WP All Import Pro installed and activated?',
				'nexcess-mapps'
			) );
		}

		try {
			$option = get_option( self::OPTION_KEY, [] );

			// Overwrite the existing license key, if it exists.
			if ( empty( $option['licenses'] ) || ! is_array( $option['licenses'] ) ) {
				$option['licenses'] = [];
			}

			// Update the PMXI_Plugin license option using the plugin's encode() method.
			$option['licenses']['PMXI_Plugin'] = PMXI_Plugin::getInstance()->encode( $license_key );

			update_option( self::OPTION_KEY, $option );
		} catch ( \Exception $e ) {
			return WP_CLI::error( sprintf(
				/* Translators: %1$s is the exception message. */
				__( 'An error occurred setting the WP All Import Pro license key: %1$s', 'nexcess-mapps' ),
				$e->getMessage()
			) );
		}

		WP_CLI::success( __( 'The WP All Import Pro license key has been set!', 'nexcess-mapps' ) );
	}
}
