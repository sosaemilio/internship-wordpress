<?php
/**
 * Limit the number of products a site may have.
 *
 * @package LiquidWeb/WooCommerceUpperLimits
 */

namespace LiquidWeb\WooCommerceUpperLimits\Constraints;

use WP_Error;

class ProductConstraint extends AbstractConstraint {

	/**
	 * {@inheritDoc}
	 *
	 * @var string
	 */
	protected $env = 'WOOCOMMERCE_MAX_PRODUCTS';

	/**
	 * {@inheritDoc}
	 *
	 * @var int
	 */
	protected $default = 15;

	/**
	 * Apply constraints to the store once the limit has been reached.
	 */
	public function restrict_store() {
		parent::restrict_store();

		add_action( 'woocommerce_product_import_before_import', [ $this, 'disable_imports' ] );

		// Prevent new products from being published.
		add_action( 'publish_product', [ $this, 'block_publishing' ], PHP_INT_MAX, 2 );
		add_action( 'private_product', [ $this, 'block_publishing' ], PHP_INT_MAX, 2 );
		add_action( 'publish_to_private', [ $this, 'bypass_publish_blocking' ] );
		add_action( 'publish_to_publish', [ $this, 'bypass_publish_blocking' ] );
		add_action( 'private_to_publish', [ $this, 'bypass_publish_blocking' ] );
		add_action( 'private_to_private', [ $this, 'bypass_publish_blocking' ] );
		add_filter( 'post_updated_messages', [ $this, 'adjust_post_updated_messages' ] );
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_qualifying_records_count() {
		$products = wc_get_products( [
			'post_status' => [ 'publish', 'private' ],
			'return'      => 'ids',
			'limit'       => $this->get_limit(),
		] );

		return count( $products );
	}

	/**
	 * {@inheritDoc}
	 */
	public function admin_notice() {
		$this->render_admin_warning( sprintf(
			/* Translators: %1$d is the maximum number of products. */
			__( 'Your product catalog has met your current threshold of %1$d products. As such, you will not be able to add any more products. If you need to add more products, try removing some first.', 'woocommerce-upper-limits' ),
			$this->get_limit()
		) );
	}

	/**
	 * {@inheritDoc}
	 */
	public function disable_rest() {
		add_filter( 'woocommerce_rest_pre_insert_product_object', function () {
			return new WP_Error(
				'too-many-products',
				__( 'Your store has exceeded the allowed number of products.', 'woocommerce-upper-limits' )
			);
		} );
	}

	/**
	 * Disable the product importer.
	 *
	 * @param array $product The product being imported.
	 */
	public function disable_imports( $product ) {

		// Users may update existing products via the importer.
		if ( isset( $product['id'] ) && wc_get_product( $product['id'] ) ) {
			return;
		}

		return wp_die( esc_html__( 'You are currently unable to import products, as you have exceeded your product threshold.', 'woocommerce-upper-limits' ) );
	}

	/**
	 * Prevent products from transitioning to "publish" status.
	 *
	 * @param int     $id   The ID of the post that is transitioning.
	 * @param WP_Post $post The post object being transitioned.
	 */
	public function block_publishing( $id, $post ) {
		wp_update_post( [
			'ID'          => $id,
			'post_status' => 'draft',
		] );
	}

	/**
	 * When attempting to publish a product while the constraint is in effect, rewrite the post
	 * updated message from "Product published" to "Product saved".
	 *
	 * @param array $messages An array of post_updated_messages, keyed by post type.
	 */
	public function adjust_post_updated_messages( $messages ) {
		if ( isset( $messages['product'][7] ) ) {
			$messages['product'][6] = $messages['product'][7];
		}

		return $messages;
	}

	/**
	 * For products moving between the "publish" and "private" post statuses, don't let
	 * block_publishing() reset them to drafts.
	 *
	 * @param WP_Post $post The post object being transitioned.
	 */
	public function bypass_publish_blocking( $post ) {
		$action = sprintf( '%s_product', get_post_status( $post ) );

		remove_action( $action, [ $this, 'block_publishing' ], PHP_INT_MAX, 2 );
	}
}
