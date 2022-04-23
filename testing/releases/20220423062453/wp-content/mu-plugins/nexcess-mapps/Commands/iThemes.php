<?php

namespace Nexcess\MAPPS\Commands;

use WP_CLI;

use function WP_CLI\Utils\format_items;

/**
 * Manage iThemes Licensing.
 */
class iThemes {

	/**
	 * The option key used to store iThemes product licenses.
	 */
	const OPTION_NAME = 'ithemes-updater-keys';

	/**
	 * List saved iThemes product licenses.
	 *
	 * ## EXAMPLES
	 *
	 *   wp nxmapps ithemes list
	 *
	 * @subcommand list
	 */
	public function all() {
		$current = get_option( self::OPTION_NAME, [] );
		$table   = [];

		// List products in alphabetical order.
		ksort( $current );

		// Build a formatted table.
		foreach ( $current as $product => $license ) {
			$table[] = [
				'product' => $product,
				'license' => $license,
			];
		}

		return format_items( 'table', $table, [ 'product', 'license' ] );
	}

	/**
	 * Add a new iThemes product license.
	 *
	 * ## OPTIONS
	 *
	 * <product>
	 * : The iThemes product.
	 *
	 * <license>
	 * : The corresponding license key.
	 *
	 * [--force]
	 * : If a license already exists for the given product, replace it.
	 *
	 * ## EXAMPLES
	 *
	 *   wp nxmapps ithemes add backupbuddy abcdef123456
	 *
	 * @param string[] $args    Positional arguments.
	 * @param string[] $options Associative arguments.
	 */
	public function add( $args, $options ) {
		$product = $args[0];
		$license = $args[1];
		$force   = isset( $options['force'] );
		$current = get_option( self::OPTION_NAME, [] );

		if ( isset( $current[ $product ] ) && ! $force ) {
			return WP_CLI::error( sprintf(
				/* Translators: %1$s is the product name. */
				__(
					'A license key already exists for "%1$s". Please use "wp nxmapps ithemes update" or the --force flag to override this value.',
					'nexcess-mapps'
				),
				$product
			) );
		}

		$current[ $product ] = $license;

		update_option( self::OPTION_NAME, $current );

		return WP_CLI::success( sprintf(
			/* Translators: %1$s is the product name. */
			__( 'Product license for "%1$s" has been saved.', 'nexcess-mapps' ),
			$product
		) );
	}

	/**
	 * Update/replace an existing iThemes product license.
	 *
	 * ## OPTIONS
	 *
	 * <product>
	 * : The iThemes product.
	 *
	 * <license>
	 * : The corresponding license key.
	 *
	 * ## EXAMPLES
	 *
	 *   wp nxmapps ithemes update backupbuddy abcdef123456
	 *
	 * @param string[] $args Positional arguments.
	 */
	public function update( $args ) {
		$product = $args[0];
		$license = $args[1];
		$current = get_option( self::OPTION_NAME, [] );

		$current[ $product ] = $license;

		update_option( self::OPTION_NAME, $current );

		return WP_CLI::success( sprintf(
			/* Translators: %1$s is the product name. */
			__( 'Product license for "%1$s" has been updated.', 'nexcess-mapps' ),
			$product
		) );
	}

	/**
	 * Remove an existing iThemes product license.
	 *
	 * ## OPTIONS
	 *
	 * <product>
	 * : The iThemes product.
	 *
	 * ## EXAMPLES
	 *
	 *   wp nxmapps ithemes delete backupbuddy
	 *
	 * @param string[] $args Positional arguments.
	 */
	public function delete( $args ) {
		$product = $args[0];
		$current = get_option( self::OPTION_NAME, [] );

		unset( $current[ $product ] );

		update_option( self::OPTION_NAME, $current );

		return WP_CLI::success( sprintf(
			/* Translators: %1$s is the product name. */
			__( 'Product license for "%1$s" has been removed.', 'nexcess-mapps' ),
			$product
		) );
	}
}
