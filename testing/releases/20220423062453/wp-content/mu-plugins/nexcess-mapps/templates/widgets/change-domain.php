<?php

/**
 * The "Go Live!" domain change widget.
 *
 * @global string $dns_help_url Documentation for configuring DNS records.
 */

?>

<h3 class="mapps-storebuilder--section-headline">
	<?php esc_attr_e( '1. Update your domain\'s DNS', 'nexcess-mapps' ); ?>
</h3>

<small>
	<?php
	echo wp_kses_post( sprintf(
		/* Translators: %1$s is the DNS registration URL. */
		__( '<a class="mapps-storebuilder--help-text" href="%1$s" target="_blank" rel="noopener">I haven\'t purchased a custom domain</a>', 'nexcess-mapps' ),
		esc_url( 'https://www.nexcess.net/domain-registration/' )
	) );
	?>
</small>

<p>
	<?php esc_attr_e( 'Login to your domain registrar dashboard or get in touch with your domain registrar\'s support department to change your name servers and connect your domain with your StoreBuilder store.', 'nexcess-mapps' ); ?>
</p>

<p>
	<details class="mapps-storebuilder-welcome-details">
		<summary>
			<?php esc_attr_e( 'View Name Servers', 'nexcess-mapps' ); ?>
		</summary>
		<ul>
			<li>
				<code>ns1.nexcess.net</code>
			</li>
			<li>
				<code>ns2.nexcess.net</code>
			</li>
			<li>
				<code>ns3.nexcess.net</code>
			</li>
			<li>
				<code>ns4.nexcess.net</code>
			</li>
		</ul>
	</details>
</p>

<h3 class="mapps-storebuilder--section-headline">
	<?php esc_attr_e( '2. Go Live!', 'nexcess-mapps' ); ?>
</h3>

<p>
	<?php esc_attr_e( 'After completing step 1, enter the domain name below, press "Connect" and we\'ll do all the work. This process may take a few minutes, after which you will need to log in again on the new live domain.', 'nexcess-mapps' ); ?>
</p>

<div id="mapps-change-domain-form"></div>

<?php do_action( 'Nexcess\MAPPS\DomainChange\After' ); ?>
