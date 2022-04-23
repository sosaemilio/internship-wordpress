<?php

/**
 * The support tab of the MAPPS Dashboard.
 *
 * @var array<string,mixed>     $details  Support details.
 * @var \Nexcess\MAPPS\Settings $settings The current settings object.
 */

use Nexcess\MAPPS\Support\Helpers;

?>

<div class="mapps-layout-fluid">
	<div class="mapps-primary">
		<h2><?php esc_html_e( 'Get Support', 'nexcess-mapps' ); ?></h2>
		<p><?php esc_html_e( 'Our dedicated support team has you covered 24/7/365.', 'nexcess-mapps' ); ?></p>
		<p>
		<?php
			echo wp_kses_post( sprintf(
				/* Translators: %1$s is the user's client portal. */
				__( 'You can contact them by <a href="%1$s">opening a ticket via your Client Portal</a>, emailing <a href="mailto:support@nexcess.net">support@nexcess.net</a>, or calling:', 'nexcess-mapps' ),
				esc_attr( Helpers::getPortalUrl( $settings->plan_id, $settings->account_id ) )
			) );
			?>
		</p>

		<table class="widefat striped mapps-w-auto">
			<tr>
				<th scope="row"><?php esc_html_e( '🇺🇸 Americas', 'nexcess-mapps' ); ?></th>
				<td><a href="tel:+18666392377">+1-866-639-2377</a></td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( '🇦🇺 Australia', 'nexcess-mapps' ); ?></th>
				<td><a href="tel:+1800765472">+1-800-765-472</a></td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( '🇬🇧 United Kingdom', 'nexcess-mapps' ); ?></th>
				<td><a href="tel:+08081207609">+0-808-120-7609</a></td>
			</tr>
		</table>

		<h3><?php esc_html_e( 'Support Details', 'nexcess-mapps' ); ?></h3>
		<p><?php esc_html_e( 'There are some details that support may ask for, so we\'ve bundled them up for you:', 'nexcess-mapps' ); ?></p>
		<table class="widefat striped mapps-w-auto mapps-autoselect">
			<?php foreach ( $details as $label => $value ) : ?>
				<tr>
					<th scope="row"><?php echo esc_html( $label ); ?></th>
					<td><?php echo esc_html( (string) $value ); ?></td>
				</tr>
			<?php endforeach; ?>
		</table>
	</div>

	<div class="mapps-sidebar card">
		<h3><?php esc_html_e( 'Explore our Knowledge Library', 'nexcess-mapps' ); ?></h3>
		<p><?php esc_html_e( 'Prefer to be more hands-on? You can search the constantly-growing Nexcess Knowledge Library for tutorials and how-to material.', 'nexcess-mapps' ); ?></p>

		<?php if ( $settings->is_mapps_site ) : ?>
			<h4><?php esc_html_e( 'Popular Articles', 'nexcess-mapps' ); ?></h4>
			<ul class="mapps-link-list">
				<li><a href="https://help.nexcess.net/74095-wordpress/getting-started-with-managed-wordpress-and-woocommerce-hosting">Getting started with managed WordPress and WooCommerce hosting</a></li>
				<li><a href="https://help.nexcess.net/74095-wordpress/locating-your-ssh-credentials-in-managed-wordpress-and-managed-woocommerce-hosting?from_search=52252971">Locating your SSH credentials in managed WordPress and managed WooCommerce hosting</a></li>
				<li><a href="https://help.nexcess.net/74095-wordpress/setting-up-a-staging-site-in-managed-wordpress-and-managed-woocommerce-hosting">Setting up a staging environment in managed WordPress and managed WooCommerce hosting</a></li>
				<li><a href="https://help.nexcess.net/79236-woocommerce/how-to-use-the-nexcess-installer-plugin">How to use the Nexcess Installer Plugin</a></li>
				<li><a href="https://help.nexcess.net/74095-wordpress/going-live-with-your-site-in-managed-wordpress-and-managed-woocommerce-hosting">Going live with your site in Managed WordPress and Managed WooCommerce hosting</a></li>
			</ul>
		<?php endif; ?>

		<p><a href="https://help.nexcess.net/" class="button"><?php esc_html_e( 'Explore Now', 'nexcess-mapps' ); ?></a></p>

		<h3><?php esc_html_e( 'Hosting FAQ', 'nexcess-mapps' ); ?></h3>
		<p><?php esc_html_e( 'See if your questions have already been answered. Check our hosting FAQ. ', 'nexcess-mapps' ); ?></p>
		<p><a href="https://www.nexcess.net/support/faq/" class="button"><?php esc_html_e( 'More Info', 'nexcess-mapps' ); ?></a></p>
	</div>
</div>
