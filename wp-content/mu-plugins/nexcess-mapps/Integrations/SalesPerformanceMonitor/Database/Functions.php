<?php
/**
 * Our general database functions.
 */

namespace Nexcess\MAPPS\Integrations\SalesPerformanceMonitor\Database;

use Nexcess\MAPPS\Integrations\SalesPerformanceMonitor;
use Nexcess\MAPPS\Integrations\SalesPerformanceMonitor\Helpers;
use WP_Error;

class Functions {
	/**
	 * Check if a given ID exists in a column on a table.
	 *
	 * @param int    $lookup_id   The ID of the thing we are checking.
	 * @param string $table_name  Which table we want to look in.
	 * @param string $column_name The column we want (if we wanna check it specific).
	 *
	 * @return mixed \WP_Error on failure, else boolean of whether ID exists.
	 */
	public static function maybe_id_exists( $lookup_id = 0, $table_name = '', $column_name = '' ) {

		// Bail if we don't have the lookup ID.
		if ( empty( $lookup_id ) ) {
			return new WP_Error( 'missing-lookup-id', __( 'The required ID was missing or invalid.', 'nexcess-mapps' ) );
		}

		// Bail if we don't have the table name.
		if ( empty( $table_name ) ) {
			return new WP_Error( 'missing-table-name', __( 'The required table name was missing or invalid.', 'nexcess-mapps' ) );
		}

		// If we didn't get a column name, set the variable based on the table.
		if ( empty( $column_name ) ) {
			$column_name = Setup::get_primary_key( $table_name );
		}

		// Make sure we have a column.
		if ( empty( $column_name ) ) {
			return new WP_Error( 'missing-column-name', __( 'The required column name is missing.', 'nexcess-mapps' ) );
		}

		// Call the global class.
		global $wpdb;

		// Set my table name.
		$table_name = $wpdb->prefix . SalesPerformanceMonitor::TABLE_PREFIX . esc_attr( $table_name );

		// Set up our query.
		$query_run = $wpdb->get_col( $wpdb->prepare('
			SELECT   COUNT(*)
			FROM     %s
			WHERE    %s = %d
		', $table_name, $column_name, absint( $lookup_id ) ) );

		// Return the result.
		return ! empty( $query_run[0] ) ? true : false;
	}

	/**
	 * Check that we have the required args for a DB insert.
	 *
	 * @param array  $insert_args The data we have to insert.
	 * @param string $table_name  Which table we want to insert in.
	 *
	 * @return mixed
	 */
	public static function confirm_insert_args( $insert_args = [], $table_name = '' ) {

		// Bail if we don't have args to check.
		if ( empty( $insert_args ) ) {
			return new WP_Error( 'missing-insert-args', __( 'The required arguments were not provided.', 'nexcess-mapps' ) );
		}

		// Bail if we don't have the table name.
		if ( empty( $table_name ) ) {
			return new WP_Error( 'missing-table-name', __( 'The required table name was missing or invalid.', 'nexcess-mapps' ) );
		}

		// Get the requirements for the table.
		$required_keys = Setup::get_required_table_args( $table_name, 'columns' );

		// Bail without the args to check.
		if ( ! $required_keys ) {
			return new WP_Error( 'no-required-args', __( 'No required arguments could be found.', 'nexcess-mapps' ) );
		}

		// Loop our requirements and check.
		foreach ( $insert_args as $insert_key => $insert_arg ) {

			// Check if it is in the array.
			if ( in_array( $insert_key, $required_keys, true ) ) {
				continue;
			}

			// Set a variable for the arg display if returned.
			$arg_formatted = '<code>' . esc_attr( $insert_key ) . '</code>';

			// Not in the array? Return the error.
			/* Translators: %1$s is the missing key. */
			return new WP_Error( 'missing-required-arg', sprintf( __( 'The required %1$s argument is missing.', 'nexcess-mapps' ), $arg_formatted ) );
		}

		// Now make sure none are missing data, since all are needed.
		foreach ( $required_keys as $required_key ) {

			// Check the array for an actual value.
			// Except variance, which can be zero.
			if ( 'rev_variance' === $required_key || ! empty( $insert_args[ $required_key ] ) ) {
				continue;
			}

			// Set a variable for the arg display if returned.
			$arg_formatted = '<code>' . esc_attr( $required_key ) . '</code>';

			// Not in the array? Return the error.
			/* Translators: %1$s is the key with a missing value. */
			return new WP_Error( 'missing-required-data', sprintf( __( 'The required %s argument has no value.', 'nexcess-mapps' ), $arg_formatted ) );
		}

		// We good!
		return true;
	}

	/**
	 * Check that we have the required args for a DB update.
	 *
	 * @param array  $update_args What the specific args are.
	 * @param string $table_name  Which table we want to insert in.
	 *
	 * @return mixed
	 */
	public static function confirm_update_args( $update_args = [], $table_name = '' ) {

		// Bail if we don't have args to check.
		if ( empty( $update_args ) ) {
			return new WP_Error( 'missing-update-args', __( 'The required arguments were not provided.', 'nexcess-mapps' ) );
		}

		// Bail if we don't have the table name.
		if ( empty( $table_name ) ) {
			return new WP_Error( 'missing-table-name', __( 'The required table name was missing or invalid.', 'nexcess-mapps' ) );
		}

		// Get the requirements for the table.
		$required_keys = Setup::get_required_table_args( $table_name, 'columns' );

		// Bail without the args to check.
		if ( ! $required_keys ) {
			return new WP_Error( 'no-required-args', __( 'No required arguments could be found.', 'nexcess-mapps' ) );
		}

		// Loop the args we have present and check.
		foreach ( $update_args as $update_key => $update_arg ) {

			// Check if it is in the array.
			if ( in_array( $update_key, $required_keys, true ) ) {
				continue;
			}

			// Set a variable for the arg display if returned.
			$arg_formatted = '<code>' . esc_attr( $update_key ) . '</code>';

			// Not in the array? Return the error.
			/* Translators: %1$s is the invalid key. */
			return new WP_Error( 'invalid-arg-provided', sprintf( __( 'The %s argument is not valid for this table.', 'nexcess-mapps' ), $arg_formatted ) );
		}

		// We good!
		return true;
	}

	/**
	 * Take our incoming dataset and handle any formatting.
	 *
	 * @param array  $action_args The data we have to insert / update.
	 * @param string $table_name  Which table we want to insert in.
	 *
	 * @return mixed
	 */
	public static function format_data_for_action( $action_args = [], $table_name = '' ) {

		// Bail if we don't have args to check.
		if ( empty( $action_args ) ) {
			return new WP_Error( 'missing-action-data', __( 'The required dataset was not provided.', 'nexcess-mapps' ) );
		}

		// Bail if we don't have the table name.
		if ( empty( $table_name ) ) {
			return new WP_Error( 'missing-table-name', __( 'The required table name was missing or invalid.', 'nexcess-mapps' ) );
		}

		// Pull our formatting rules.
		$get_format_rules = Setup::get_required_table_args( $table_name, 'dataset' );

		// Bail if we don't have rules to check against.
		if ( empty( $get_format_rules ) ) {
			return new WP_Error( 'missing-format-rules', __( 'The required formatting rules were not found.', 'nexcess-mapps' ) );
		}

		// Loop my args and check.
		foreach ( $action_args as $action_column => $action_value ) {

			// If we don't have a sanitization rule for this column, skip.
			if ( ! isset( $get_format_rules[ $action_column ] ) ) {
				continue;
			}

			// Set the type of sanitization.
			$set_val_type = sanitize_text_field( $get_format_rules[ $action_column ] );

			// Now switch and handle various validations.
			switch ( $set_val_type ) {

				case '%s':
					$updated_value = sanitize_text_field( $action_value );
					break;

				case '%d':
					$updated_value = Helpers::clean_numeric_string( $action_value );
					break;

				default:
					$updated_value = sanitize_text_field( $action_value );
					break;
			}

			// Now swap out the array value, trimming white space.
			$action_args[ $action_column ] = trim( strval( $updated_value ) );
		}

		// Return the array.
		return $action_args;
	}
}
