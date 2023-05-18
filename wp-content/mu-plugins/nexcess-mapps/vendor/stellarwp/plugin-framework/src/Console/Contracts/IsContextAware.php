<?php

namespace StellarWP\PluginFramework\Console\Contracts;

/**
 * Indicates that a command should be aware how it's registered within the plugin.
 *
 * When this interface is applied to a command class, Plugin::loadCommand() will inject the
 * command's WP-CLI namespace via `setCommandNamespace()`.
 *
 * For example, imagine SomeCommand implements ContextAware, and is added to Plugin::$commands as:
 *
 *     $commands = [
 *         'stellarwp some-command' => SomeCommand::class,
 *     ];
 *
 * When calling $this->getCommandNamespace() from within the command class, the method will return
 * "stellarwp some-command".
 */
interface IsContextAware
{
    /**
     * Retrieve the current command namespace.
     *
     * @return string The WP-CLI namespace under which this command class is registered.
     */
    public function getCommandNamespace();

    /**
     * Set the current command namespace.
     *
     * @param string $namespace The WP-CLI namespace under which this command class is registered.
     *
     * @return self
     */
    public function setCommandNamespace($namespace);
}
