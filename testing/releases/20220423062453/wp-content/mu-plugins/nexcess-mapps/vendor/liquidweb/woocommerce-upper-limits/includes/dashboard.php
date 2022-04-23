<?php
/**
 * Welcome screen on the WordPress admin dashboard.
 *
 * @package LiquidWeb/WooCommerceUpperLimits
 */

namespace LiquidWeb\WooCommerceUpperLimits\Dashboard;

use LiquidWeb\WooCommerceUpperLimits\Constraints as Constraints;
use LiquidWeb\WooCommerceUpperLimits\Constraints\OrderConstraint;
use LiquidWeb\WooCommerceUpperLimits\Constraints\ProductConstraint;
use LiquidWeb\WooCommerceUpperLimits\Helpers as Helpers;
use LiquidWeb\WooCommerceUpperLimits\Integration;
use const LiquidWeb\WooCommerceUpperLimits\OPTION_KEY;

/**
 * If the site has not yet set a constraint, replace the default WordPress welcome panel.
 */
function replace_welcome_screen() {
	if ( false !== get_option( OPTION_KEY, false ) || ! current_user_can( 'manage_woocommerce' ) ) {
		return;
	}

	// Process changes from within the dashboard.
	if (
		isset( $_POST['woocommerce-upper-limits-constraint'], $_POST['woocommerce-upper-limits-nonce'] )
		&& current_user_can( 'manage_woocommerce' )
		&& wp_verify_nonce( $_POST['woocommerce-upper-limits-nonce'], 'set-constraint' ) // WPCS: sanitization ok.
	) {
		$integration = new Integration();

		update_option(
			OPTION_KEY,
			$integration->sanitize_selection( $_POST['woocommerce-upper-limits-constraint'] ) // WPCS: sanitization ok.
		);
		return;
	}

	// Replace the default panel with ours.
	remove_action( 'welcome_panel', 'wp_welcome_panel' );
	add_action( 'welcome_panel', __NAMESPACE__ . '\render_welcome_screen' );
}
add_action( 'wp_dashboard_setup', __NAMESPACE__ . '\replace_welcome_screen' );

/**
 * Render the welcome screen on the WordPress admin dashboard.
 *
 * @todo Copy + formatting.
 *
 * @see wp_welcome_panel() for available formatting.
 */
