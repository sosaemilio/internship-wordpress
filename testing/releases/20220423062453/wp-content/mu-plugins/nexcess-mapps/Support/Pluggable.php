<?php
/**
 * Overrides for WordPress pluggable functions.
 *
 * This file should not be loaded directly; instead, use the
 * Nexcess\MAPPS\Concerns\HasPluggables::loadPluggables() method.
 *
 * Note that this file is in the *global* namespace!
 *
 * @link https://codex.wordpress.org/Pluggable_Functions
 */

use Nexcess\MAPPS\Settings;

// In case this file is included directly, ensure $settings is defined.
if ( ! isset( $settings ) || ! $settings instanceof Settings ) {
	$settings = new Settings();
}

// Mail can be short-circuited via pre_wp_mail on WordPress 5.7+.
if ( $settings->is_regression_site && ! function_exists( 'wp_mail' ) ) {
	/**
	 * Short-circuit emails on regression sites by defining a stubbed version of wp_mail().
	 *
	 * @return bool Will always be true.
	 */
	function wp_mail() {
		return true;
	}
}

if ( $settings->is_regression_site && ! function_exists( 'wp_rand' ) ) {
	/**
	 * Not-so-random numbers for regression sites().
	 *
	 * @return int Will always be 123.
	 */
	function wp_rand() {
		return 123;
	}
}
