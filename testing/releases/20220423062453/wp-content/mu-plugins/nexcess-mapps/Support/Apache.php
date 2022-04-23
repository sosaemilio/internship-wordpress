<?php

/**
 * Helpers for dealing with Apache rewrite rules.
 */

namespace Nexcess\MAPPS\Support;

class Apache {

	/**
	 * Convert a regular expression pattern to a RewriteCond.
	 *
	 * @param string $pattern The regular expression pattern.
	 *
	 * @return string A regex pattern suitable for Apache RewriteCond directives. An empty string
	 *                will be returned if the pattern cannot be parsed or is invalid.
	 */
	public static function regexToRewriteCond( $pattern ) {
		if ( ! self::validateRegex( $pattern ) ) {
			return '';
		}

		/*
		 * Extract modifiers and determine the delimiter being used.
		 *
		 * Capture groups:
		 * 1: The delimiter (non-alphanumeric character)
		 * 2: The contents of the pattern
		 * 3: Regex modifiers
		 */
		if ( ! preg_match( '/^([^A-Za-z0-9]{1})(.+)\1([ADSUXJimsux]*)/', $pattern, $parts ) ) {
			return '';
		}

		/*
		 * Undo any preg_quote() escaping.
		 *
		 * The preg_quote() function will escape the following characters and, optionally, the
		 * pattern delimiter. We want to escape all of these characters, then replace any of their
		 * replaced versions:
		 *
		 *     . \ + * ? [ ^ ] $ ( ) { } = ! < > | : - #
		 *
		 * @link https://www.php.net/manual/en/function.preg-quote.php
		 */
		$escaped = preg_quote( '.\\\+*?[^]$(){}=!<>|:-#', $parts[1] );
		$pattern = preg_replace( '/\\\([\\' . $parts[1] . $escaped . '])/', '$1', $parts[2] );

		// Don't allow any whitespace in the pattern or Apache will break!
		$pattern = (string) preg_replace( '/\s+/', '\\s+', (string) $pattern );

		// If the "i" (case-insensitive) modifier was present, add the "NC" flag.
		if ( false !== mb_strpos( $parts[3], 'i' ) ) {
			$pattern .= ' [NC]';
		}

		return $pattern;
	}

	/**
	 * Validate a regex pattern.
	 *
	 * This method is merely a syntax check, using PHP's internal PCRE engine.
	 *
	 * @param string $pattern The regular expression pattern to validate.
	 *
	 * @return bool True if the pattern is valid (read: does not cause errors), false otherwise.
	 */
	public static function validateRegex( $pattern ) {
		// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		return false !== @preg_match( $pattern, '' );
	}
}
