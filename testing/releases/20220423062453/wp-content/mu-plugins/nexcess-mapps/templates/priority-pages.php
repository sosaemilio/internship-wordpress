<?php
/**
 * The "Priority Pages" dashboard tab.
 *
 * @var \Nexcess\MAPPS\Settings $settings The current settings object.
 */

use Nexcess\MAPPS\Integrations\VisualComparison;

?>

<div class="mapps-layout-fluid-deferred">
	<div class="mapps-primary">
		<p><?php esc_html_e( 'You know your site better than anyone, and some pages are more important than others.', 'nexcess-mapps' ); ?></p>
		<p><?php esc_html_e( 'By specifying key pages, we can monitor their performance and perform visual comparisons during plugin updates, alerting you if these priority pages require your attention.', 'nexcess-mapps' ); ?></p>

		<form method="POST" action="<?php echo esc_attr( admin_url( 'options.php' ) ); ?>">
			<div id="mapps-visual-comparison-urls">
				<!-- Root element for the VisualComparisonUrls React component. -->
			</div>

			<?php settings_fields( VisualComparison::SETTINGS_GROUP ); ?>
			<input type="hidden" name="_wp_http_referer" value="<?php echo esc_attr( stripslashes( $_SERVER['REQUEST_URI'] ) ); ?>#priority-pages" />
			<?php submit_button(); ?>
		</form>
	</div>

	<?php if ( $settings->mapps_plugin_updates_enabled ) : ?>
	<div class="mapps-sidebar card">
		<h3><?php esc_html_e( 'About Visual Comparison', 'nexcess-mapps' ); ?></h3>
		<p><?php esc_html_e( 'Occasionally, updating a plugin can cause major changes to the appearance or behavior of your site.', 'nexcess-mapps' ); ?></p>
		<p><?php esc_html_e( 'We don\'t like that kind of surprise around here, so we perform visual regression testing on key pages of your site.', 'nexcess-mapps' ); ?></p>
		<p><?php esc_html_e( 'Before upgrading anything on a live site, we create a copy of your site, then take screenshots before and after the plugin update; if anything has changed, we hold back the update and let you know.', 'nexcess-mapps' ); ?></p>
		<p><a href="https://help.nexcess.net/74095-wordpress/how-to-use-visual-comparison-tool" class="button"><?php esc_html_e( 'Learn More', 'nexcess-mapps' ); ?></a></p>
	</div>
	<?php endif; ?>

</div>
