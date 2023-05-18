<?php

namespace StellarWP\PluginFramework\Extensions\Plugins;

use StellarWP\PluginFramework\Extensions\Contracts\ConfiguresExtension;

/**
 * A base definition for plugin configurations.
 */
abstract class PluginConfig implements ConfiguresExtension
{
    /**
     * The absolute path to the plugin directory.
     *
     * @var string
     */
    protected $pluginDir;

    /**
     * Actions to perform upon plugin activation.
     *
     * @param bool $network_wide Optional. Is the plugin being activated network-wide?
     *                           Default is false.
     *
     * @return void
     */
    public function activate($network_wide = false)
    {
        // No-op by default.
    }

    /**
     * Actions to perform upon plugin deactivation.
     *
     * @param bool $network_wide Optional. Is the plugin being deactivated network-wide?
     *                           Default is false.
     *
     * @return void
     */
    public function deactivate($network_wide = false)
    {
        // No-op by default.
    }

    /**
     * Actions to perform every time the plugin is loaded.
     *
     * @return void
     */
    public function load()
    {
        // No-op by default.
    }

    /**
     * Set the plugin directory.
     *
     * @param string $dir The plugin directory.
     *
     * @return $this
     */
    public function setPluginDir($dir)
    {
        $this->pluginDir = untrailingslashit($dir);

        return $this;
    }

    /**
     * Actions to perform when the plugin is updated.
     *
     * @return void
     */
    public function update()
    {
        // No-op by default.
    }
}
