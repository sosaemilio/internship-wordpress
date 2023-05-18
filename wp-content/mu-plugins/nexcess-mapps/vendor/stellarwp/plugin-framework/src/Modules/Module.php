<?php

namespace StellarWP\PluginFramework\Modules;

/**
 * The basic definition for a module.
 */
abstract class Module
{
    /**
     * Perform any necessary setup for the module.
     *
     * This method is automatically called as part of Plugin::load_modules(), and is the
     * entry-point for all modules.
     *
     * @return void
     */
    abstract public function setup();
}
