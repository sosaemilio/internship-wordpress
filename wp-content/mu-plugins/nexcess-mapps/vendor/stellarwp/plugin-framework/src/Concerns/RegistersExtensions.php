<?php

namespace StellarWP\PluginFramework\Concerns;

use StellarWP\PluginFramework\Extensions\Plugins\PluginConfig;

/**
 * Create a registry of extension configurations.
 */
trait RegistersExtensions
{
    /**
     * An array containing all registered plugin configurations.
     *
     * @var Array<string,class-string<PluginConfig>>
     */
    protected $plugins = [];

    /**
     * Retrieve all registered plugin configurations.
     *
     * @return Array<string,class-string<PluginConfig>>
     */
    public function getPlugins()
    {
        return $this->plugins;
    }

    /**
     * Register a new plugin configuration.
     *
     * @param string                     $plugin The plugin to configure (e.g. "jetpack/jetpack.php").
     * @param class-string<PluginConfig> $config The PluginConfig class to apply.
     *
     * @return self
     */
    public function registerPlugin($plugin, $config)
    {
        $this->plugins[$plugin] = $config;

        return $this;
    }

    /**
     * Register a batch of plugin configurations.
     *
     * @param Array<string,class-string<PluginConfig>> $plugins Plugins to be registered.
     *
     * @return $this
     */
    public function registerPlugins($plugins)
    {
        $this->plugins = array_merge($this->plugins, $plugins);

        return $this;
    }
}
