<?php

namespace Nexcess\MAPPS\Integrations\PerformanceMonitor;

use Nexcess\MAPPS\Integrations\PerformanceMonitor;

/**
 * Helper class to simplify manipulation with post meta key and values.
 */
class Meta {
	/**
	 * Adds a prefix to a single meta key.
	 *
	 * @param string $meta_key A meta key.
	 *
	 * @return string
	 */
	public static function prefix_key( $meta_key ) {
		return PerformanceMonitor::DATA_PREFIX . $meta_key;
	}

	/**
	 * Adds a prefix to an array composed of key => value pairs.
	 *
	 * @param Array<string, mixed> $meta A meta array.
	 *
	 * @return Array<string, mixed>
	 */
	public static function prefix_meta_array( $meta ) {
		$prefixed_meta = [];

		foreach ( $meta as $meta_key => $meta_value ) {
			$prefixed_meta[ PerformanceMonitor::DATA_PREFIX . $meta_key ] = $meta_value;
		}

		return $prefixed_meta;
	}

	/**
	 * Removes a prefix from a single meta key.
	 *
	 * @param string $meta_key A meta key.
	 *
	 * @return string
	 */
	public static function unprefix_key( $meta_key ) {
		$prefix_regex   = sprintf( '~^%s~', preg_quote( PerformanceMonitor::DATA_PREFIX, '~' ) );
		$unprefixed_key = preg_replace( $prefix_regex, '', $meta_key );

		return $unprefixed_key ?: '';
	}

	/**
	 * Removes a prefix from an array composed of key => value pairs.
	 *
	 * @param Array<string, mixed> $meta A meta array.
	 *
	 * @return Array<string, mixed>
	 */
	public static function unprefix_meta_array( $meta ) {
		$unprefixed_meta = [];

		foreach ( $meta as $meta_key => $meta_value ) {
			$unprefixed_key                     = self::unprefix_key( $meta_key );
			$unprefixed_meta[ $unprefixed_key ] = $meta_value;
		}

		return $unprefixed_meta;
	}
}
