<?php
/**
 * Set up and control the content inside the alerts.
 */

namespace Nexcess\MAPPS\Integrations\SalesPerformanceMonitor\Alerts;

use Nexcess\MAPPS\Integrations\SalesPerformanceMonitor;

class Content {

	/**
	 * Construct and return our subject line for a midpoint alert.
	 *
	 * @param array $checkpoint_args The arguments for adding an entry.
	 * @param array $revenue_results The results of our revenue checks.
	 *
	 * @return string
	 */
	public static function get_midpoint_subject( $checkpoint_args = [], $revenue_results = [] ) {

		// Set up a static subject line for now.
		$set_subject = __( 'Check Out This Week\'s Revenue Progress', 'nexcess-mapps' );

		// Return it filtered.
		return apply_filters( SalesPerformanceMonitor::HOOK_PREFIX . 'midpoint_alert_subject', $set_subject, $checkpoint_args, $revenue_results );
	}

	/**
	 * Construct and return the actual content body for a midpoint alert.
	 *
	 * @param array $checkpoint_args The arguments for adding an entry.
	 * @param array $revenue_results The results of our revenue checks.
	 * @param bool  $include_markup  Whether or not to include the markup.
	 *
	 * @return string
	 */
	public static function get_midpoint_content( $checkpoint_args = [], $revenue_results = [], $include_markup = true ) {

		// Set an empty to fill.
		$build_content = '';

		// Get the variance text portion first.
		$build_content .= self::get_midpoint_variance_message_text( $revenue_results['variance'] ) . "\n\n\n";

		// Now add the additional content.
		$build_content .= __( 'We\'ll continue to monitor and provide updates as the week goes on.', 'nexcess-mapps' ) . "\n\n";
		$build_content .= __( 'The Nexcess Team', 'nexcess-mapps' );

		// And set the text as is.
		$message_text = apply_filters( SalesPerformanceMonitor::HOOK_PREFIX . 'midpoint_alert_content', $build_content, $checkpoint_args, $revenue_results );

		// If we just want the content, return that.
		if ( false === $include_markup ) {
			return $message_text;
		}

		// Build our HTML.
		$build_html = '';

		// Do the opening tags.
		$build_html .= '<html>' . "\n";
		$build_html .= '<body>' . "\n";

		// Inject the content with some markup.
		$build_html .= wpautop( $message_text ) . "\n";

		// Close my tags.
		$build_html .= '</body>' . "\n";
		$build_html .= '</html>';

		// Now send it back with a second filter.
		return apply_filters( SalesPerformanceMonitor::HOOK_PREFIX . 'midpoint_alert_html_content', trim( $build_html ), $checkpoint_args, $revenue_results );
	}

	/**
	 * Construct and return our email subject for a weekend.
	 *
	 * @param array $checkpoint_args The arguments for adding an entry.
	 * @param array $revenue_results The results of our revenue checks.
	 *
	 * @return string
	 */
	public static function get_week_end_subject( $checkpoint_args = [], $revenue_results = [] ) {

		// Set up a static subject line for now.
		$set_subject = __( 'Check Out This Week\'s Revenue Report', 'nexcess-mapps' );

		// Return it filtered.
		return apply_filters( SalesPerformanceMonitor::HOOK_PREFIX . 'week_end_alert_subject', $set_subject, $checkpoint_args, $revenue_results );
	}

