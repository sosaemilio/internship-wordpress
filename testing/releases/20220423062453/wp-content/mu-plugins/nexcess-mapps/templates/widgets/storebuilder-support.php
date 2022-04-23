<?php

/**
 * The "StoreBuilder Support" widget.
 */

?>

<h3><?php esc_html_e( 'Need Help With Your Store?', 'nexcess-mapps' ); ?></h3>

<p>
	<?php echo wp_kses_post( sprintf( __( 'Looking to learn more about WordPress and WooCommerce? Or have a specific question? We have you covered:', 'nexcess-mapps' ) ) ); ?>
</p>

<ul class="mapps-storebuilder-support-links">
	<li>
		<div class="mapps-storebuilder-support-links__primary">
			<i class="mapps-icon mapps-icon--school"></i>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=wp101' ) ); ?>">
				<?php echo wp_kses_post( __( 'WooCommerce Video Tutorials', 'nexcess-mapps' ) ); ?>
			</a>
		</div>
		<div class="mapps-storebuilder-support-links__secondary">
			<?php echo wp_kses_post( __( 'Helpful tutorials to learn everything you need about your new store', 'nexcess-mapps' ) ); ?>
		</div>
	</li>
	<li>
		<div class="mapps-storebuilder-support-links__primary">
			<i class="mapps-icon mapps-icon--wand"></i>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=wp101' ) ); ?>">
				<?php echo wp_kses_post( __( 'WooCommerce Tips & Tricks', 'nexcess-mapps' ) ); ?>
			</a>
		</div>
		<div class="mapps-storebuilder-support-links__secondary">
			<?php echo wp_kses_post( __( 'Take your eCommerce store to the next level with', 'nexcess-mapps' ) ); ?>
		</div>
	</li>
</ul>

<h3><?php esc_html_e( 'Need help with hosting/going live?', 'nexcess-mapps' ); ?></h3>

<ul class="mapps-storebuilder-support-links">
	<li>
		<div class="mapps-storebuilder-support-links__primary">
			<i class="mapps-icon mapps-icon--library"></i>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=nexcess-mapps#support' ) ); ?>">
				<?php echo wp_kses_post( __( 'Nexcess Knowledgebase', 'nexcess-mapps' ) ); ?>
			</a>
		</div>
		<div class="mapps-storebuilder-support-links__secondary">
			<?php echo wp_kses_post( __( 'Check out these articles and guides from our Knowledge Base to learn more about how you can bring your store online.', 'nexcess-mapps' ) ); ?>
		</div>
	</li>
</ul>

<p class="mapps-storebuilder-support-footer">
	<?php
	echo wp_kses_post(
		sprintf(
			/* Translators: %1$s: Opening link tag, %2$s: Closing link tag. */
			__( 'Can\'t find your answer? %1$sGet help from our Nexcess experts%2$s', 'nexcess-mapps' ),
			'<a href="' . admin_url( 'admin.php?page=nexcess-mapps#support' ) . '">',
			'</a>'
		)
	);
	?>
</p>
