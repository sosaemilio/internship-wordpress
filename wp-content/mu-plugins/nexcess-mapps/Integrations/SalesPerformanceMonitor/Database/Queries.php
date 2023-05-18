<?php
/**
 * The specific queries we are going to do on our table.
 */

namespace Nexcess\MAPPS\Integrations\SalesPerformanceMonitor\Database\Queries;

use Nexcess\MAPPS\Integrations\SalesPerformanceMonitor;
use WP_Error;

class Queries {
	/**
	 * Just get a count of the checkpoints.
	 *
	 * @param bool $purge Optional to purge the cache'd version before looking up.
	 *
	 * @return mixed
	 */
	public static function get_checkpoint_count( $purge = false ) {

		// Set the key to use in our transient.
		$ky = SalesPerformanceMonitor::TRANSIENT_PREFIX . 'chk_count';

		// If we don't want the cache'd version, delete the transient first.
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG || ! empty( $purge ) ) {
			delete_transient( $ky );
		}

		// Attempt to get the data from the cache.
		$cached_dataset = get_transient( $ky );

		// If we have none, do the things.
		if ( false === $cached_dataset ) {

			// Call the global database.
			global $wpdb;

			// Set our table name.
			$table_name = $wpdb->prefix . 'wc_spm_checkpoints';

			// Set up our query.
			$query_run = $wpdb->get_var($wpdb->prepare('
				SELECT   COUNT(*)
				FROM     %s
			', $table_name));

			// Bail without any data.
			if ( empty( $query_run ) ) {
				return false;
			}

			// Set our transient with our data.
			set_transient( $ky, $query_run, HOUR_IN_SECONDS );

			// And change the variable to do the things.
			$cached_dataset = $query_run;
		}

		// And just return the result.
		return $cached_dataset;
	}

	/**
	 * Get all the data.
	 *
	 * @param string $return_type What type of return we want. Accepts "counts", "dataset", or specific fields.
	 * @param bool   $purge       Optional to purge the cache'd version before looking up.
	 *
	 * @return mixed
	 */
	public static function get_all_checkpoints( $return_type = 'dataset', $purge = false ) {

		// Set the key to use in our transient.
		$ky = SalesPerformanceMonitor::TRANSIENT_PREFIX . 'all_chks';

		// If we don't want the cache'd version, delete the transient first.
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG || ! empty( $purge ) ) {
			delete_transient( $ky );
		}

		// Attempt to get the data from the cache.
		$cached_dataset = get_transient( $ky );

		// If we have none, do the things.
		if ( false === $cached_dataset ) {

			// Call the global database.
			global $wpdb;

			// Set our table name.
			$table_name = $wpdb->prefix . 'wc_spm_checkpoints';

			// Set up our query.
			$query_run = $wpdb->get_results($wpdb->prepare('
				SELECT   *
				FROM     %s
				ORDER BY check_date ASC
			', $table_name), ARRAY_A );

			// Bail without any data.
			if ( empty( $query_run ) ) {
				return false;
			}

			// Set our transient with our data.
			set_transient( $ky, $query_run, HOUR_IN_SECONDS );

			// And change the variable to do the things.
			$cached_dataset = $query_run;
		}

		// Now switch between my return types.
		switch ( sanitize_text_field( $return_type ) ) {

			case 'counts':
				return count( $cached_dataset );

			case 'dataset':
				return $cached_dataset;

			case 'ids':
				return wp_list_pluck( $cached_dataset, 'submit_id' );

			// No more case breaks, no more return types.
		}

		// No reason we should get down this far but here we go.
		return false;
	}

	/**
	 * Get one checkpoint.
	 *
	 * @param int  $check_id The checkpoint ID we are looking up.
	 * @param bool $purge    Optional to purge the cache'd version before looking up.
	 *
	 * @return mixed
	 */
	public static function get_single_checkpoint_by_id( $check_id = 0, $purge = false ) {

		// Bail without an ID.
		if ( empty( $check_id ) ) {
			return new WP_Error( 'missing-check-id', __( 'An ID is required.', 'nexcess-mapps' ) );
		}

		// Set the key to use in our transient.
		$ky = SalesPerformanceMonitor::TRANSIENT_PREFIX . 'sng_' . absint( $check_id );

		// If we don't want the cache'd version, delete the transient first.
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG || ! empty( $purge ) ) {
			delete_transient( $ky );
		}

		// Attempt to get the data from the cache.
		$cached_dataset = get_transient( $ky );

		// If we have none, do the things.
		if ( false === $cached_dataset ) {

			// Call the global database.
			global $wpdb;

			// Set our table name.
			$table_name = $wpdb->prefix . 'wc_spm_checkpoints';

			// Set up our query.
			$query_run = $wpdb->get_results( $wpdb->prepare('
				SELECT   *
				FROM     %s
				WHERE    check_id = %d
			', $table_name, absint( $check_id ) ), ARRAY_A );

			// Bail without any data.
			if ( empty( $query_run ) || empty( $query_run[0] ) ) {
				return false;
			}

			// Set our transient with our data.
			set_transient( $ky, $query_run[0], HOUR_IN_SECONDS );

			// And change the variable to do the things.
			$cached_dataset = $query_run[0];
		}

		// And return my dataset.
		return $cached_dataset;
	}

	/**
	 * Get the most recent checkpoint date.
	 *
	 * @param string $return_type What type of return we want. Accepts "timestamp" or "database".
	 * @param bool   $purge       Optional to purge the cache'd version before looking up.
	 *
	 * @return mixed
	 */
	public static function get_last_checkpoint_date( $return_type = 'timestamp', $purge = false ) {

		// Set the key to use in our transient.
		$ky = SalesPerformanceMonitor::TRANSIENT_PREFIX . 'last_check_date';

		// If we don't want the cache'd version, delete the transient first.
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG || ! empty( $purge ) ) {
			delete_transient( $ky );
		}

		// Attempt to get the data from the cache.
		$cached_dataset = get_transient( $ky );

		// If we have none, do the things.
		if ( false === $cached_dataset ) {

			// Call the global database.
			global $wpdb;

			// Set our table name.
			$table_name = $wpdb->prefix . 'wc_spm_checkpoints';

			// Set up our query.
			$query_run = $wpdb->get_var($wpdb->prepare('
				SELECT   check_date
				FROM     %s
				ORDER BY check_date DESC
			', $table_name ) );

			// Bail without any data.
			if ( empty( $query_run ) ) {
				return false;
			}

			// Set our transient with our data.
			set_transient( $ky, $query_run, HOUR_IN_SECONDS );

			// And change the variable to do the things.
			$cached_dataset = $query_run;
		}

		// Return it either as-is or as a timestamp.
		return 'timestamp' === sanitize_text_field( $return_type ) ? strtotime( $cached_dataset ) : $cached_dataset;
	}

	/**
	 * Get the most recent checkpoint target.
	 *
	 * @param bool $purge Optional to purge the cache'd version before looking up.
	 *
	 * @return mixed
	 */
	public static function get_last_checkpoint_target( $purge = false ) {

		// Call the global database.
		global $wpdb;

		// Set our table name.
		$table_name = $wpdb->prefix . 'wc_spm_checkpoints';

		// Set up our query.
		$query_run = $wpdb->get_var($wpdb->prepare('
			SELECT   target_rev
			FROM     %s
			ORDER BY check_date DESC
		', $table_name ) );

		// Bail without any data.
		if ( empty( $query_run ) ) {
			return false;
		}

		// Return the result.
		return $query_run;
	}
}
