<?php
/**
 * The abstract class for all WooCommerce constraints.
 *
 * @package LiquidWeb/WooCommerceUpperLimits
 */

namespace LiquidWeb\WooCommerceUpperLimits\Constraints;

use function LiquidWeb\WooCommerceUpperLimits\Constraints\get_constraint;
use function LiquidWeb\WooCommerceUpperLimits\Constraints\get_constraint_class;

abstract class AbstractConstraint {

	/**
	 * The environment variable that determines the limit for this constraint.
	 *
	 * @var string
	 */
	protected $env;

	/**
	 * The default limit if the environment variable is not set.
	 *
	 * @var int
	 */
	protected $default;

	/**
	 * Apply constraints to the store once the limit has been reached.
	 */
	public function restrict_store() {
		add_action( 'admin_notices', [ $this, 'admin_notice' ] );
		add_action( 'rest_api_init', [ $this, 'disable_rest' ] );
	}

	/**
	 * Get the limit defined for this constraint.
	 *
	 * @return int The maximum number of objects permitted.
	 */
	public function get_limit() {
		return (int) getenv( $this->env ) ?: $this->default;
	}

	/**
	 * Retrieve the number of qualifying records for this constraint.
	 *
	 * @return int The number of records that match the constraint and count towards the limit.
	 */
	abstract public function get_qualifying_records_count();

	/**
	 * Has the site reached its upper limit for this constraint?
	 *
	 * @return bool Whether or not the site has reached its limit.
	 */
	public function has_reached_limit() {
		return $this->get_qualifying_records_count() >= $this->get_limit();
	}

	/**
	 * Is the current constraint active on the store?
	 *
	 * @return bool Whether or not this is an active constraint.
	 */
	public function is_active_constraint() {
		return get_constraint_class( get_constraint() ) === static::class;
	}

	/**
	 * Display a notice for the site administrator once constraints have been applied.
	 */
	abstract public function admin_notice();

	/**
	 * Disable the ability to create new resources via the WP REST API.
	 */
	abstract public function disable_rest();

	/**
	 * Render a warning within the admin dashboard.
	 *
	 * @param string $message The message to display in the warning.
	 */
	protected function render_admin_warning( $message ) {
		// phpcs:disable Generic.WhiteSpace.ScopeIndent.IncorrectExact
?>

	<div class="notice notice-warning is-dismissible">
		<p><?php echo esc_html( $message ); ?></p>
	</div>

<?php // phpcs:enable Generic.WhiteSpace.ScopeIndent.IncorrectExact
	}
}
