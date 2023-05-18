<?php
/**
 * Our utility functions to use across the plugin.
 */

namespace Nexcess\MAPPS\Integrations\SalesPerformanceMonitor;

use Nexcess\MAPPS\Integrations\SalesPerformanceMonitor;

class Utilities {

	/**
	 * Get the user cap for the actions.
	 *
	 * @return string
	 */
	public static function get_user_cap() {
		return apply_filters( SalesPerformanceMonitor::HOOK_PREFIX . 'user_menu_cap', 'manage_options' );
	}

	/**
	 * Get the timestamp of a specific day at midnight.
	 *
	 * @param string $formatted_date The date we wanna pull.
	 *
	 * @return mixed False if no formatted date, else int timestamp
	 */
	public static function get_single_timestamp( $formatted_date = '' ) {

		// Bail without the date.
		if ( empty( $formatted_date ) ) {
			return false;
		}

		// Determine how the date was provided and ensure it is a timestamp.
		$determine_date_set = is_integer( $formatted_date ) ? $formatted_date : intval( strtotime( $formatted_date ) );

		// Set the date as a formatted date.
		$setup_date_format = gmdate( 'Y-m-d', $determine_date_set );

		// Then add the zero'd time portion, and flip it back to a timestamp.
		$setup_date_stamp = strtotime( $setup_date_format . ' 00:00:00' );

		// Return it as an integer.
		return absint( $setup_date_stamp );
	}

	/**
	 * Get the timestamp of today at midnight.
	 *
	 * @return int
	 */
	public static function get_today_timestamp() {

		// Set today as a formatted date.
		$setup_date_format = gmdate( 'Y-m-d' );

		// Then add the zero'd time portion, and flip it back to a timestamp.
		$setup_date_stamp = strtotime( $setup_date_format . ' 00:00:00' );

		// Return it as an integer.
		return absint( $setup_date_stamp );
	}

	/**
	 * Get the timestamp for a specific midpoint in the tracking.
	 *
	 * @param string $weekday The midpoint (week day) we want.
	 *
	 * @return mixed False if no weekday, else int timestamp
	 */
	public static function get_midpoint_timestamp( $weekday = '' ) {

		// Bail without a weekday provided.
		if ( empty( $weekday ) ) {
			return false;
		}

		// Get the single digit representation of the day.
		$maybe_is_our_day = gmdate( 'N' );

		// Set a blank timestamp.
		$setup_date_stamp = 0;

		// Now switch.
		switch ( sanitize_text_field( $weekday ) ) {

			case 'monday':
				$setup_date_stamp = 1 === absint( $maybe_is_our_day ) ? self::get_today_timestamp() : strtotime( 'previous monday' );
				break;

			case 'thursday':
				$setup_date_stamp = 4 === absint( $maybe_is_our_day ) ? self::get_today_timestamp() : strtotime( 'previous thursday' );
				break;

			case 'saturday':
				$setup_date_stamp = 6 === absint( $maybe_is_our_day ) ? self::get_today_timestamp() : strtotime( 'previous saturday' );
				break;
		}

		// Return it as an integer.
		return absint( $setup_date_stamp );
	}

	/**
	 * Get the timestamp for the most recent Monday.
	 *
	 * @return int
	 */
	public static function get_monday_timestamp() {

		// First determine if today is a Monday.
		$maybe_is_our_day = gmdate( 'N' );

		// Get the appropriate stamp.
		$setup_date_stamp = 1 === absint( $maybe_is_our_day ) ? self::get_today_timestamp() : strtotime( 'previous monday' );

		// Return it as an integer.
		return absint( $setup_date_stamp );
	}

	/**
	 * Get the timestamp for the most recent Thursday.
	 *
	 * @return int
	 */
	public static function get_thursday_timestamp() {

		// First determine if today is a Thursday.
		$maybe_is_our_day = gmdate( 'N' );

		// Get the appropriate stamp.
		$setup_date_stamp = 4 === absint( $maybe_is_our_day ) ? self::get_today_timestamp() : strtotime( 'previous thursday' );

		// Return it as an integer.
		return absint( $setup_date_stamp );
	}

