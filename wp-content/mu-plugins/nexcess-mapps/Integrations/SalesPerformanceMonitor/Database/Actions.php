<?php
/**
 * The functionality related to setting up our DB table.
 */

namespace Nexcess\MAPPS\Integrations\SalesPerformanceMonitor\Database;

use WP_Error;

class Actions {
	/**
	 * Insert a new form submission into our database.
	 *
	 * @param array $insert_args The individual bits we wanna include.
	 *
	 * @return mixed \WP_Error on failure, else record ID
	 */
	public static function insert_checkpoint( $insert_args = [] ) {

		// Make sure we have args.
		if ( empty( $insert_args ) || ! is_array( $insert_args ) ) {
			return new WP_Error( 'missing-insert-args', __( 'The required database arguments are missing or invalid.', 'nexcess-mapps' ) );
		}

		// Confirm our args are set up properly.
		$confirm_args = Functions::confirm_insert_args( $insert_args, 'checkpoints' );

		// Handle an empty or false return with a new WP Error object.
		if ( false === $confirm_args || empty( $confirm_args ) ) {
			return new WP_Error( 'invalid-insert-args', __( 'The provided database arguments were not properly formatted.', 'nexcess-mapps' ) );
		}

		// Return the existing WP Error object.
		if ( is_wp_error( $confirm_args ) ) {
			return $confirm_args;
		}

		// Format all my data.
		$insert_args = Functions::format_data_for_action( $insert_args, 'checkpoints' );

		// Call the global DB.
		global $wpdb;

		// Set our table formatting.
		$table_format = Setup::get_required_table_args( 'checkpoints', 'formats' );

		// Run my insert function.
		$wpdb->insert( $wpdb->prefix . 'wc_spm_checkpoints', $insert_args, $table_format );

		// Check for the ID and throw an error if we don't have it.
		if ( ! $wpdb->insert_id ) {
			return new WP_Error( 'database-insert-error', __( 'The data could not be written to the database.', 'nexcess-mapps' ) );
		}

		// Return the new ID.
		return $wpdb->insert_id;
	}

	/**
	 * Update an existing record in our database.
	 *
	 * @param int   $update_id   The ID of the thing we are updating.
	 * @param array $update_args The individual bits we wanna include.
	 *
	 * @return mixed \WP_Error on failure, else string if rows are affected.
	 */
	public static function update_checkpoint( $update_id = 0, $update_args = [] ) {

		// Make sure we have an ID.
		if ( empty( $update_id ) ) {
			return new WP_Error( 'missing-update-id', __( 'The required ID was missing or invalid.', 'nexcess-mapps' ) );
		}

		// Make sure we have args.
		if ( empty( $update_args ) || ! is_array( $update_args ) ) {
			return new WP_Error( 'missing-update-args', __( 'The required database arguments are missing or invalid.', 'nexcess-mapps' ) );
		}

		// Make sure it exists.
		$maybe_exists = Functions::maybe_id_exists( $update_id, 'checkpoints' );

		// If the ID doesn't exist, bail.
		if ( ! $maybe_exists ) {
			return new WP_Error( 'invalid-update-id', __( 'The provided ID does not exist in the database.', 'nexcess-mapps' ) );
		}

		// Confirm our args are set up properly.
		$confirm_args = Functions::confirm_update_args( $update_args, 'checkpoints' );

		// Handle an empty or false return with a new WP Error object.
		if ( false === $confirm_args || empty( $confirm_args ) ) {
			return new WP_Error( 'invalid-insert-args', __( 'The provided database arguments were not properly formatted.', 'nexcess-mapps' ) );
		}

		// Return the existing WP Error object.
		if ( is_wp_error( $confirm_args ) ) {
			return $confirm_args;
		}

		// Format all my data.
		$update_args = Functions::format_data_for_action( $update_args, 'checkpoints' );

		// Call the global DB.
		global $wpdb;

		// Set our table formatting.
		$table_format = Setup::get_required_table_args( 'checkpoints', 'formats' );

		// Run the update process.
		$wpdb->update( $wpdb->prefix . 'wc_spm_checkpoints', $update_args, [ 'check_id' => absint( $update_id ) ], $table_format, [ '%d' ] );

		// Return the error if we got one.
		if ( ! empty( $wpdb->last_error ) ) {
			return new WP_Error( 'database-update-error', __( 'The item could not be updated in the database.', 'nexcess-mapps' ) );
		}

		// Return a boolean based on the rows affected count.
		return ! empty( $wpdb->rows_affected ) ? 'updated' : 'unchanged';
	}

	/**
	 * Delete an existing record in our database.
	 *
	 * @param int $delete_id The ID of the thing we are deleting.
	 *
	 * @return mixed \WP_Error on failure, else boolean for whether rows are affected.
	 */
	public static function delete_checkpoint( $delete_id = 0 ) {

		// Make sure we have args.
		if ( empty( $delete_id ) ) {
			return new WP_Error( 'missing-delete-id', __( 'The required ID was missing or invalid.', 'nexcess-mapps' ) );
		}

		// Make sure it exists.
		$maybe_exists = Functions::maybe_id_exists( $delete_id, 'checkpoints', 'check_id' );

		// If the ID doesn't exist, bail.
		if ( ! $maybe_exists ) {
			return new WP_Error( 'invalid-delete-id', __( 'The provided ID does not exist in the database.', 'nexcess-mapps' ) );
		}

		// Call the global DB.
		global $wpdb;

		// Run my delete function.
		$wpdb->delete( $wpdb->prefix . 'wc_spm_checkpoints', [ 'check_id' => absint( $delete_id ) ] );

		// Return the error if we got one.
		if ( ! empty( $wpdb->last_error ) ) {
			return new WP_Error( 'database-delete-error', __( 'The item could not be deleted in the database.', 'nexcess-mapps' ) );
		}

		// Return a boolean based on the rows affected count.
		return ! empty( $wpdb->rows_affected ) ? true : false;
	}

}
