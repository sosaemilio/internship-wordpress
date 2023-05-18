<?php
/**
 * The functions related to setting up our database storage.
 */

namespace Nexcess\MAPPS\Integrations\SalesPerformanceMonitor\Database;

use Nexcess\MAPPS\Integrations\SalesPerformanceMonitor;

class Setup {
	/**
	 * Check to see if we need to run the initial install on tables.
	 *
	 * @return bool
	 */
	public static function maybe_install_tables() {

		// Set the array of table names.
		$tables = [ 'checkpoints' ];

		// Now loop my tables and check.
		foreach ( $tables as $table_name ) {

			// Check if the table exists.
			$table_exists = self::maybe_table_exists( $table_name );

			// If it exists, skip it.
			if ( false !== $table_exists ) {
				continue;
			}

			// Now run the actual install.
			$table_install = self::install_single_table( $table_name );

			// Bail if we had a false install.
			if ( false === $table_install ) {
				return false;
			}
		}

		// Run the update schema keys.
		update_option( SalesPerformanceMonitor::SCHEMA_KEY, SalesPerformanceMonitor::DB_VERS );

		return true;
	}

	/**
	 * Confirm that the table itself actually exists.
	 *
	 * @param string $table_name The name of the specific table.
	 *
	 * @return bool
	 */
	public static function maybe_table_exists( $table_name = '' ) {

		// Bail if we don't have a name to check.
		if ( empty( $table_name ) ) {
			return false;
		}
		// Call the global class.
		global $wpdb;

		// Set table name.
		$table = $wpdb->prefix . SalesPerformanceMonitor::TABLE_PREFIX . esc_attr( $table_name );

		// Return the result of the var lookup.
		return $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $table ) ) ) === $table;
	}

	/**
	 * Compare the stored version of the database schema.
	 */
	public static function maybe_update_tables() {
		/*
		We're already updated and current, so nothing here.
		if ( (int) get_option( SalesPerformanceMonitor::SCHEMA_KEY ) === (int) SalesPerformanceMonitor::DB_VERS ) {
			// Run the update setups.
		}
		*/
	}

	/**
	 * Install a single table, as needed.
	 *
	 * @param string $table_name The table name we wanna install.
	 *
	 * @return bool
	 */
	public static function install_single_table( $table_name = '' ) {

		// Bail if we don't have a name to check.
		if ( empty( $table_name ) ) {
			return false;
		}

		// Handle the table install based on the provided name.
		switch ( sanitize_text_field( $table_name ) ) {

			case 'checkpoints':
				return self::install_checkpoints_table();

			// No more case breaks, no more tables.
		}

		// Hit the end, so false.
		return false;
	}

	/**
	 * Create our custom table to store the checkpoint data.
	 *
	 * @return bool
	 */
	public static function install_checkpoints_table() {

		// Pull in the upgrade functions.
		if ( ! function_exists( 'dbDelta' ) ) {
			require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		}

		// Load the WPDB global.
		global $wpdb;

		// Pull our character set and collating.
		$char_coll = $wpdb->get_charset_collate();

		// Set our table name.
		$table_name = $wpdb->prefix . SalesPerformanceMonitor::TABLE_PREFIX . 'checkpoints';

		// Setup the SQL syntax.
		//
		// This stores the individual checkpoints.
		//
		$table_args = "
			CREATE TABLE {$table_name} (
				check_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
				check_date datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
				target_rev bigint(30) NOT NULL DEFAULT 0,
				actual_rev bigint(30) NOT NULL DEFAULT 0,
				rev_variance float(6) NOT NULL DEFAULT 0,
			PRIMARY KEY  (check_id)
			) $char_coll;
		";

		// Create the actual table.
		$run_query = dbDelta( $table_args );

		// Now run the confirm and return the value.
		return self::maybe_table_exists( 'checkpoints' );
	}

	/**
	 * Return the primary key name for each table.
	 *
	 * @param string $table_name Which table we want to look in.
	 *
	 * @return mixed
	 */
	public static function get_primary_key( $table_name = '' ) {

		// Set up the array.
		$primary_keys = [
			'checkpoints' => 'check_id',
		];

		// If we didn't specify a table name, return the whole thing.
		if ( empty( $table_name ) ) {
			return $primary_keys;
		}

		// Now return the single item or false if it doesn't exist.
		return ! empty( $primary_keys[ $table_name ] ) ? $primary_keys[ $table_name ] : false;
	}

	/**
	 * Set each required item for a database insert.
	 *
	 * @param string $table_name  Which table we want the args for.
	 * @param string $return_type What return type is requested.
	 *
	 * @return array
	 */
	public static function get_required_table_args( $table_name = '', $return_type = '' ) {

		// Set up the array for the form table.
		$checkp_submit_args = [
			'check_date'   => '%s',
			'target_rev'   => '%d',
			'actual_rev'   => '%d',
			'rev_variance' => '%s',
		];

		// Define which set of args we want based on the request.
		$defined_table_args = $checkp_submit_args;

		// Return the requested setup.
		switch ( sanitize_text_field( $return_type ) ) {

			case 'formats':
				return array_values( $defined_table_args );

			case 'columns':
				return array_keys( $defined_table_args );

			case 'dataset':
				return $defined_table_args;

			// No more case breaks, no more tables.
		}

		// Bail even though we shouldn't be here.
		return [];
	}
}
