<?php

namespace StellarWP\PluginFramework\Console\Concerns;

/**
 * A standard implementation of {@see StellarWP\PluginFramework\Console\Contracts\IsContextAware}.
 */
trait HasContextAwareness
{
    /**
     * The WP-CLI command namespace.
     *
     * @var string
     */
    private $commandNamespace = '';

    /**
     * Retrieve the current command namespace.
     *
     * @return string The WP-CLI namespace under which this command class is registered.
     */
    public function getCommandNamespace()
    {
        return $this->commandNamespace;
    }

    /**
     * Set the current command namespace.
     *
     * @param string $namespace The WP-CLI namespace under which this command class is registered.
     *
     * @return self
     */
    public function setCommandNamespace($namespace)
    {
        $this->commandNamespace = (string) $namespace;

        return $this;
    }
}