	/**
	 * Get the timestamp for the most recent Saturday.
	 *
	 * @return int
	 */
	public static function get_saturday_timestamp() {

		// First determine if today is a Thursday.
		$maybe_is_our_day = gmdate( 'N' );

		// Get the appropriate stamp.
		$setup_date_stamp = 6 === absint( $maybe_is_our_day ) ? self::get_today_timestamp() : strtotime( 'previous saturday' );

		// Return it as an integer.
		return absint( $setup_date_stamp );
	}

	/**
	 * Round a number up to the nearest multiple of 100.
	 *
	 * @param int|float $current_value Number to round.
	 * @param int       $round_limit   What the nearest value should be.
	 *
	 * @return mixed False if no value, else float.
	 */
	public static function get_rounded_revenue( $current_value = 0, $round_limit = 100 ) {

		// Bail without a value to round.
		if ( empty( $current_value ) ) {
			return false;
		}

		// Make sure we have a number.
		$define_limit_value = ! empty( $round_limit ) && is_integer( $round_limit ) ? $round_limit : 100;

		// And return the calculation.
		return ceil( $current_value / absint( $define_limit_value ) ) * absint( $define_limit_value );
	}

	/**
	 * Handle the math portion of our variance caluclations.
	 *
	 * @param int  $target_number  The target we wanted.
	 * @param int  $actual_number  What we actually had.
	 * @param bool $include_format Whether to include the percentage formatting.
	 *
	 * @return mixed Boolean false or string value.
	 */
	public static function get_percentage_variance( $target_number = 0, $actual_number = 0, $include_format = false ) {

		// Bail without a provided target or actual.
		if ( empty( $target_number ) || empty( $actual_number ) ) {
			return false;
		}

		// Handle my actual calculation.
		$calculate_raw_diff = absint( $actual_number ) - absint( $target_number );

		// Now calculate the percentage.
		$calculate_variance = ( floatval( $calculate_raw_diff ) / absint( $target_number ) ) * 100;

		// Set the value to be rounded.
		$set_rounded_value = round( $calculate_variance, 0, PHP_ROUND_HALF_UP );

		// If we asked for the formatted, do that check and return.
		if ( false !== $include_format ) {
			return floatval( $set_rounded_value ) < 1 ? $set_rounded_value . '%' : '+' . $set_rounded_value . '%';
		}

		// Now return the rounded value.
		return $set_rounded_value;
	}

	/**
	 * Get the next time a checkpoint is supposed to be run.
	 *
	 * @param int $last_checked A timestamp to use as our base.
	 *
	 * @return int
	 */
	public static function get_next_checkpoint_stamp( $last_checked = 0 ) {

		// Set the timestamp we need to compare against.
		$set_compare_stamp = ! empty( $last_checked ) ? $last_checked : time();

		// Get the single digit representation of the day.
		$get_comp_day_digit = gmdate( 'N', $set_compare_stamp );

		// Now switch.
		switch ( absint( $get_comp_day_digit ) ) {

			case 1:
			case 2:
			case 3:
				return strtotime( 'next thursday', $set_compare_stamp );

			case 4:
			case 5:
				return strtotime( 'next saturday', $set_compare_stamp );

			case 6:
				return strtotime( 'next monday', $set_compare_stamp );
		}

		// The only possible outcome is 1-6.
		return 0;
	}

	/**
	 * Determine how many days we have between stamps.
	 *
	 * @param int $start_stamp  The starting time.
	 * @param int $finish_stamp The finish time.
	 *
	 * @return mixed Boolean if no start/finish stamp, else string
	 */
	public static function get_day_count_from_range( $start_stamp = 0, $finish_stamp = 0 ) {

		// Bail without any stamps.
		if ( empty( $start_stamp ) || empty( $finish_stamp ) ) {
			return false;
		}

		// Set each as a formatted date.
		$start_date  = new \DateTime( gmdate( 'Y-m-d H:i:s', absint( $start_stamp ) ) );
		$finish_date = new \DateTime( gmdate( 'Y-m-d H:i:s', absint( $finish_stamp ) ) );

		// Return the absolute difference (our day count).
		return $finish_date->diff( $start_date );
	}

	/**
	 * Set the array of the alert types we want to use.
	 *
	 * @return array
	 */
	public static function get_alert_types() {

		// Define the alert types we have.
		$define_alert_types = [
			'email',
			'inbox',
		];

		// Return it, filtered.
		return apply_filters( SalesPerformanceMonitor::HOOK_PREFIX . 'alert_types', $define_alert_types );
	}
}
