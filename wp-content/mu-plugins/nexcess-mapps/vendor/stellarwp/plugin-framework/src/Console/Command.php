<?php

/**
 * A way to invoke CLI commands from within the plugin, including support for the "nice" and
 * "timeout" commands.
 */

namespace StellarWP\PluginFramework\Console;

use StellarWP\PluginFramework\Console\Concerns\HasShellDependencies;
use StellarWP\PluginFramework\Console\Concerns\InteractsWithWpCli;

class Command
{
    use HasShellDependencies;
    use InteractsWithWpCli;

    /**
     * Arguments that have been passed to the command.
     *
     * @var Array<int|string,mixed>
     */
    protected $arguments;

    /**
     * The command to be executed.
     *
     * @var string
     */
    protected $command;

    /**
     * The priority when using the "nice" command.
     *
     * @var int
     */
    protected $priority = 18;

    /**
     * A cache of the parsed, escaped shell command.
     *
     * @var ?string
     */
    protected $shellCommand;

    /**
     * The maximum number of seconds to run the command before timing out.
     *
     * @var int
     */
    protected $timeout = 60;

    /**
     * Create a new ConsoleCommand instance.
     *
     * @param string       $command   The command to run.
     * @param Array<mixed> $arguments Optional. An array of arguments. Numeric keys will be treated as
     *                                [positional] arguments, while strings will be treated as options.
     *                                Default is empty.
     */
    public function __construct($command, array $arguments = [])
    {
        $this->command   = $command;
        $this->arguments = $arguments;
    }

    /**
     * Execute the command.
     *
     * @return Response A representation of the console response.
     */
    public function execute()
    {
        return $this->executeCommand($this->getShellCommand());
    }

    /**
     * Retrieve the raw, unescaped arguments array as it was passed to the constructor.
     *
     * @return Array<mixed> The original, unescaped arguments array.
     */
    public function getRawArguments()
    {
        return $this->arguments;
    }

    /**
     * Retrieve the raw, unescaped command as it was passed to the constructor.
     *
     * @return string The original, unescaped command.
     */
    public function getRawCommand()
    {
        return $this->command;
    }

    /**
     * Get the full, escaped command string as it will be passed to the shell.
     *
     * @return string The command, ready to hand to the shell.
     */
    public function getShellCommand()
    {
        if (null === $this->shellCommand) {
            $arguments = array_map('escapeshellarg', $this->parseArguments($this->arguments));

            // Construct the basic command.
            $command = trim(sprintf('%s %s', $this->command, implode(' ', $arguments)));

            // If we're calling the WP-CLI binary, use the full system path.
            if (0 === mb_strpos($command, 'wp ')) {
                $command = $this->getWpBinary() . mb_substr($command, 2);
            }

            // Set a timeout for all commands.
            if ($this->commandExists('timeout')) {
                $command = sprintf('timeout %d %s', $this->timeout, $command);
            }

            // Finally, prefix all commands with nice.
            if ($this->commandExists('nice')) {
                $command = sprintf('nice -n %d %s', $this->priority, $command);
            }

            $this->shellCommand = $command;
        }

        return $this->shellCommand;
    }

    /**
     * Set the command priority.
     *
     * @param int $priority The priority value, an integer between -20 and 19.
     *
     * @throws \OutOfRangeException If given an invalid $priority.
     *
     * @return self
     */
    public function setPriority($priority)
    {
        $priority = (int) $priority;

        if ($priority < -20 || $priority > 19) {
            throw new \OutOfRangeException(
                'The priority levels for the "nice" command range from -20—19. Run `man nice` for details.'
            );
        }

        $this->priority     = $priority;
        $this->shellCommand = null;

        return $this;
    }

    /**
     * Set the command timeout.
     *
     * @param int $timeout The timeout value (in seconds).
     *
     * @throws \OutOfRangeException If given a value <= 0.
     *
     * @return self
     */
    public function setTimeout($timeout)
    {
        $timeout = (int) $timeout;

        if ($timeout <= 0) {
            throw new \OutOfRangeException('Timeout values must be greater than 0.');
        }

        $this->timeout      = $timeout;
        $this->shellCommand = null;

        return $this;
    }

    /**
     * Parse arguments passed to a command.
     *
     * @param Array<int|string,mixed> $arguments Optional. An array of arguments. Numeric keys will be treated as
     *                                           [positional] arguments, while strings will be treated as options.
     *                                           Default is empty.
     *
     * @throws \InvalidArgumentException If given an argument that cannot be parsed.
     *
     * @return Array<int,string> An array of arguments, ready to be passed to a script.
     *                           Note that the arguments are NOT escaped!
     */
    protected function parseArguments(array $arguments = [])
    {
        $parsed = [];

        foreach ($arguments as $key => $value) {
            // Numeric keys should be stripped.
            if (is_int($key)) {
                $key = '';
            }

            // Coerce values.
            if (is_array($value)) {
                $value = implode(',', $value);
            } elseif (is_bool($value)) {
                // No value if true, no presence at all if false.
                if ($value) {
                    $value = null;
                } else {
                    continue;
                }
            }

            // Non-scalar values that we haven't already handled are problematic.
            if (! is_scalar($value) && null !== $value) {
                throw new \InvalidArgumentException(sprintf(
                    'Unable to process type %s of argument %s',
                    gettype($value),
                    $key
                ));
            }

            // Finally, make sure everything is treated as a string.
            $parsed[] = (string) $key;
            $parsed[] = (string) $value;
        }

        return array_values(array_filter($parsed));
    }

    /**
     * Execute the given command.
     *
     * @param string $command The full command to execute.
     *
     * @return Response A representation of the console response.
     */
    protected function executeCommand($command)
    {
        $output = '';
        $errors = '';
        $code   = 0;

        try {
            // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.system_calls_proc_open
            $proc = proc_open(escapeshellcmd($command), [
                0 => ['pipe', 'r'],
                1 => ['pipe', 'w'],
                2 => ['pipe', 'w'],
            ], $pipes);

            if (is_resource($proc)) {
                $output = (string) stream_get_contents($pipes[1]);
                $errors = (string) stream_get_contents($pipes[2]);
                $code   = proc_close($proc);
            }
        } catch (\Exception $e) {
            $code   = $e->getCode();
            $errors = $e->getMessage() . PHP_EOL . PHP_EOL . $e->getTraceAsString();
        }

        return new Response($command, $code, trim($output), trim($errors));
    }
}
