<?php
/**
 * Logic for registering and handling constraints.
 *
 * @package LiquidWeb/WooCommerceUpperLimits
 */

namespace LiquidWeb\WooCommerceUpperLimits\Constraints;

use LiquidWeb\WooCommerceUpperLimits\Exceptions\MissingConstraintException;
use LiquidWeb\WooCommerceUpperLimits\Integration;
use const LiquidWeb\WooCommerceUpperLimits\OPTION_KEY;

/**
 * The default constraint.
 */
define( __NAMESPACE__ . '\DEFAULT_CONSTRAINT', 'product' );

/**
 * When WordPress boots, load up the appropriate constraint.
 *
 * @return AbstractConstraint The constraint that has been applied.
 */
function apply_constraints() {
	if ( ! did_action( 'woocommerce_loaded' ) ) {
		return;
	}

	try {
		$instance = get_constraint_instance( get_constraint() );
	} catch ( MissingConstraintException $e ) {
		return;
	}

	if ( $instance->has_reached_limit() ) {
		$instance->restrict_store();
	}

	return $instance;
}
add_action( 'init', __NAMESPACE__ . '\apply_constraints' );

/**
 * Retrieve the current constraint.
 *
 * @return string The type of constraint in effect on the store.
 */
function get_constraint() {
	$option = get_option( OPTION_KEY );
	$keys   = array_keys( get_available_constraints() );

	// Remove invalid constraint types.
	if ( $option && ! in_array( $option, $keys, true ) ) {
		delete_option( OPTION_KEY );
		$option = null;
	}

	// If we have a valid option value, return it.
	if ( $option ) {
		return $option;
	}

	// Verify that the default constraint is sensible for the given store.
	$instance = get_constraint_instance( DEFAULT_CONSTRAINT );

	return $instance->has_reached_limit() ? 'order' : DEFAULT_CONSTRAINT;
}

/**
 * Retrieve an array of available constraints.
 *
 * @return array An array of constraints and their descriptions.
 */
function get_available_constraints() {
	return [
		'product' => _x( 'Unlimited Orders', 'constraint name', 'woocommerce-upper-limits' ),
		'order'   => _x( 'Unlimited Products', 'constraint name', 'woocommerce-upper-limits' ),
	];
}

/**
 * Get the class name for a given constraint.
 *
 * @param string $constraint The constraint name.
 *
 * @return string A fully-qualified class name for the constraint.
 */
function get_constraint_class( $constraint ) {
	return 'LiquidWeb\\WooCommerceUpperLimits\\Constraints\\' . ucwords( $constraint ) . 'Constraint';
}

/**
 * Retrieve an instance of the Constraint class for the given constraint.
 *
 * @throws MissingConstraintException If the given constraint definition cannot be found.
 *
 * @param string $constraint The type of constraint to return.
 *
 * @return AbstractConstraint An instance of the corresponding constraint object.
 */
function get_constraint_instance( $constraint ) {
	$class = get_constraint_class( $constraint );

	if ( ! class_exists( $class ) ) {
		throw new MissingConstraintException( sprintf(
			/* Translators: %1$s is the constraint class name. */
			__( 'Constraint definition for "%1$s" cannot be found!', 'woocommerce-upper-limits' ),
			$class
		) );
	}

	return new $class();
}
