<?php
/**
 * Add useful functions that have yet to ship in WordPress core.
 *
 * Note that this file is intentionally in the global namespace.
 */

/**
 * DateTime improvements shipping in WordPress 5.3.
 *
 * @link https://make.wordpress.org/core/2019/09/23/date-time-improvements-wp-5-3/
 */
if ( ! function_exists( 'current_datetime' ) ) {
	/**
	 * Retrieves the current time as an object with the timezone from settings.
	 *
	 * @since 5.3.0
	 *
	 * @return DateTimeImmutable Date and time object.
	 */
	function current_datetime() {
		return new DateTimeImmutable( 'now', wp_timezone() );
	}
}

if ( ! function_exists( 'wp_timezone' ) ) {
	/**
	 * Retrieves the timezone from site settings as a `DateTimeZone` object.
	 *
	 * Timezone can be based on a PHP timezone string or a ±HH:MM offset.
	 *
	 * @since 5.3.0
	 *
	 * @return DateTimeZone Timezone object.
	 */
	function wp_timezone() {
		return new DateTimeZone( wp_timezone_string() );
	}
}

if ( ! function_exists( 'wp_timezone_string' ) ) {
	/**
	 * Retrieves the timezone from site settings as a string.
	 *
	 * Uses the `timezone_string` option to get a proper timezone if available,
	 * otherwise falls back to an offset.
	 *
	 * @since 5.3.0
	 *
	 * @return string PHP timezone string or a ±HH:MM offset.
	 */
	function wp_timezone_string() {
		$timezone_string = get_option( 'timezone_string' );

		if ( $timezone_string ) {
			return $timezone_string;
		}

		$offset  = (float) get_option( 'gmt_offset' );
		$hours   = (int) $offset;
		$minutes = ( $offset - $hours );

		$sign      = ( $offset < 0 ) ? '-' : '+';
		$abs_hour  = abs( $hours );
		$abs_mins  = abs( $minutes * 60 );
		$tz_offset = sprintf( '%s%02d:%02d', $sign, $abs_hour, $abs_mins );

		return $tz_offset;
	}
}

/**
 * Environment type declarations, introduced in WordPress 5.5.
 *
 * Note that this version skips the notice about WP_ENVIRONMENT_TYPES, as older versions wouldn't
 * have ever included support.
 *
 * @link https://make.wordpress.org/core/2020/07/24/new-wp_get_environment_type-function-in-wordpress-5-5/
 */
if ( ! function_exists( 'wp_get_environment_type' ) ) {
	/**
	 * Retrieves the current environment type.
	 *
	 * The type can be set via the `WP_ENVIRONMENT_TYPE` global system variable,
	 * or a constant of the same name.
	 *
	 * Possible values include 'local', 'development', 'staging', 'production'.
	 * If not set, the type defaults to 'production'.
	 *
	 * @since 5.5.0
	 * @since 5.5.1 Added the 'local' type.
	 * @since 5.5.1 Removed the ability to alter the list of types.
	 *
	 * @return string The current environment type.
	 */
	function wp_get_environment_type() {
		static $current_env = '';

		if ( $current_env ) {
			return $current_env;
		}

		$wp_environments = [
			'local',
			'development',
			'staging',
			'production',
		];

		// Check if the environment variable has been set, if `getenv` is available on the system.
		if ( function_exists( 'getenv' ) ) {
			$has_env = getenv( 'WP_ENVIRONMENT_TYPE' );
			if ( false !== $has_env ) {
				$current_env = $has_env;
			}
		}

		// Fetch the environment from a constant, this overrides the global system variable.
		if ( defined( 'WP_ENVIRONMENT_TYPE' ) ) {
			$current_env = WP_ENVIRONMENT_TYPE;
		}

		// Make sure the environment is an allowed one, and not accidentally set to an invalid value.
		if ( ! in_array( $current_env, $wp_environments, true ) ) {
			$current_env = 'production';
		}

		return $current_env;
	}
}

/**
 * WordPress may never define time constants based in minutes, but this makes expressing time
 * much cleaner.
 *
 * Borrowed from https://github.com/stevegrunwell/time-constants
 */
if ( ! defined( 'HOUR_IN_MINUTES' ) ) {
	define( 'HOUR_IN_MINUTES', 60 );
}

if ( ! defined( 'DAY_IN_MINUTES' ) ) {
	define( 'DAY_IN_MINUTES', 24 * HOUR_IN_MINUTES );
}
