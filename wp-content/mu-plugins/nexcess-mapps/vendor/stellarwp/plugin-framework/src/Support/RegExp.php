<?php

namespace StellarWP\PluginFramework\Support;

/**
 * Helpers for dealing with regular expressions.
 */
class RegExp
{
    /**
     * Validate whether or not a regular expression pattern is valid.
     *
     * Note that this will *not* test whether or not the pattern works, only that it's syntax is valid.
     *
     * @param string $pattern The regular expression to validate.
     *
     * @return bool True if the pattern is a valid regular expression, false otherwise.
     */
    public static function validate($pattern)
    {
        // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
        return false !== @preg_match($pattern, '');
    }
}
