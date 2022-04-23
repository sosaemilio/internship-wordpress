<?php

/**
 * The main dashboard for Nexcess MAPPS.
 *
 * @var \Nexcess\MAPPS\Settings $settings The current settings object.
 */

use Nexcess\MAPPS\Support\Helpers;

?>

<div class="mapps-layout-fluid">
	<div class="mapps-primary">
		<?php
			settings_errors();

			/**
			 * Render MAPPS-specific dashboard notices.
			 *
			 * @see Nexcess\MAPPS\Concerns\HasDashboardNotices::addDashboardNotice().
			 */
			do_action( 'Nexcess\\MAPPS\\dashboard_notices' );
		?>

		<h3 class="title">Get Support</h3>
		<p><?php esc_html_e( 'Our dedicated support team has you covered 24/7/365.', 'nexcess-mapps' ); ?></p>
		<p><a href="#support" class="button"><?php esc_html_e( 'Get Support', 'nexcess-mapps' ); ?></a></p>

		<!-- RSS feed for https://blog.nexcess.net. -->
		<h3 class="title"><?php esc_html_e( 'From the Nexcess Blog', 'nexcess-mapps' ); ?></h3>
		<p><?php echo wp_kses_post( __( 'We regularly publish tips, insights, and the latest news <a href="https://blog.nexcess.net">on the Nexcess Blog</a>.', 'nexcess-mapps' ) ); ?></p>
		<?php
			wp_widget_rss_output( 'https://feeds.feedburner.com/nexcess', [
				'items' => 5,
			] );
			?>
	</div>

	<div id="plan-details" class="mapps-sidebar card">
		<h3 class="title"><?php esc_html_e( 'Plan Details', 'nexcess-mapps' ); ?></h3>
		<table class="widefat striped" role="presentation">
			<tr>
				<th scope="row"><?php esc_html_e( 'Plan', 'nexcess-mapps' ); ?></th>
				<td><?php echo esc_html( ucwords( $settings->plan_name ) ); ?></td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Environment', 'nexcess-mapps' ); ?></th>
				<td><?php echo esc_html( ucwords( $settings->environment ) ); ?></td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'PHP Version', 'nexcess-mapps' ); ?></th>
				<td><?php echo esc_html( $settings->php_version ); ?></td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Autoscaling', 'nexcess-mapps' ); ?></th>
				<td><?php Helpers::enabled( $settings->autoscaling_enabled ); ?></td>
			</tr>
		</table>
		<p><a href="<?php echo esc_attr( Helpers::getPortalUrl( $settings->plan_id, $settings->account_id ) ); ?>" class="button button-primary"><?php esc_html_e( 'Manage Site', 'nexcess-mapps' ); ?></a></p>
	</div>
</div>
