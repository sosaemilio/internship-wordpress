<?php

namespace StellarWP\PluginFramework\Concerns;

trait HasPluggables
{
    /**
     * Explicitly load the Pluggables file and ensure that $this->settings is injected.
     */
    public function loadPluggables()
    {
        $settings = $this->settings;

        // Use require instead of require_once for the sake of automated tests.
        require dirname(__DIR__) . '/Support/Pluggable.php';
    }
}
