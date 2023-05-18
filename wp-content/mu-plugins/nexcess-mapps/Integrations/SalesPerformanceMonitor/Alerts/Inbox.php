<?php
/**
 * Handle sending Woo inbox alerts.
 */

namespace Nexcess\MAPPS\Integrations\SalesPerformanceMonitor\Alerts;

use Nexcess\MAPPS\Integrations\SalesPerformanceMonitor;

class Inbox {
	/**
	 * Send our midpoint alert.
	 *
	 * @param array $checkpoint_args The arguments for adding an entry.
	 * @param array $revenue_results The results of our revenue checks.
	 *
	 * @return mixed
	 */
	public static function send_midpoint_inbox_alert( $checkpoint_args = [], $revenue_results = [] ) {

		// Check for Admin Note support.
		if ( ! class_exists( '\Automattic\WooCommerce\Admin\Notes\Note' ) ) {
			return;
		}

		// Confirm the "WC_Data_Store" class is set.
		if ( ! class_exists( '\WC_Data_Store' ) ) {
			return;
		}

		// Bail without our items.
		if ( empty( $checkpoint_args ) || empty( $revenue_results ) ) {
			return;
		}

		// We will construct the note here.
		$get_note_args = self::get_midpoint_inbox_args( $checkpoint_args, $revenue_results );

		// If we have no args, bail.
		if ( empty( $get_note_args ) ) {
			return;
		}

		// phpcs:disable
		/*
		// Set the Woo data store.
		$load_admin_notes   = \WC_Data_Store::load( 'admin-note' );

		// Check to see if our note already exists.
		$maybe_note_exists  = $load_admin_notes->get_notes_with_name( $get_note_args['name'] );

		// If we already have it, bail.
		if ( ! empty( $maybe_note_exists ) ) {
			return;
		}
		*/
		// phpcs:enable

		// Set the proper class for setting the alert.
		$set_woo_alert = new \Automattic\WooCommerce\Admin\Notes\Note();

		// Now set each individual part of the alert.
		$set_woo_alert->set_title( $get_note_args['title'] );
		$set_woo_alert->set_content( $get_note_args['content'] );
		$set_woo_alert->set_type( $get_note_args['type'] );
		$set_woo_alert->set_name( $get_note_args['name'] );
		$set_woo_alert->set_layout( $get_note_args['layout'] );
		$set_woo_alert->set_source( $get_note_args['source'] );

		// Add an image if one was included.
		if ( ! empty( $get_note_args['image'] ) ) {
			$set_woo_alert->set_image( $get_note_args['image'] );
		}

		// Include an action to look at the orders page.
		if ( ! empty( $get_note_args['action'] ) && ! empty( $get_note_args['orders'] ) ) {
			$set_woo_alert->add_action( $get_note_args['action'], __( 'View Recent Orders', 'nexcess-mapps' ), $get_note_args['orders'] );
		}

		// And save the note.
		$set_woo_alert->save();
		return true;
	}

