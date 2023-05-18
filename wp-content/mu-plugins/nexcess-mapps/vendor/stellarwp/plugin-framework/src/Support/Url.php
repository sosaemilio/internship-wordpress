<?php

namespace StellarWP\PluginFramework\Support;

/**
 * Helpers for dealing with URLs.
 */
class Url
{
    /**
     * Perform a URL-safe base64_encode().
     *
     * @link https://www.php.net/manual/en/function.base64-encode.php#123098
     *
     * @param string $string The string to encode.
     *
     * @return string The encoded string.
     */
    public static function base64Urlencode($string)
    {
        // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
        return str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($string));
    }

    /**
     * Perform a URL-safe base64_decode().
     *
     * @link https://www.php.net/manual/en/function.base64-encode.php#123098
     *
     * @param string $string The string to decode.
     *
     * @return string The decoded string.
     */
    public static function base64Urldecode($string)
    {
        // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode
        return base64_decode(str_replace(['-', '_'], ['+', '/'], $string));
    }

    /**
     * Safely parse the URL Host from a string.
     *
     * @param string $string The string to parse.
     *
     * @return string The domain portion of a string. Will return empty if the provided string is not a valid URL.
     */
    public static function parseUrlHost($string)
    {
        $valid_domain = defined('FILTER_VALIDATE_DOMAIN')
            // phpcs:ignore PHPCompatibility.Constants.NewConstants.filter_validate_domainFound
            ? (bool) filter_var($string, FILTER_VALIDATE_DOMAIN)
            : (bool) filter_var($string, FILTER_VALIDATE_URL);

        if (! $valid_domain) {
            return '';
        }

        $domain = wp_parse_url($string, PHP_URL_HOST);

        return is_string($domain) ? $domain : '';
    }

    /**
     * Determine if the provided array contains the domain
     *
     * @param string       $needle Domain to test.
     * @param Array<mixed> $haystack
     *
     * @return bool
     */
    public static function domainMatches($needle, $haystack)
    {
        $domain = mb_strtolower(self::parseUrlHost($needle));

        return in_array(
            $domain,
            array_map(function ($value) {
                return is_string($value) ? mb_strtolower($value) : $value;
            }, $haystack),
            true
        );
    }
}
