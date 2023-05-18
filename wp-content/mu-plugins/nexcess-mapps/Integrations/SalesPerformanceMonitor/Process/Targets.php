<?php
/**
 * Manage the revenue target calculations.
 */

namespace Nexcess\MAPPS\Integrations\SalesPerformanceMonitor\Process;

use Nexcess\MAPPS\Integrations\SalesPerformanceMonitor;
use Nexcess\MAPPS\Integrations\SalesPerformanceMonitor\Helpers;
use Nexcess\MAPPS\Integrations\SalesPerformanceMonitor\Utilities;

class Targets {

	/**
	 * Run our initial setup to calculate our averages.
	 *
	 * @return string
	 */
	public static function calculate_initial_base_revenue() {

		// First check for the initial setup.
		$maybe_initial_done = Helpers::maybe_initial_setup_done();

		// Bail if the initial setup is already done.
		if ( false !== $maybe_initial_done ) {
			return 'done';
		}

		// Get the initial batch revenue data.
		$get_batch_revenue = Orders::query_batch_set_revenue();

		// Bail if we have no revenue to work with.
		if ( empty( $get_batch_revenue ) ) {
			return 'no-revenue';
		}

		// Now format the batch revenue.
		$set_batch_revenue = Helpers::format_batch_data_for_targets( $get_batch_revenue );

		// Now set each individual one.
		update_option( SalesPerformanceMonitor::OPTION_PREFIX . 'current_daily', $set_batch_revenue['daily'], 'no' );
		update_option( SalesPerformanceMonitor::OPTION_PREFIX . 'current_weekly', $set_batch_revenue['weekly'], 'no' );
		update_option( SalesPerformanceMonitor::OPTION_PREFIX . 'range_target', $set_batch_revenue['weekly'], 'no' );

		// Handle calculating the various timestamps we need.
		$set_initial_stamp = Utilities::get_midpoint_timestamp( 'monday' );

		// And store them.
		update_option( SalesPerformanceMonitor::OPTION_PREFIX . 'range_start', $set_initial_stamp, 'no' );
		update_option( SalesPerformanceMonitor::OPTION_PREFIX . 'range_last', $set_initial_stamp, 'no' );

		// Set our initial flag.
		update_option( SalesPerformanceMonitor::OPTION_PREFIX . 'initial_setup', 'done', 'no' );

		// Return the same flag we stored.
		return 'done';
	}

	/**
	 * Calculate the revenue variance at a midpoint check.
	 *
	 * @param array $checkpoint_args The arguments we had from a checkpoint.
	 *
	 * @return mixed False without valid args, or array of variances.
	 */
	public static function calculate_midpoint_revenue( $checkpoint_args = [] ) {

		// Bail without a valid set of arguments.
		if ( empty( $checkpoint_args ) ) {
			return false;
		}

		// Get the daily target we are working with.
		$get_daily_target_rev = Helpers::fetch_adjusted_compare_value( 'daily' );

		// Set our target for this checkpoint.
		$set_checkpoint_target = absint( $get_daily_target_rev ) * absint( $checkpoint_args['days'] );

		// Pull the revenue from the range provided.
		$get_revenue_from_range = Orders::query_revenue_in_range( $checkpoint_args['start'], $checkpoint_args['next'] );

		// Calculate the variance and return it.
		return [
			'target'   => $set_checkpoint_target,
			'actual'   => $get_revenue_from_range,
			'variance' => Utilities::get_percentage_variance( $set_checkpoint_target, $get_revenue_from_range ),
		];
	}

	/**
	 * Calculate and set a new daily and weekly amount at a midpoint.
	 *
	 * @param int $revenue_variance What the variance percentage was.
	 *
	 * @return bool
	 */
	public static function calculate_new_midpoint_targets( $revenue_variance = 0 ) {

		// If our variance is actually zero, then just return the target.
		if ( empty( $revenue_variance ) || 0 === absint( $revenue_variance ) ) {
			return false;
		}

		// Get the daily target we are working with.
		$get_current_daily = Helpers::fetch_adjusted_compare_value( 'daily' );

		// Calculate our new target value.
		$get_updated_daily = Helpers::calculate_new_target_value( $get_current_daily, $revenue_variance );

		// Bail if this doesn't come back.
		if ( empty( $get_updated_daily ) ) {
			return false;
		}

		// Set the new values.
		update_option( SalesPerformanceMonitor::OPTION_PREFIX . 'current_daily', $get_updated_daily, 'no' );
		update_option( SalesPerformanceMonitor::OPTION_PREFIX . 'current_weekly', ( $get_updated_daily * 7 ), 'no' );

		return true;
	}

	/**
	 * Calculate our week-end results.
	 *
	 * @param array $checkpoint_args The arguments we had from a checkpoint.
	 *
	 * @return mixed False without valid args, or array of data for entry.
	 */
	public static function calculate_week_end_revenue( $checkpoint_args = [] ) {

		// Bail without a valid set of arguments.
		if ( empty( $checkpoint_args ) ) {
			return false;
		}

		// Pull the revenue from the range provided.
		$get_revenue_from_range = Orders::query_revenue_in_range( $checkpoint_args['start'], $checkpoint_args['next'] );

		// Get my most recent weekly target.
		$get_recent_target_rev = get_option( SalesPerformanceMonitor::OPTION_PREFIX . 'range_target' );

		// And calculate the variance.
		$calculate_rev_variance = Utilities::get_percentage_variance( $get_recent_target_rev, $get_revenue_from_range );

		// Set up the data for a database entry and return it.
		return [
			'stamp'    => $checkpoint_args['next'],
			'target'   => $get_recent_target_rev,
			'actual'   => $get_revenue_from_range,
			'variance' => $calculate_rev_variance,
		];
	}

	/**
	 * Calculate and set a new daily and weekly amount at the week end.
	 *
	 * @param int $end_range_stamp What the weekend end range stamp was.
	 *
	 * @return bool
	 */
	public static function calculate_new_weekly_targets( $end_range_stamp = 0 ) {

		// Get the past 8 weeks of data.
		$get_revenue_from_range = Orders::query_revenue_from_finish( $end_range_stamp );

		// Calculate our new target value.
		$format_revenue_values = Helpers::format_batch_data_for_targets( $get_revenue_from_range );

		// Bail if this doesn't come back.
		if ( empty( $format_revenue_values ) ) {
			return false;
		}

		// Set the new values.
		update_option( SalesPerformanceMonitor::OPTION_PREFIX . 'current_daily', $format_revenue_values['daily'], 'no' );
		update_option( SalesPerformanceMonitor::OPTION_PREFIX . 'current_weekly', $format_revenue_values['weekly'], 'no' );
		update_option( SalesPerformanceMonitor::OPTION_PREFIX . 'range_target', $format_revenue_values['weekly'], 'no' );

		return true;
	}
}
