<?php
/**
 * Handle sending email alerts.
 */

namespace Nexcess\MAPPS\Integrations\SalesPerformanceMonitor\Alerts;

use Nexcess\MAPPS\Integrations\SalesPerformanceMonitor;

class Email {
	/**
	 * Send our midpoint email.
	 *
	 * @param array $checkpoint_args The arguments for adding an entry.
	 * @param array $revenue_results The results of our revenue checks.
	 *
	 * @return mixed
	 */
	public static function send_midpoint_email_alert( $checkpoint_args = [], $revenue_results = [] ) {

		// Bail without our items.
		if ( empty( $checkpoint_args ) || empty( $revenue_results ) ) {
			return;
		}

		// Get my to address and email headers.
		$email_to_addr = self::get_email_to_address();
		$email_headers = self::get_email_headers();

		// Pull my subject.
		$email_subject = Content::get_midpoint_subject( $checkpoint_args, $revenue_results );

		// And pull the content.
		$email_content = Content::get_midpoint_content( $checkpoint_args, $revenue_results );

		// Now attempt to send the actual email.
		return wp_mail( $email_to_addr, $email_subject, $email_content, $email_headers );
	}

	/**
	 * Send our week end email.
	 *
	 * @param array $checkpoint_args The arguments for adding an entry.
	 * @param array $revenue_results The results of our revenue checks.
	 *
	 * @return mixed
	 */
	public static function send_week_end_email_alert( $checkpoint_args = [], $revenue_results = [] ) {

		// Bail without our items.
		if ( empty( $checkpoint_args ) || empty( $revenue_results ) ) {
			return;
		}

		// Get my to address and email headers.
		$email_to_addr = self::get_email_to_address();
		$email_headers = self::get_email_headers();

		// Pull my subject.
		$email_subject = Content::get_week_end_subject( $checkpoint_args, $revenue_results );

		// And pull the content.
		$email_content = Content::get_week_end_content( $checkpoint_args, $revenue_results );

		// Now attempt to send the actual email.
		return wp_mail( $email_to_addr, $email_subject, $email_content, $email_headers );
	}

	/**
	 * Get the email address we wanna use for our alert.
	 *
	 * @return string
	 */
	public static function get_email_to_address() {

		// Check the Woo filter for email.
		$maybe_use_woo = apply_filters( SalesPerformanceMonitor::HOOK_PREFIX . 'alert_email_use_woo_from', false );

		// Pull the appropriate email.
		$default_email = false !== $maybe_use_woo ? get_option( 'woocommerce_email_from_address' ) : get_option( 'admin_email' );

		// Return it filtered.
		return apply_filters( SalesPerformanceMonitor::HOOK_PREFIX . 'alert_email_to_address', $default_email );
	}

	/**
	 * Build a basic name.
	 *
	 * @return string
	 */
	public static function get_email_from_name() {

		// Now set the name.
		$set_from_name = __( 'Sales Performance Monitor', 'nexcess-mapps' );

		// Return it filtered.
		return apply_filters( SalesPerformanceMonitor::HOOK_PREFIX . 'alert_email_from_name', $set_from_name );
	}

	/**
	 * Get the address we need to mail from.
	 *
	 * @return string
	 */
	public static function get_email_from_address() {

		// First pull the domain and parse it.
		$get_site_home = wp_parse_url( network_home_url(), PHP_URL_HOST );

		// Strip the opening WWW if it exists.
		if ( 'www.' === substr( $get_site_home, 0, 4 ) ) {
			$get_site_home = substr( $get_site_home, 4 );
		}

		// Now set up the email.
		$set_from_email = 'wordpress@' . $get_site_home;

		// Return it filtered.
		return apply_filters( SalesPerformanceMonitor::HOOK_PREFIX . 'alert_email_from_address', $set_from_email );
	}

	/**
	 * Set up our standard return headers.
	 *
	 * @return string
	 */
	public static function get_email_headers() {

		// Set the from name.
		$set_from_name  = self::get_email_from_name();
		$set_from_email = self::get_email_from_address();

		// Now set my headers.
		$set_headers[] = 'Content-Type: text/html; charset=UTF-8';
		/* Translators: %1$s is the From name, %2$s is the From email. */
		$set_headers[] = sprintf( __( 'From: %1$s <%2$s>', 'nexcess-mapps' ), esc_attr( $set_from_name ), $set_from_email );

		// Return it filtered.
		return apply_filters( SalesPerformanceMonitor::HOOK_PREFIX . 'alert_email_headers', $set_headers );
	}

}