	/**
	 * Construct and return the actual email body.
	 *
	 * @param array $checkpoint_args The arguments for adding an entry.
	 * @param array $revenue_results The results of our revenue checks.
	 * @param bool  $include_markup  Whether or not to include the markup.
	 *
	 * @return mixed False if $revenue_results is empty, else a string of HTML content.
	 */
	public static function get_week_end_content( $checkpoint_args = [], $revenue_results = [], $include_markup = true ) {

		// Bail without results to use.
		if ( empty( $revenue_results ) ) {
			return false;
		}

		// Format all the values.
		$format_st_date = gmdate( 'F jS', absint( $revenue_results['stamp'] ) );
		$format_act_rev = '$' . number_format( absint( $revenue_results['actual'] ) );
		$format_act_trg = '$' . number_format( absint( $revenue_results['target'] ) );
		$format_rev_vrc = floatval( $revenue_results['variance'] ) . '%';

		// Add the above / below moniker.
		$format_rev_txt = floatval( $revenue_results['variance'] ) > 0 ? __( 'above', 'nexcess-mapps' ) : __( 'below', 'nexcess-mapps' );

		// Set an empty to fill.
		$build_content = '';

		// Build out the intro.
		/* Translators: %1$s is the formatted date. */
		$build_content .= sprintf( __( 'Here\'s your complete revenue report for the week ending %s:', 'nexcess-mapps' ), esc_attr( $format_st_date ) ) . "\n\n";

		// Now construct the message.
		$build_content .= sprintf(
			/* Translators: %1$s is the actual revenue, %2$s is the variance, %3$s is "above" or "below", %4$s is the revenue target. */
			__( 'Your ecommerce site generated %1$s of revenue, which is %2$s %3$s your target revenue of %4$s.', 'nexcess-mapps' ),
			esc_attr( $format_act_rev ),
			esc_attr( $format_rev_vrc ),
			esc_attr( $format_rev_txt ),
			esc_attr( $format_act_trg )
		) . "\n\n\n";

		// Now add the additional content.
		$build_content .= __( 'Your trending analysis for next week\'s targets will be sent to you in the next few days.', 'nexcess-mapps' ) . "\n\n";
		$build_content .= __( 'The Nexcess Team', 'nexcess-mapps' );

		// And set the text as is.
		$message_text = apply_filters( SalesPerformanceMonitor::HOOK_PREFIX . 'week_end_alert_content', $build_content, $checkpoint_args, $revenue_results );

		// If we just want the content, return that.
		if ( false === $include_markup ) {
			return $message_text;
		}

		// Build our HTML.
		$build_html = '';

		// Do the opening tags.
		$build_html .= '<html>' . "\n";
		$build_html .= '<body>' . "\n";

		// Inject the content with some markup.
		$build_html .= wpautop( $message_text ) . "\n";

		// Close my tags.
		$build_html .= '</body>' . "\n";
		$build_html .= '</html>';

		// Now send it back with a second filter.
		return apply_filters( SalesPerformanceMonitor::HOOK_PREFIX . 'week_end_alert_html_content', trim( $build_html ), $checkpoint_args, $revenue_results );
	}

	/**
	 * Set the dynamic message text for a midpoint notice.
	 *
	 * For now, the range percentage is hard coded. That could
	 * change in the future, but not at this point.
	 *
	 * @param int $revenue_variance The variance calculation from the checkpoint.
	 *
	 * @return string
	 */
	public static function get_midpoint_variance_message_text( $revenue_variance = 0 ) {

		// Our variance is positive and within range.
		if ( floatval( $revenue_variance ) <= 15 ) {
			return __( 'You\'re off to a great start! Your ecommerce site revenue is trending positively towards your target revenue goal for this week.', 'nexcess-mapps' );
		}

		// Our variance is negative, but within range.
		if ( floatval( $revenue_variance ) >= -15 ) {
			return __( 'While your ecommerce site revenue is currently trending downward this week, it\'s within the expected target goal.', 'nexcess-mapps' );
		}

		// Format the revenue variance.
		$format_rev_val = absint( $revenue_variance ) . '%';

		// Our variance is positive and over the target.
		if ( floatval( $revenue_variance ) >= 15 ) {
			/* Translators: %1$s is the positive revenue variance. */
			return sprintf( __( 'Congratulations! Your ecommerce site revenue is trending %s above your target revenue goal for this week.', 'nexcess-mapps' ), esc_attr( $format_rev_val ) );
		}

		// Our variance is negative and over the target.
		if ( floatval( $revenue_variance ) <= 15 ) {
			/* Translators: %1$s is the negative revenue variance. */
			return sprintf( __( 'Your ecommerce site revenue is currently trending %s below the expected target goal for this week.', 'nexcess-mapps' ), esc_attr( $format_rev_val ) );
		}

		return '';
	}

}
