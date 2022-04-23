<?php
/**
 * Helper functions for formatting and output.
 *
 * @package LiquidWeb/WooCommerceUpperLimits
 */

namespace LiquidWeb\WooCommerceUpperLimits\Helpers;

/**
 * Given a number, return the ordinal form based on the current locale.
 *
 * For example, 1 would be "1st", 2 would be "2nd", etc.
 *
 * @link https://stackoverflow.com/a/8346173/329911
 *
 * @param int $num The number to format.
 *
 * @return string The ordinal form of the number.
 */
function ordinal_number( $num ) {
	$day = ( ( ( $num >= 10 ) + ( $num % 100 >= 20 ) + ( 0 === $num ) ) * 10 + $num % 10 );

	return $num . date( 'S', mktime( 1, 1, 1, 1, $day ) );
}
