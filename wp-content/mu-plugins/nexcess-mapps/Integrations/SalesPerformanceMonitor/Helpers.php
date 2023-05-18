<?php
/**
 * Our helper functions to use across the plugin.
 */

namespace Nexcess\MAPPS\Integrations\SalesPerformanceMonitor;

use Nexcess\MAPPS\Integrations\SalesPerformanceMonitor;

class Helpers {

	/**
	 * Check to see if WooCommerce is installed and active.
	 *
	 * @return bool
	 */
	public static function maybe_woo_activated() {
		return class_exists( 'woocommerce' ) ? true : false;
	}

	/**
	 * Check to see if the initial setup has been run.
	 *
	 * @return bool
	 */
	public static function maybe_initial_setup_done() {

		// Pull the option value.
		$maybe_done = get_option( SalesPerformanceMonitor::OPTION_PREFIX . 'initial_setup', false );

		// Return the result in a strict boolean.
		return ! empty( $maybe_done ) && 'done' === sanitize_text_field( $maybe_done ) ? true : false;
	}

	/**
	 * Check to see if we need to run a checkpoint.
	 *
	 * @return mixed Array on success, else boolean false
	 */
	public static function maybe_run_checkpoint() {

		// First get the last date we checked.
		$get_last_check = get_option( SalesPerformanceMonitor::OPTION_PREFIX . 'range_last', 0 );

		// Bail without a date to check against.
		if ( empty( $get_last_check ) ) {
			return false;
		}

		// Now calculate the next checkpoint timestamp.
		$calc_next_check = Utilities::get_next_checkpoint_stamp( $get_last_check );

		// If we aren't there yet, just return false.
		if ( time() < absint( $calc_next_check ) ) {
			return false;
		}

		// Get the timestamp of the weekly start rate.
		$get_start_date = get_option( SalesPerformanceMonitor::OPTION_PREFIX . 'range_start', false );

		// Set the array and return it.
		return [
			'start' => $get_start_date,
			'last'  => $get_last_check,
			'next'  => $calc_next_check,
			'days'  => Utilities::get_day_count_from_range( $get_start_date, $calc_next_check ),
		];
	}

	/**
	 * Take an 8 week batch of data and calculate the targets.
	 *
	 * @param int $revenue_total The overall total.
	 *
	 * @return mixed Array on success, else boolean false
	 */
	public static function format_batch_data_for_targets( $revenue_total = 0 ) {

		// Bail without a provided total.
		if ( empty( $revenue_total ) ) {
			return false;
		}

		// Set our initial weekly averages.
		$calc_week_average = floatval( $revenue_total ) / 8;

		// Now format our values.
		$format_week_avg = Utilities::get_rounded_revenue( $calc_week_average );

		// Now calculate the daily average.
		$calc_daily_average = floatval( $format_week_avg ) / 7;

		// And now format the daily to the nearest 10.
		$format_daily_avg = Utilities::get_rounded_revenue( $calc_daily_average, 10 );

		// Return a formatted array.
		return [
			'daily'  => $format_daily_avg,
			'weekly' => $format_week_avg,
		];
	}

	/**
	 * Get the value we have for our base comparison.
	 *
	 * @param string $compare_window What window we wanna compare.
	 *
	 * @return mixed Int on success, else boolean false.
	 */
	public static function fetch_base_compare_value( $compare_window = '' ) {

		// Bail without a provided window.
		if ( empty( $compare_window ) ) {
			return false;
		}

		// Get our base values.
		$get_base_vals = get_option( SalesPerformanceMonitor::OPTION_PREFIX . 'base_values', 0 );

		// Bail without values to work with.
		if ( empty( $get_base_vals ) ) {
			return false;
		}

		// Now run down each one.
		switch ( sanitize_text_field( $compare_window ) ) {

			// Handle our possible daily check.
			case 'day':
			case 'daily':
				return ! empty( $get_base_vals['daily'] ) ? $get_base_vals['daily'] : false;

			// Handle our possible weekly check.
			case 'week':
			case 'weekly':
				return ! empty( $get_base_vals['weekly'] ) ? $get_base_vals['weekly'] : false;
		}

		// Throw a false if they didn't provide a valid window.
		return false;
	}

	/**
	 * Get the value we have for our adjusted comparison.
	 *
	 * @param string $compare_window What window we wanna compare.
	 *
	 * @return mixed Int on success, else boolean false.
	 */
	public static function fetch_adjusted_compare_value( $compare_window = '' ) {

		// Bail without a provided window.
		if ( empty( $compare_window ) ) {
			return false;
		}

		// Now run down each one.
		switch ( sanitize_text_field( $compare_window ) ) {

			// Handle our possible daily check.
			case 'day':
			case 'daily':
				return get_option( SalesPerformanceMonitor::OPTION_PREFIX . 'current_daily', 0 );

			// Handle our possible weekly check.
			case 'week':
			case 'weekly':
				return get_option( SalesPerformanceMonitor::OPTION_PREFIX . 'current_weekly', 0 );

		}

		// Throw a false if they didn't provide a valid window.
		return false;
	}

	/**
	 * Get the values we first calculated to set the checkpoint.
	 *
	 * @return array
	 */
	public static function fetch_initial_checkpoint_values() {

		// Get the stamp value.
		$get_stamp_val = get_option( SalesPerformanceMonitor::OPTION_PREFIX . 'range_start' );

		// Get my initial weekly target.
		$get_target_val = get_option( SalesPerformanceMonitor::OPTION_PREFIX . 'current_weekly' );

		// Return the values.
		return [
			'date'   => gmdate( 'Y-m-d H:i:s', absint( $get_stamp_val ) ),
			'target' => $get_target_val,
		];
	}

	/**
	 * Calculate a new target based on the variance.
	 *
	 * @param int  $original_target  What the original target was.
	 * @param int  $percent_variance What my variance is.
	 * @param bool $return_rounded   Whether or not to return the number rounded.
	 *
	 * @return mixed Int on success, else boolean false.
	 */
	public static function calculate_new_target_value( $original_target = 0, $percent_variance = 0, $return_rounded = true ) {

		// Bail without a target number.
		if ( empty( $original_target ) ) {
			return false;
		}

		// If our variance is actually zero, then just return the target.
		if ( empty( $percent_variance ) || 0 === absint( $percent_variance ) ) {
			return $original_target;
		}

		// Calculate the target change.
		$target_change = ( absint( $original_target ) / 100 ) * floatval( $percent_variance );

		// Now set the new target.
		$set_new_target = $original_target + $target_change;

		// Return them added up, and rounded to the nearest 10.
		return false !== $return_rounded ? Utilities::get_rounded_revenue( $set_new_target, 10 ) : $set_new_target;
	}

	/**
	 * Make sure we clean out anything from a numeric string.
	 *
	 * @param string $num_string The string we wanna fix.
	 *
	 * @return int
	 */
	public static function clean_numeric_string( $num_string = '' ) {

		// Strip out string.
		$remove_chars = preg_replace( '/[^a-z0-9 ]/i', '', $num_string );

		// Filter it with some PHP.
		$set_filtered = filter_var( $remove_chars, FILTER_SANITIZE_NUMBER_INT );

		// Return it ensured as a integer.
		return absint( $set_filtered );
	}
}