	/**
	 * Send our week end alert.
	 *
	 * @param array $checkpoint_args The arguments for adding an entry.
	 * @param array $revenue_results The results of our revenue checks.
	 *
	 * @return mixed
	 */
	public static function send_week_end_inbox_alert( $checkpoint_args = [], $revenue_results = [] ) {

		// Check for Admin Note support.
		if ( ! class_exists( '\Automattic\WooCommerce\Admin\Notes\Note' ) ) {
			return;
		}

		// Confirm the "WC_Data_Store" class is set.
		if ( ! class_exists( '\WC_Data_Store' ) ) {
			return;
		}

		// Bail without our items.
		if ( empty( $checkpoint_args ) || empty( $revenue_results ) ) {
			return;
		}

		// We will construct the note here.
		$get_note_args = self::get_week_end_inbox_args( $checkpoint_args, $revenue_results );

		// If we have no args, bail.
		if ( empty( $get_note_args ) ) {
			return;
		}

		// phpcs:disable
		/*
		// Set the Woo data store.
		$load_admin_notes   = \WC_Data_Store::load( 'admin-note' );

		// Check to see if our note already exists.
		$maybe_note_exists  = $load_admin_notes->get_notes_with_name( $get_note_args['name'] );

		// If we already have it, bail.
		if ( ! empty( $maybe_note_exists ) ) {
			return;
		}
		*/
		// phpcs:enable

		// Set the proper class for setting the alert.
		$set_woo_alert = new \Automattic\WooCommerce\Admin\Notes\Note();

		// Now set each individual part of the alert.
		$set_woo_alert->set_title( $get_note_args['title'] );
		$set_woo_alert->set_content( $get_note_args['content'] );
		$set_woo_alert->set_type( $get_note_args['type'] );
		$set_woo_alert->set_name( $get_note_args['name'] );
		$set_woo_alert->set_layout( $get_note_args['layout'] );
		$set_woo_alert->set_source( $get_note_args['source'] );

		// Add an image if one was included.
		if ( ! empty( $get_note_args['image'] ) ) {
			$set_woo_alert->set_image( $get_note_args['image'] );
		}

		// Include an action to look at the orders page.
		if ( ! empty( $get_note_args['action'] ) && ! empty( $get_note_args['orders'] ) ) {
			$set_woo_alert->add_action( $get_note_args['action'], __( 'View Recent Orders', 'nexcess-mapps' ), $get_note_args['orders'] );
		}

		// And save the note.
		$set_woo_alert->save();
		return true;
	}

	/**
	 * Build out the arguments for a midpoint alert.
	 *
	 * @param array $checkpoint_args The arguments for adding an entry.
	 * @param array $revenue_results The results of our revenue checks.
	 *
	 * @return array
	 */
	public static function get_midpoint_inbox_args( $checkpoint_args = [], $revenue_results = [] ) {

		// Construct the individual args.
		$set_note_args = [
			'title'   => Content::get_midpoint_subject( $checkpoint_args, $revenue_results ),
			'content' => Content::get_midpoint_content( $checkpoint_args, $revenue_results, false ),
			'name'    => SalesPerformanceMonitor::WOO_INBOX_NOTE_ID,
			'type'    => 'info', // maybe also 'warning'?
			'source'  => SalesPerformanceMonitor::WOO_INBOX_SOURCE,
			'layout'  => 'plain',
			'image'   => '',
			'action'  => SalesPerformanceMonitor::WOO_INBOX_ACTION,
			'orders'  => add_query_arg( [ 'post_type' => 'shop_order' ], admin_url( '/edit.php' ) ),
		];

		// Return it filtered.
		return apply_filters( SalesPerformanceMonitor::HOOK_PREFIX . 'midpoint_alert_inbox_args', $set_note_args, $checkpoint_args, $revenue_results );
	}

	/**
	 * Build out the arguments for a week end alert.
	 *
	 * @param array $checkpoint_args The arguments for adding an entry.
	 * @param array $revenue_results The results of our revenue checks.
	 *
	 * @return array
	 */
	public static function get_week_end_inbox_args( $checkpoint_args = [], $revenue_results = [] ) {

		// Construct the individual args.
		$set_note_args = [
			'title'   => Content::get_week_end_subject( $checkpoint_args, $revenue_results ),
			'content' => Content::get_week_end_content( $checkpoint_args, $revenue_results, false ),
			'name'    => SalesPerformanceMonitor::WOO_INBOX_NOTE_ID,
			'type'    => 'info', // maybe also 'warning'?
			'source'  => SalesPerformanceMonitor::WOO_INBOX_SOURCE,
			'layout'  => 'plain',
			'image'   => '',
			'action'  => SalesPerformanceMonitor::WOO_INBOX_ACTION,
			'orders'  => add_query_arg( [ 'post_type' => 'shop_order' ], admin_url( '/edit.php' ) ),
		];

		// Return it filtered.
		return apply_filters( SalesPerformanceMonitor::HOOK_PREFIX . 'week_end_alert_inbox_args', $set_note_args, $checkpoint_args, $revenue_results );
	}
}
