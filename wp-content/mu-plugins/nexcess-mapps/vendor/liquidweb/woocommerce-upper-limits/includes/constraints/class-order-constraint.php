<?php
/**
 * Limit the number of orders a site may receive in a month.
 *
 * @package LiquidWeb/WooCommerceUpperLimits
 */

namespace LiquidWeb\WooCommerceUpperLimits\Constraints;

use WP_Error;
use function LiquidWeb\WooCommerceUpperLimits\Helpers\ordinal_number;

class OrderConstraint extends AbstractConstraint {

	/**
	 * {@inheritDoc}
	 *
	 * @var string
	 */
	protected $env = 'WOOCOMMERCE_MAX_ORDERS';

	/**
	 * {@inheritDoc}
	 *
	 * @var int
	 */
	protected $default = 150;

	/**
	 * Actions to perform when the constraint is instantiated.
	 */
	public function __construct() {
		$this->add_hooks();
	}

	/**
	 * Add any additional hooks that are necessary.
	 */
	public function add_hooks() {
		if ( ! $this->is_active_constraint() ) {
			return;
		}

		add_action( 'save_post_shop_order', [ $this, 'order_created' ], 100, 3 );
	}

	/**
	 * Apply constraints to the store once the limit has been reached.
	 */
	public function restrict_store() {
		parent::restrict_store();

		// Notify customers on the front-end of the site.
		add_action( 'wp', [ $this, 'customer_notice' ] );
		add_filter( 'woocommerce_add_to_cart_validation', [ $this, 'customer_notice' ] );
		add_filter( 'woocommerce_add_to_cart_validation', '__return_false', 11 );

		// Remove Add to Cart buttons.
		add_filter( 'woocommerce_is_purchasable', '__return_false' );

		// Prevent users from creating new orders.
		add_filter( 'current_screen', [ $this, 'restrict_order_creation' ] );
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_qualifying_records_count() {
		$orders = wc_get_orders( [
			'type'       => wc_get_order_types( 'order-count' ),
			'date_after' => date_i18n( 'Y-m-01 00:00:00' ),
			'return'     => 'ids',
			'limit'      => $this->get_limit(),
		] );

		return count( $orders );
	}

	/**
	 * Callback to run when an order is created.
	 *
	 * @param int     $id     Post ID.
	 * @param WP_Post $post   Post object.
	 * @param bool    $update Whether this is an existing order being updated or not.
	 */
	public function order_created( $id, $post, $update ) {
		$transient_key = 'wc_upper_limits_order_constraint_warning';

		if ( $update || get_transient( $transient_key ) ) {
			return;
		}

		// By default, send notifications once we've reached the equivalent of 130/150 of the limit.
		$threshold = (int) floor( 130 / 150 * $this->get_limit() );

		if ( $threshold && $threshold === $this->get_qualifying_records_count() ) {
			$this->send_constraint_notification();

			// Set a transient through the end of the month so we won't send this again.
			set_transient( $transient_key, time(), $this->seconds_until_renewal() );
		}
	}

	/**
	 * Retrieve the number of seconds before the period renews (e.g. the first of next month).
	 *
	 * @return int The number of seconds until midnight on the first of the next month.
	 */
	public function seconds_until_renewal() {
		return mktime( 0, 0, 0, date( 'n' ) + 1, 1 ) - current_time( 'timestamp' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function admin_notice() {
		$this->render_admin_warning( sprintf(
			/* Translators: %1$d is the maximum number of orders permitted each month. */
			__( 'You have met your current threshold of %1$d orders per month. This threshold will reset on the first of next month.', 'woocommerce-upper-limits' ),
			$this->get_limit()
		) );
	}

	/**
	 * Display a global notice to visitors once the site has reached its order threshold.
	 */
	public function customer_notice() {
		$message = __( 'We are not currently taking orders for the rest of the month - come back on the first of next month!', 'woocommerce-upper-limits' );

		/**
		 * Filter the message displayed to customers when the site has hit its order threshold.
		 *
		 * @param string $message The message to display to customers.
		 */
		$message = apply_filters( 'woocommerce_upper_limits_customer_order_notice', $message );

		if ( is_woocommerce() && ! wc_has_notice( $message, 'notice' ) ) {
			wc_add_notice( $message, 'notice' );
		}
	}

	/**
	 * {@inheritDoc}
	 */
	public function disable_rest() {
		add_filter( 'woocommerce_rest_pre_insert_shop_order_object', function () {
			return new WP_Error(
				'too-many-orders',
				__( 'Your store has exceeded the allowed number of orders for this month.', 'woocommerce-upper-limits' )
			);
		} );
	}

	/**
	 * Disable the administration interface for creating new orders.
	 *
	 * @param WP_Screen $screen The current screen.
	 */
	public function restrict_order_creation( $screen ) {
		$is_order_edit = 'edit' === $screen->base && 'shop_order' === $screen->post_type && 'edit-shop_order' === $screen->id;
		$is_order_add  = 'add' === $screen->action && 'shop_order' === $screen->post_type;

		if ( $is_order_add ) {
			// Eliminate Add New Product Link, and disable add new product URL completely.
			add_filter( 'map_meta_cap', function( $caps, $cap ) {
				switch ( $cap ) {
					case 'edit_shop_orders':
					case 'create_posts':
						$caps[] = 'do_not_allow';
						break;
				}
				return $caps;
			}, 10, 2 );

		} elseif ( $is_order_edit ) {
			add_action( 'admin_print_styles', function() {
				?>
				<style>
					body.post-type-shop_order a.page-title-action {
						display: none;
					}
				</style>
				<?php
			} );
		}
	}

	/**
	 * Send an email to the store owner once they cross a given threshold of orders.
	 *
	 * @todo Register a WC_Email for these notifications.
	 */
	protected function send_constraint_notification() {
		$admin_email   = get_option( 'admin_email' );
		$admin_user    = get_user_by( 'email', $admin_email );
		$admin_name    = $admin_user ? $admin_user->display_name : $admin_email;
		$url           = site_url();
		$count         = $this->get_qualifying_records_count();
		$count_ordinal = ordinal_number( $count );
		$limit         = $this->get_limit();
		$percent       = ceil( $count / $limit * 100 );
		$headers       = [ 'From: Liquid Web <sales@liquidweb.com>', 'Bcc: partner-team@liquidweb.com' ];
		$subject       = sprintf(
			/* Translators: %1$d is the current number of orders for the month. */
			__( 'You\'ve just made your %1$s sale this month!', 'woocommerce-upper-limits' ),
			$count_ordinal
		);
		$message = <<<EOT
{$admin_name},

We wanted to let you know that you've just made your {$count_ordinal} sale on {$url} this month, which puts you {$percent}% of the way to your limit of {$limit} orders.

If you feel that you’re on track to exceed {$limit} orders this month, consider upgrading your hosting plan to remove the limitations. We make it simple to upgrade. Simply reply to this email and our sales team will be able to help you upgrade.

Best,
Liquid Web
EOT;

		wp_mail( $admin_email, $subject, $message, $headers );
	}
}
