<?php

namespace StellarWP\PluginFramework\Support;

/**
 * Helpers for working with HTML attributes.
 */
class Attributes
{
    /**
     * Given an associative array, construct a string of (escaped) data-attributes.
     *
     * @param Array<string,mixed> $atts Attributes to transform. Non-associative keys will be ignored,
     *                                  and non-scalar values will be JSON-encoded.
     *
     * @return string A string of the transformed $atts as HTML data-attributes.
     */
    public static function getDataAttributeString(array $atts)
    {
        $attributes = [];

        foreach ($atts as $key => $value) {
            if (! is_string($key)) {
                continue;
            }

            // Strip "data-" prefix if already present.
            if (0 === mb_strpos($key, 'data-')) {
                $key = mb_substr($key, 5);
            }

            // Convert non-scalar value to JSON.
            if (! is_scalar($value)) {
                $value = wp_json_encode($value);
            }

            $attributes[$key] = sprintf('data-%s="%s"', Str::kebab($key), esc_attr((string) $value));
        }

        return implode(' ', $attributes);
    }
}
