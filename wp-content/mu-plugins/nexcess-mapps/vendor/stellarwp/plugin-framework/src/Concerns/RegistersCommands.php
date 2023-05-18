<?php

namespace StellarWP\PluginFramework\Concerns;

use StellarWP\PluginFramework\Console\WPCommand;

/**
 * Create a registry of WP-CLI commands.
 */
trait RegistersCommands
{
    /**
     * An array containing all registered WP-CLI commands.
     *
     * @var Array<string,class-string<WPCommand>>
     */
    protected $commands = [];

    /**
     * Retrieve all registered commands.
     *
     * @return Array<string,class-string<WPCommand>>
     */
    public function getCommands()
    {
        return $this->commands;
    }

    /**
     * Register a new command.
     *
     * @param string                  $name    How the command will be referenced within WP-CLI
     *                                         (e.g. "stellarwp some-command").
     * @param class-string<WPCommand> $command The container abstract corresponding to the command.
     *
     * @return self
     */
    public function registerCommand($name, $command)
    {
        if (! in_array($command, $this->commands, true)) {
            $this->commands[$name] = $command;
        }

        return $this;
    }
}
