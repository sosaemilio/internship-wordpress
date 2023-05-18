<?php

namespace StellarWP\PluginFramework\Support;

use StellarWP\PluginFramework\Console\Command;

/**
 * Represents a series of instructions for performing an action.
 */
class InstructionSet
{
    /**
     * WP-CLI commands to be run.
     *
     * @var Array<int,Command>
     */
    protected $commands = [];

    /**
     * Add a command to the queue.
     *
     * @param Command $command The command to add.
     *
     * @return $this
     */
    public function addCommand(Command $command)
    {
        $this->commands[] = $command;

        return $this;
    }

    /**
     * Retrieve a series of WP-CLI commands to be run.
     *
     * @return Array<int,Command>
     */
    public function getCommands()
    {
        return $this->commands;
    }
}
