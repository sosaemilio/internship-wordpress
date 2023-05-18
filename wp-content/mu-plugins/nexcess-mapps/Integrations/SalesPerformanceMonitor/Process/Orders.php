<?php
/**
 * The various order queries we run inside the plugin.
 */

namespace Nexcess\MAPPS\Integrations\SalesPerformanceMonitor\Process;

use Nexcess\MAPPS\Integrations\SalesPerformanceMonitor;
use Nexcess\MAPPS\Integrations\SalesPerformanceMonitor\Utilities;

class Orders {

	/**
	 * Get the order / revenue data to handle our initial calculations.
	 *
	 * @param bool $purge Whether or not to purge the cache.
	 *
	 * @return mixed
	 */
	public static function query_batch_set_revenue( $purge = false ) {

		// Set the key to use in our transient.
		$ky = SalesPerformanceMonitor::TRANSIENT_PREFIX . 'batch_set';

		// If we don't want the cache'd version, delete the transient first.
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG || ! empty( $purge ) ) {
			delete_transient( $ky );
		}

		// Attempt to get the reviews from the cache.
		$cached_dataset = get_transient( $ky );

		// If we have none, do the things.
		if ( false === $cached_dataset ) {

			// Get the timestamp from the most recent Monday.
			$setup_finish_stamp = Utilities::get_midpoint_timestamp( 'monday' );

			// Set a 2 month (8 week) backfill of orders.
			$setup_start_stamp = absint( $setup_finish_stamp ) - ( WEEK_IN_SECONDS * 8 );

			// Set the args for a specific lookup.
			$setup_single_args = [
				'limit'        => -1,
				'return'       => 'ids',
				'type'         => 'shop_order',
				'status'       => [ 'wc-completed' ],
				'date_created' => $setup_start_stamp . '...' . $setup_finish_stamp,
			];

			// Now run our lookup.
			$run_query_lookup = new \WC_Order_Query( $setup_single_args );

			// Bail out if none exist.
			if ( empty( $run_query_lookup->get_orders() ) || is_wp_error( $run_query_lookup ) ) {
				return false;
			}

			// Now fetch all the orders.
			$fetch_batch_orders = $run_query_lookup->get_orders();

			// Return the actual zero dollar for no orders.
			if ( empty( $fetch_batch_orders ) ) {
				return 0;
			}

			// Set our zero.
			$set_revenue_total = 0;

			// Now loop and pull out the total.
			foreach ( $fetch_batch_orders as $batch_order_id ) {

				// Get my complete order object.
				$get_order_object = wc_get_order( $batch_order_id );

				// And pull the total.
				$set_revenue_total += $get_order_object->get_total();
			}

			// Set our transient with our data.
			set_transient( $ky, $set_revenue_total, HOUR_IN_SECONDS );

			// And change the variable to do the things.
			$cached_dataset = $set_revenue_total;
		}

