<?php

namespace StellarWP\PluginFramework\Console\Concerns;

trait HasShellDependencies
{
    /**
     * A cache of resolved binary paths.
     *
     * @var Array<string,string>
     */
    private static $binaryPaths = [];

    /**
     * Determine whether or not an executable can be found for the given command.
     *
     * @param string $command The command to resolve.
     *
     * @return bool True if an executable for the command could be found in the user's path,
     *              false otherwise.
     */
    protected function commandExists($command)
    {
        return (bool) $this->getCommandPath($command);
    }

    /**
     * Retrieve the system path for the given command's executable.
     *
     * @param string $command The command to resolve.
     *
     * @return string The system path to the executable, or an empty string if no executable could
     *                be located in the user's path.
     */
    protected function getCommandPath($command)
    {
        if (! array_key_exists($command, self::$binaryPaths)) {
            $cmd = sprintf('command -v %s', escapeshellarg($command));
            // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.system_calls_shell_exec
            self::$binaryPaths[$command] = trim((string) shell_exec($cmd));
        }

        return self::$binaryPaths[$command];
    }
}
