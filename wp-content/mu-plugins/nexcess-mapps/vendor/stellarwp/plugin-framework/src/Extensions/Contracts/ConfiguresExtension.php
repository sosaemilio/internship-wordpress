<?php

namespace StellarWP\PluginFramework\Extensions\Contracts;

/**
 * Indicates that the class configures an extension (plugin or theme).
 */
interface ConfiguresExtension
{
    /**
     * Actions to perform upon activation of the extension.
     *
     * @return void
     */
    public function activate();

    /**
     * Actions to perform upon deactivation of the extension.
     *
     * @return void
     */
    public function deactivate();

    /**
     * Actions to perform every time the extension is loaded.
     *
     * @return void
     */
    public function load();

    /**
     * Actions to perform when the extension is updated.
     *
     * @return void
     */
    public function update();
}
