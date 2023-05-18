<?php

/**
 * Helper methods.
 */

namespace StellarWP\PluginFramework\Support;

class Helpers
{
    /**
     * Return either "Enabled" or "Disabled" based on the value of $is_enabled.
     *
     * @param bool $is_enabled Whether or not a particular flag is enabled.
     *
     * @return string One of "Enabled" or "Disabled".
     */
    public static function getEnabled($is_enabled)
    {
        return $is_enabled
            ? _x('Enabled', 'setting state', 'stellarwp-framework')
            : _x('Disabled', 'setting state', 'stellarwp-framework');
    }
}
