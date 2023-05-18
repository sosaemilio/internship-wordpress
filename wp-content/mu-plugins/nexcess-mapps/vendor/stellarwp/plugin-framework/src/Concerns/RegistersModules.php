<?php

namespace StellarWP\PluginFramework\Concerns;

use StellarWP\PluginFramework\Modules\Module;

/**
 * Create a registry of modules.
 */
trait RegistersModules
{
    /**
     * An array containing all registered modules.
     *
     * @var Array<int,class-string<Module>>
     */
    protected $modules = [];

    /**
     * Retrieve all registered modules.
     *
     * @return Array<int,class-string<Module>>
     */
    public function getModules()
    {
        return $this->modules;
    }

    /**
     * Register a new module.
     *
     * @param class-string<Module> $module The fully-qualified module class name.
     *
     * @return self
     */
    public function registerModule($module)
    {
        if (! in_array($module, $this->modules, true)) {
            $this->modules[] = $module;
        }

        return $this;
    }
}
