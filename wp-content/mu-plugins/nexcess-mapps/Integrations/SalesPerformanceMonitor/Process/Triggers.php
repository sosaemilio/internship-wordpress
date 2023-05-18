<?php
/**
 * Set up the specific triggers for running various queries and results.
 */

namespace Nexcess\MAPPS\Integrations\SalesPerformanceMonitor\Process;

use Nexcess\MAPPS\Integrations\SalesPerformanceMonitor;
use Nexcess\MAPPS\Integrations\SalesPerformanceMonitor\Alerts\Email;
use Nexcess\MAPPS\Integrations\SalesPerformanceMonitor\Alerts\Inbox;
use Nexcess\MAPPS\Integrations\SalesPerformanceMonitor\Database\Actions;
use Nexcess\MAPPS\Integrations\SalesPerformanceMonitor\Helpers;
use Nexcess\MAPPS\Integrations\SalesPerformanceMonitor\Utilities;

class Triggers {

	/**
	 * Set our first entry for a checkpoint.
	 *
	 * @return bool
	 */
	public static function add_initial_checkpoint_entry() {

		// Attempt to get the values.
		$get_initial_values = Helpers::fetch_initial_checkpoint_values();

		// Bail if we have no items to work with.
		if ( empty( $get_initial_values ) ) {
			return false;
		}

		// Now set up the arguments for the database.
		$setup_insert_args = [
			'check_date'   => $get_initial_values['date'],
			'target_rev'   => absint( $get_initial_values['target'] ),
			'actual_rev'   => absint( $get_initial_values['target'] ),
			'rev_variance' => 0,
		];

		// Try to attempt the insert.
		$attempt_new_insert = Actions::insert_checkpoint( $setup_insert_args );

		// Bail if it didn't set the first entry.
		if ( ! is_int( $attempt_new_insert ) ) {
			return false;
		}

		// Return true so we can continue.
		return true;
	}

	/**
	 * Set an individual entry.
	 *
	 * @param array $checkpoint_args The arguments for adding an entry.
	 *
	 * @return bool
	 */
	public static function add_ongoing_checkpoint_entry( $checkpoint_args = [] ) {

		// Bail if we have no items to work with.
		if ( empty( $checkpoint_args ) ) {
			return false;
		}

		// Calculate the variance.
		$calculate_variance = Utilities::get_percentage_variance( $checkpoint_args['target'], $checkpoint_args['actual'] );

		// Now set up the arguments for the database.
		$setup_insert_args = [
			'check_date'   => gmdate( 'Y-m-d H:i:s', absint( $checkpoint_args['stamp'] ) ),
			'target_rev'   => absint( $checkpoint_args['target'] ),
			'actual_rev'   => absint( $checkpoint_args['actual'] ),
			'rev_variance' => floatval( $calculate_variance ),
		];

		// Try to attempt the insert.
		$attempt_new_insert = Actions::insert_checkpoint( $setup_insert_args );

		// Bail if it didn't set the first entry.
		if ( ! is_int( $attempt_new_insert ) ) {
			return false;
		}

		// Return true so we can continue.
		return true;
	}

	/**
	 * Send the notification(s) for a midpoint check.
	 *
	 * @param array $checkpoint_args The arguments we had at the checkpoint.
	 * @param array $revenue_results Our midpoint revenue results.
	 *
	 * @return mixed
	 */
	public static function send_midpoint_check_results( $checkpoint_args = [], $revenue_results = [] ) {

		// Bail without results to use.
		if ( empty( $checkpoint_args ) || empty( $revenue_results ) ) {
			return false;
		}

		// Get our alert types.
		$get_alerts = Utilities::get_alert_types();

		// Bail if somehow we cleared this out.
		if ( empty( $get_alerts ) ) {
			return;
		}

		// If we didn't bypass the email, send it.
		if ( in_array( 'email', $get_alerts, true ) ) {
			Email::send_midpoint_email_alert( $checkpoint_args, $revenue_results );
		}

		// If we didn't bypass the Woo inbox alert, send it.
		if ( in_array( 'inbox', $get_alerts, true ) ) {
			Inbox::send_midpoint_inbox_alert( $checkpoint_args, $revenue_results );
		}

		// Include an action to add new alert types.
		do_action( SalesPerformanceMonitor::HOOK_PREFIX . 'midpoint_result_alert', $checkpoint_args, $revenue_results );
		return true;
	}

	/**
	 * Send the notification(s) for a week end check.
	 *
	 * @param array $checkpoint_args The arguments we had at the checkpoint.
	 * @param array $revenue_results Our week end revenue results.
	 *
	 * @return mixed
	 */
	public static function send_week_end_check_results( $checkpoint_args = [], $revenue_results = [] ) {

		// Bail without results to use.
		if ( empty( $checkpoint_args ) || empty( $revenue_results ) ) {
			return false;
		}

		// Get our alert types.
		$get_alerts = Utilities::get_alert_types();

		// Bail if somehow we cleared this out.
		if ( empty( $get_alerts ) ) {
			return;
		}

		// If we didn't bypass the email, send it.
		if ( in_array( 'email', $get_alerts, true ) ) {
			Email::send_week_end_email_alert( $checkpoint_args, $revenue_results );
		}

		// If we didn't bypass the Woo inbox alert, send it.
		if ( in_array( 'inbox', $get_alerts, true ) ) {
			Inbox::send_week_end_inbox_alert( $checkpoint_args, $revenue_results );
		}

		// Include an action to add new alert types.
		do_action( SalesPerformanceMonitor::HOOK_PREFIX . 'week_end_result_alert', $checkpoint_args, $revenue_results );
		return true;
	}
}
