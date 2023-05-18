<?php

namespace Nexcess\MAPPS\Services\Importers;

use StellarWP\PluginFramework\Exceptions\IngestionException;
use StellarWP\PluginFramework\Exceptions\WPErrorException;
use WC_Product_CSV_Importer_Controller;

class WooCommerceProductImporter {

	/**
	 * Import products using a local CSV file.
	 *
	 * This method acts as a wrapper around the core WooCommerce CSV product importer, with
	 * improved error handling.
	 *
	 * @param string $csv The system path to the CSV file.
	 *
	 * @throws \StellarWP\PluginFramework\Exceptions\IngestionException If the CSV file cannot be imported.
	 *
	 * @return array[] {
	 *
	 *   An array containing products that were created, updated, skipped, or that failed.
	 *
	 *   @see WC_Product_CSV_Importer::import()
	 *
	 *   @type WP_Error[] $failed   An array of WP_Error objects describing any failures.
	 *   @type int[]      $imported The IDs of any newly-imported products.
	 *   @type WP_Error[] $skipped  An array of WP_Error objects describing any skipped products.
	 *   @type int[]      $updated  The IDs of any updated products.
	 * }
	 */
	public function import( $csv ) {
		if ( ! is_readable( $csv ) ) {
			throw new IngestionException( sprintf( 'Unable to read %s, aborting.', $csv ), 404 );
		}

		$this->includes();

		try {
			$results = WC_Product_CSV_Importer_Controller::get_importer( $csv, [
				'parse' => true,
			] )->import();
		} catch ( \Exception $e ) {
			throw new IngestionException(
				sprintf( 'Unable to import products from %1$s: %2$s', $csv, $e->getMessage() ),
				$e->getCode(),
				$e
			);
		}

		// Report any errors.
		if ( ! empty( $results['failed'] ) ) {
			$messages = array_map( function ( $error ) {
				return is_wp_error( $error )
					? sprintf( '%1$s (row %2$d)', $error->get_error_message(), $error->get_error_data( 'row' ) )
					: 'Unspecified (failure was not a WP_Error object)';
			}, $results['failed'] );

			throw new IngestionException( sprintf(
				"The following error(s) occurred while importing products:\n -%1\$s",
				implode( "\n- ", $messages )
			) );
		}

		return $results;
	}

	/**
	 * Import products using a CSV URL.
	 *
	 * @param string $url The remote URL for the attachment.
	 *
	 * @throws \StellarWP\PluginFramework\Exceptions\IngestionException If the products cannot be imported.
	 * @throws \StellarWP\PluginFramework\Exceptions\WPErrorException   If the products cannot be imported.
	 */
	public function importFromUrl( $url ) {
		add_filter( 'woocommerce_csv_product_import_valid_filetypes', [ $this, 'filterValidCsvTypes' ] );

		try {
			$csv = download_url( $url );

			if ( is_wp_error( $csv ) ) {
				throw new WPErrorException( $csv );
			}

			$results = $this->import( $csv );

			// Unlink the temporary CSV file.
			if ( file_exists( $csv ) ) {
				unlink( $csv );
			}
		} catch ( \Exception $e ) {
			throw new IngestionException(
				sprintf( 'Unable to import products from %1$s: %2$s', $url, $e->getMessage() ),
				$e->getCode(),
				$e
			);
		}

		return $results;
	}

	/**
	 * Import products using a CSV on the filesystem.
	 *
	 * @param string $path The local file path for the attachment.
	 *
	 * @throws \StellarWP\PluginFramework\Exceptions\IngestionException If the products cannot be imported.
	 */
	public function importFromLocalFile( $path ) {
		add_filter( 'woocommerce_csv_product_import_valid_filetypes', [ $this, 'filterValidCsvTypes' ] );

		if ( ! file_exists( $path ) ) {
			throw new IngestionException( sprintf( 'Unable to read %s, aborting.', $path ), 404 );
		}

		return $this->import( $path );
	}

	/**
	 * Treat .tmp as a valid extension for CSV files.
	 *
	 * By default, WooCommerce is expecting files with .csv or .txt extensions, but download_url()
	 * will produce temp files with .tmp extensions.
	 *
	 * @param string[] $types Valid extension => MIME-type mappings.
	 *
	 * @return string[] The filtered $types array.
	 */
	public function filterValidCsvTypes( $types ) {
		$types['tmp'] = 'text/csv';

		return $types;
	}

	/**
	 * Load files required by the importer.
	 */
	protected function includes() {
		require_once ABSPATH . 'wp-admin/includes/file.php';

		if ( defined( 'WC_ABSPATH' ) && WC_ABSPATH ) {
			require_once WC_ABSPATH . 'includes/admin/importers/class-wc-product-csv-importer-controller.php';
			require_once WC_ABSPATH . 'includes/import/class-wc-product-csv-importer.php';
		}
	}
}
