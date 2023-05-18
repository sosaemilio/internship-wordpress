<?php

/**
 * The main admin page for Nexcess MAPPS customers.
 *
 * @global \Nexcess\MAPPS\Settings $settings The current settings object.
 */

use Nexcess\MAPPS\Integrations\Dashboard;
use StellarWP\PluginFramework\Support\Branding;

// Fetch the company name.
$company_name = Branding::getCompanyName();

if ( $settings->is_mwch_site ) {
	$page_title = $settings->is_storebuilder
		/* Translators: %1$s is the branded company name. */
		? sprintf( __( 'StoreBuilder, powered by %s', 'nexcess-mapps' ), $company_name )
		/* Translators: %1$s is the branded company name. */
		: sprintf( __( 'Managed WooCommerce, powered by %s', 'nexcess-mapps' ), $company_name );
} else {
	/* Translators: %1$s is the branded company name. */
	$page_title = sprintf( __( 'Managed WordPress, powered by %s', 'nexcess-mapps' ), $company_name );
}

?>

<div class="wrap mapps-wrap">
	<h1 class="nexcess-page-title">
		<span><?php echo esc_html( $page_title ); ?><span>
	</h1>
	<?php do_tabbed_settings_sections( Dashboard::ADMIN_MENU_SLUG ); ?>
</div>
