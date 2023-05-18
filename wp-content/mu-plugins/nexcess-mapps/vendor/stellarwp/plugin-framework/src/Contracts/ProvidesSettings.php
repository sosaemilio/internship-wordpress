<?php

namespace StellarWP\PluginFramework\Contracts;

interface ProvidesSettings
{
    /**
     * Retrieve all settings.
     *
     * @return Array<string,mixed> All registered settings.
     */
    public function all();

    /**
     * Retrieve a setting by its key.
     *
     * @param string $key     The setting key to look for.
     * @param mixed  $default Optional. The default value to return if the key is not defined in the
     *                        $settings array. Default is null.
     *
     * @return mixed The value of the setting, or the value of $default if not provided.
     */
    public function get($key, $default = null);

    /**
     * Refresh the settings cache.
     *
     * @return self
     */
    public function refresh();
}