		// Return a formatted numeric version.
		return number_format( $cached_dataset, 2, '.', '' );
	}

	/**
	 * Get the order / revenue data for the previous week.
	 *
	 * @param bool $purge Whether or not to purge the cache.
	 *
	 * @return mixed
	 */
	public static function query_previous_week_revenue( $purge = false ) {

		// Set the key to use in our transient.
		$ky = SalesPerformanceMonitor::TRANSIENT_PREFIX . 'prev_week_' . gmdate( 'z' );

		// If we don't want the cache'd version, delete the transient first.
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG || ! empty( $purge ) ) {
			delete_transient( $ky );
		}

		// Attempt to get the reviews from the cache.
		$cached_dataset = get_transient( $ky );

		// If we have none, do the things.
		if ( false === $cached_dataset ) {

			// Get the timestamp from the most recent Monday.
			$setup_finish_stamp = Utilities::get_midpoint_timestamp( 'monday' );

			// Set a single week backfill of orders.
			$setup_start_stamp = absint( $setup_finish_stamp ) - WEEK_IN_SECONDS;

			// Set the args for a specific lookup.
			$setup_single_args = [
				'limit'        => -1,
				'return'       => 'ids',
				'type'         => 'shop_order',
				'status'       => [ 'wc-completed' ],
				'date_created' => $setup_start_stamp . '...' . $setup_finish_stamp,
			];

			// Now run our lookup.
			$run_query_lookup = new \WC_Order_Query( $setup_single_args );

			// Bail out if none exist.
			if ( empty( $run_query_lookup->get_orders() ) || is_wp_error( $run_query_lookup ) ) {
				return false;
			}

			// Now fetch all the orders.
			$fetch_batch_orders = $run_query_lookup->get_orders();

			// Return the actual zero dollar for no orders.
			if ( empty( $fetch_batch_orders ) ) {
				return 0;
			}

			// Set our zero.
			$set_revenue_total = 0;

			// Now loop and pull out the total.
			foreach ( $fetch_batch_orders as $batch_order_id ) {

				// Get my complete order object.
				$get_order_object = wc_get_order( $batch_order_id );

				// And pull the total.
				$set_revenue_total += $get_order_object->get_total();
			}

			// Set our transient with our data.
			set_transient( $ky, $set_revenue_total, HOUR_IN_SECONDS );

			// And change the variable to do the things.
			$cached_dataset = $set_revenue_total;
		}

		// Return a formatted numeric version.
		return number_format( $cached_dataset, 2, '.', '' );
	}

	/**
	 * Get the order / revenue data at the midpoint, which
	 * is current set up as EOD Wednesday.
	 *
	 * @param bool $purge Whether or not to purge the cache.
	 *
	 * @return mixed
	 */
	public static function query_midpoint_revenue( $purge = false ) {

		// Set the key to use in our transient.
		$ky = SalesPerformanceMonitor::TRANSIENT_PREFIX . 'mdp_rev_' . gmdate( 'z' );

		// If we don't want the cache'd version, delete the transient first.
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG || ! empty( $purge ) ) {
			delete_transient( $ky );
		}

		// Attempt to get the reviews from the cache.
		$cached_dataset = get_transient( $ky );

		// If we have none, do the things.
		if ( false === $cached_dataset ) {

			// Get our timestamp from today.
			$setup_finish_stamp = Utilities::get_midpoint_timestamp( 'thursday' );

			// Set a three day backfill of orders to get Monday through Wednesday.
			$setup_start_stamp = absint( $setup_finish_stamp ) - ( DAY_IN_SECONDS * 3 );

			// Set the args for a specific lookup.
			$setup_single_args = [
				'limit'        => -1,
				'return'       => 'ids',
				'type'         => 'shop_order',
				'status'       => [ 'wc-completed' ],
				'date_created' => $setup_start_stamp . '...' . $setup_finish_stamp,
			];

			// Now run our lookup.
			$run_query_lookup = new \WC_Order_Query( $setup_single_args );

			// Bail out if none exist.
			if ( empty( $run_query_lookup->get_orders() ) || is_wp_error( $run_query_lookup ) ) {
				return false;
			}

			// Now fetch all the orders.
			$fetch_batch_orders = $run_query_lookup->get_orders();

			// Return the actual zero dollar for no orders.
			if ( empty( $fetch_batch_orders ) ) {
				return 0;
			}

			// Set our zero.
			$set_revenue_total = 0;

			// Now loop and pull out the total.
			foreach ( $fetch_batch_orders as $batch_order_id ) {

				// Get my complete order object.
				$get_order_object = wc_get_order( $batch_order_id );

				// And pull the total.
				$set_revenue_total += $get_order_object->get_total();
			}

			// Set our transient with our data.
			set_transient( $ky, $set_revenue_total, HOUR_IN_SECONDS );

			// And change the variable to do the things.
			$cached_dataset = $set_revenue_total;
		}

		// Return a formatted numeric version.
		return number_format( $cached_dataset, 2, '.', '' );
	}

	/**
	 * Get the order / revenue data at the week end, which
	 * is current set up as EOD Friday.
	 *
	 * @param bool $purge Whether or not to purge the cache.
	 *
	 * @return mixed
	 */
	public static function query_week_end_revenue( $purge = false ) {

		// Set the key to use in our transient.
		$ky = SalesPerformanceMonitor::TRANSIENT_PREFIX . 'wkd_rev_' . gmdate( 'z' );

		// If we don't want the cache'd version, delete the transient first.
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG || ! empty( $purge ) ) {
			delete_transient( $ky );
		}

		// Attempt to get the reviews from the cache.
		$cached_dataset = get_transient( $ky );

		// If we have none, do the things.
		if ( false === $cached_dataset ) {

			// Get our timestamp from today.
			$setup_finish_stamp = Utilities::get_midpoint_timestamp( 'saturday' );

			// Set a  backfill of orders.
			$setup_start_stamp = absint( $setup_finish_stamp ) - ( DAY_IN_SECONDS * 5 );

			// Set the args for a specific lookup.
			$setup_single_args = [
				'limit'        => -1,
				'return'       => 'ids',
				'type'         => 'shop_order',
				'status'       => [ 'wc-completed' ],
				'date_created' => $setup_start_stamp . '...' . $setup_finish_stamp,
			];

			// Now run our lookup.
			$run_query_lookup = new \WC_Order_Query( $setup_single_args );

			// Bail out if none exist.
			if ( empty( $run_query_lookup->get_orders() ) || is_wp_error( $run_query_lookup ) ) {
				return false;
			}

			// Now fetch all the orders.
			$fetch_batch_orders = $run_query_lookup->get_orders();

			// Return the actual zero dollar for no orders.
			if ( empty( $fetch_batch_orders ) ) {
				return 0;
			}

			// Set our zero.
			$set_revenue_total = 0;

			// Now loop and pull out the total.
			foreach ( $fetch_batch_orders as $batch_order_id ) {

				// Get my complete order object.
				$get_order_object = wc_get_order( $batch_order_id );

				// And pull the total.
				$set_revenue_total += $get_order_object->get_total();
			}

			// Set our transient with our data.
			set_transient( $ky, $set_revenue_total, HOUR_IN_SECONDS );

			// And change the variable to do the things.
			$cached_dataset = $set_revenue_total;
		}

		// Return a formatted numeric version.
		return number_format( $cached_dataset, 2, '.', '' );
	}

	/**
	 * Get the order / revenue data using a specific range.
	 *
	 * @param int  $start_stamp  The timestamp of when to begin.
	 * @param int  $finish_stamp When the finishing range should end.
	 * @param bool $round_result Whether or not to round the result.
	 *
	 * @return mixed
	 */
	public static function query_revenue_in_range( $start_stamp = 0, $finish_stamp = 0, $round_result = true ) {

		// Bail without any stamps.
		if ( empty( $start_stamp ) || empty( $finish_stamp ) ) {
			return false;
		}

		// Set the args for a specific lookup.
		// This query has no cached transient because it's custom.
		$setup_single_args = [
			'limit'        => -1,
			'return'       => 'ids',
			'type'         => 'shop_order',
			'status'       => [ 'wc-completed' ],
			'date_created' => absint( $start_stamp ) . '...' . absint( $finish_stamp ),
		];

		// Now run our lookup.
		$run_query_lookup = new \WC_Order_Query( $setup_single_args );

		// Bail out if none exist.
		if ( empty( $run_query_lookup->get_orders() ) || is_wp_error( $run_query_lookup ) ) {
			return false;
		}

		// Now fetch all the orders.
		$fetch_batch_orders = $run_query_lookup->get_orders();

		// Return the actual zero dollar for no orders.
		if ( empty( $fetch_batch_orders ) ) {
			return 0;
		}

		// Set our zero.
		$set_revenue_total = 0;

		// Now loop and pull out the total.
		foreach ( $fetch_batch_orders as $batch_order_id ) {

			// Get my complete order object.
			$get_order_object = wc_get_order( $batch_order_id );

			// And pull the total.
			$set_revenue_total += $get_order_object->get_total();
		}

		// Return a formatted numeric version.
		return false !== $round_result ? Utilities::get_rounded_revenue( $set_revenue_total ) : number_format( $set_revenue_total, 2, '.', '' );
	}

	/**
	 * Get the order / revenue data using a specific range.
	 *
	 * @param int  $finish_stamp When the finishing range should end.
	 * @param bool $round_result Whether or not to round the result.
	 *
	 * @return mixed
	 */
	public static function query_revenue_from_finish( $finish_stamp = 0, $round_result = true ) {

		// Get our timestamp from today.
		$setup_finish_stamp = ! empty( $finish_stamp ) ? $finish_stamp : Utilities::get_midpoint_timestamp( 'monday' );

		// Bail without any stamp.
		if ( empty( $setup_finish_stamp ) ) {
			return false;
		}

		// Set a 2 month (8 week) backfill of orders.
		$setup_start_stamp = absint( $setup_finish_stamp ) - ( WEEK_IN_SECONDS * 8 );

		// Set the args for a specific lookup.
		// This query has no cached transient because it's custom.
		$setup_single_args = [
			'limit'        => -1,
			'return'       => 'ids',
			'type'         => 'shop_order',
			'status'       => [ 'wc-completed' ],
			'date_created' => absint( $setup_start_stamp ) . '...' . absint( $setup_finish_stamp ),
		];

		// Now run our lookup.
		$run_query_lookup = new \WC_Order_Query( $setup_single_args );

		// Bail out if none exist.
		if ( empty( $run_query_lookup->get_orders() ) || is_wp_error( $run_query_lookup ) ) {
			return false;
		}

		// Now fetch all the orders.
		$fetch_batch_orders = $run_query_lookup->get_orders();

		// Return the actual zero dollar for no orders.
		if ( empty( $fetch_batch_orders ) ) {
			return 0;
		}

		// Set our zero.
		$set_revenue_total = 0;

		// Now loop and pull out the total.
		foreach ( $fetch_batch_orders as $batch_order_id ) {

			// Get my complete order object.
			$get_order_object = wc_get_order( $batch_order_id );

			// And pull the total.
			$set_revenue_total += $get_order_object->get_total();
		}

		// Return a formatted numeric version.
		return false !== $round_result ? Utilities::get_rounded_revenue( $set_revenue_total ) : number_format( $set_revenue_total, 2, '.', '' );
	}
}
