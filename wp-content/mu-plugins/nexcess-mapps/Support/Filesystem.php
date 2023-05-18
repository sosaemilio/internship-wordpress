<?php

/**
 * A wrapper around the base WP_Filesystem class with better error handling.
 */

namespace Nexcess\MAPPS\Support;

use StellarWP\PluginFramework\Exceptions\FilesystemException;
use StellarWP\PluginFramework\Exceptions\WPErrorException;
use WP_Filesystem_Base;

use function WP_Filesystem;

class Filesystem {

	/**
	 * Initialize the WordPress filesystem.
	 *
	 * @global $wp_filesystem
	 *
	 * @throws \StellarWP\PluginFramework\Exceptions\FilesystemException If anything goes wrong.
	 * @throws \StellarWP\PluginFramework\Exceptions\WPErrorException    If WP_Filesystem() fails.
	 *
	 * @return \WP_Filesystem_Base The initialized WP_Filesystem variant.
	 */
	public static function init() {
		global $wp_filesystem;

		// We already have an instance, so return early.
		if ( $wp_filesystem instanceof WP_Filesystem_Base ) {
			return $wp_filesystem;
		}

		try {
			require_once ABSPATH . '/wp-admin/includes/file.php';

			$filesystem = WP_Filesystem();

			if ( null === $filesystem ) {
				throw new FilesystemException( 'The provided filesystem method is unavailable.' );
			}

			if ( false === $filesystem ) {
				if ( is_wp_error( $wp_filesystem->errors ) && $wp_filesystem->errors->has_errors() ) {
					throw new WPErrorException( $wp_filesystem->errors );
				}

				throw new FilesystemException( 'Unspecified failure.' );
			}

			if ( ! is_object( $wp_filesystem ) || ! $wp_filesystem instanceof WP_Filesystem_Base ) {
				throw new FilesystemException( '$wp_filesystem is not an instance of WP_Filesystem_Base' );
			}
		} catch ( \Exception $e ) {
			throw new FilesystemException(
				sprintf( 'There was an error initializing the WP_Filesystem class: %1$s', $e->getMessage() ),
				$e->getCode(),
				$e
			);
		}

		return $wp_filesystem;
	}
}
