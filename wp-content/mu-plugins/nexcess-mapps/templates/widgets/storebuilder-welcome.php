<?php

/**
 * The StoreBuilder welcome screen.
 */

?>
<div class="updated mapps-storebuilder-header">
	<h2 class="mapps-storebuilder-header__headline"><?php esc_html_e( 'Site Dashboard', 'nexcess-mapps' ); ?></h2>
	<ul class="mapps-storebuilder-header__nav">
		<li class="mapps-storebuilder-header__nav-link">
			<a class="mapps-storebuilder-header__action" href="<?php echo esc_url( admin_url( 'admin.php?page=sitebuilder-store-details' ) ); ?>">
				<?php esc_html_e( 'Store Setup', 'nexcess-mapps' ); ?>
			</a>
		</li>
		<li class="mapps-storebuilder-header__nav-link">
			<a class="mapps-storebuilder-header__action" href="<?php echo esc_url( admin_url( 'admin.php?page=wc-admin&path=%2Fanalytics%2Foverview' ) ); ?>">
				<?php esc_html_e( 'Store Analytics', 'nexcess-mapps' ); ?>
			</a>
		</li>
		<li class="mapps-storebuilder-header__nav-link">
			<a rel="nofollow" class="mapps-storebuilder-header__action" href="https://my.nexcess.net/">
				<?php esc_html_e( 'Nexcess Hosting Dashboard', 'nexcess-mapps' ); ?>
				<span aria-hidden="true" class="dashicons dashicons-external"></span>
			</a>
		</li>
	</ul>
</div>