function render_welcome_screen() {
	$order_constraint   = new OrderConstraint();
	$product_constraint = new ProductConstraint();

	// phpcs:disable Generic.WhiteSpace.ScopeIndent.IncorrectExact
?>
	<style type="text/css">
		.woocommerce-upper-limits-welcome-panel {
			padding: 23px 10px 10px;
		}

		.woocommerce-upper-limits-welcome-panel-grid {
			display: grid;
			grid-template-columns: 33% 33% 33%;
		}

		.woocommerce-upper-limits-welcome-panel-column {
			padding-right: 40px;
		}

		.woocommerce-upper-limits-welcome-panel-column h3 {
			line-height: 2em;
			margin-bottom: 10px;
		}

		.woocommerce-upper-limits-welcome-panel-column.constraint-order,
		.woocommerce-upper-limits-welcome-panel-column.constraint-product {
			display: grid;
			grid-template-rows: auto 90px;
		}

		.woocommerce-upper-limits-welcome-panel-column h3 .badge {
			display: inline-block;
			margin-left: 10px;
			padding: 4px 5px;
			vertical-align: middle;
			font-size: .75em;
			line-height: 1;
			color: #fff;
			background: #0085ba;
		}

		.woocommerce-upper-limits-welcome-panel-column h3:before {
			font-family: WooCommerce;
			display: inline-block;
			vertical-align: middle;
			margin: 0 10px -2px 0;
			font-size: 1.6em;
			font-weight: normal;
		}

		.woocommerce-upper-limits-welcome-panel-column.constraint-product h3:before {
			content: '\e02c';
			color: #7ad03a;
		}

		.woocommerce-upper-limits-welcome-panel-column.constraint-order h3:before {
			content: '\e006';
			color: #bb77ae;
		}

		@media (max-width: 870px) {
			.woocommerce-upper-limits-welcome-panel-grid {
				grid-template-columns: 100%;
			}
		}
	</style>

	<div class="woocommerce-upper-limits-welcome-panel">
		<h2><?php esc_html_e( 'Welcome to WooCommerce!', 'woocommerce-upper-limits' ); ?></h2>
		<p class="about-description">Getting started with the Liquid Web Managed WooCommerce Hosting Beginner plan:</p>

		<form method="POST" class="woocommerce-upper-limits-welcome-panel-grid">
			<div class="woocommerce-upper-limits-welcome-panel-column getting-started">
				<h3><?php echo esc_html_x( 'Choosing your Path', 'welcome panel heading', 'woocommerce-upper-limits' ); ?></h3>
				<p><?php echo wp_kses_post( sprintf(
					/* Translators: %1$s is the link to the integration settings page. */
					__( 'As you get started with our Beginner plan, you\'ll need to make a decision about your upcoming store. This decision is based on your hunch, as you\'re just getting started. And if your hunch is off, <a href="%1$s">you\'ll be able to make a switch (one time)</a>.', 'woocommerce-upper-limits' ),
					admin_url( 'admin.php?page=wc-settings&tab=integration' )
				) ); ?></p>
				<p><?php esc_html_e( 'We\'ve created these limits so that we can give you an experience of our entire platform at a much lower price. To do that, we\'ve created a choice of constraints:', 'woocommerce-upper-limits' ); ?></p>
			</div>

			<div class="woocommerce-upper-limits-welcome-panel-column constraint-order">
				<div>
					<h3><?php echo esc_html_x( 'Unlimited Products', 'welcome panel heading', 'woocommerce-upper-limits' ); ?></h3>
					<p><?php esc_html_e( 'If you have a lot of products you want to test in your new store, then this is the right choice for you.', 'woocommerce-upper-limits' ); ?></p>
					<p><?php echo esc_html( sprintf(
						/* Translators: %1$d is the upper limit for the order constraint. */
						__( 'It will not put any constraints on the number of products you create / import into your store. But it will constrain your order volume: you\'ll get up to %1$d orders a month before the buy buttons are turned off and your store becomes more of a catalog.', 'woocommerce-upper-limits' ),
						$order_constraint->get_limit()
					) ); ?></p>
				</div>
				<p><button name="woocommerce-upper-limits-constraint" type="submit" value="order" class="button button-primary button-hero"><?php esc_html_e( 'Choose Unlimited Products', 'woocommerce-upper-limits' ); ?></button></p>
			</div>

			<div class="woocommerce-upper-limits-welcome-panel-column constraint-product">
				<div>
					<h3><?php echo esc_html_x( 'Unlimited Orders', 'welcome panel heading', 'woocommerce-upper-limits' ); ?></h3>
					<p><?php esc_html_e( 'If you have a small number of products, then unlimited orders could be the best plan for you.', 'woocommerce-upper-limits' ); ?></p>
					<p><?php echo esc_html( sprintf(
						/* Translators: %1$d is the upper limit for the product constraint, %2$s is that same number in ordinal form. */
						__( 'This lets your customers order as many times and as many of your products as they want, but you\'re limited to %1$d products. Once you create / import your %2$s product, you won\'t be able to grow your catalog past that.', 'woocommerce-upper-limits' ),
						$product_constraint->get_limit(),
						Helpers\ordinal_number( $product_constraint->get_limit() )
					) ); ?></p>
				</div>
				<p><button name="woocommerce-upper-limits-constraint" type="submit" value="product" class="button button-primary button-hero"><?php esc_html_e( 'Choose Unlimited Orders', 'woocommerce-upper-limits' ); ?></button></p>
			</div>

			<?php wp_nonce_field( 'set-constraint', 'woocommerce-upper-limits-nonce' ); ?>
		</form>
	</div>

<?php // phpcs:enable Generic.WhiteSpace.ScopeIndent.IncorrectExact
}
