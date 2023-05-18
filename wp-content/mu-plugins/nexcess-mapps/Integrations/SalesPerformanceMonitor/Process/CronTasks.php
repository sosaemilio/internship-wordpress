<?php
/**
 * Handle our WP-Cron integration.
 */

namespace Nexcess\MAPPS\Integrations\SalesPerformanceMonitor\Process;

use Nexcess\MAPPS\Integrations\SalesPerformanceMonitor;

class CronTasks {

	/**
	 * Set our first time cron to run.
	 */
	public static function set_initial_checkpoint_cron() {

		// Confirm we are clear first.
		self::clear_existing_cron( SalesPerformanceMonitor::FIRST_TIME_CRON );

		// Set the timestamp to run in 1 minute.
		$set_initial_stamp = time() + MINUTE_IN_SECONDS;

		// Now schedule our new one with the timestamp we just defined.
		wp_schedule_single_event( $set_initial_stamp, SalesPerformanceMonitor::FIRST_TIME_CRON );
	}

	/**
	 * Set the cron for the ongoing checkpoint runs.
	 */
	public static function set_ongoing_checkpoints_cron() {

		// Confirm we are clear first.
		self::clear_existing_cron( SalesPerformanceMonitor::CHECKPOINT_CRON );

		// Now schedule our new one with our custom new frequency.
		wp_schedule_event( time(), 'fourhours', SalesPerformanceMonitor::CHECKPOINT_CRON );
	}

	/**
	 * Clear out an existing cron entry before setting a new one.
	 *
	 * @param string $cron_name Which one we wanna do.
	 */
	public static function clear_existing_cron( $cron_name = '' ) {

		// Bail without a name to check.
		if ( empty( $cron_name ) ) {
			return;
		}

		// Grab the next scheduled stamp.
		$maybe_has_schedule = wp_next_scheduled( $cron_name );

		// If we have one, remove it from the schedule first.
		if ( ! empty( $maybe_has_schedule ) ) {
			wp_unschedule_event( $maybe_has_schedule, $cron_name );
		}
	}
}
