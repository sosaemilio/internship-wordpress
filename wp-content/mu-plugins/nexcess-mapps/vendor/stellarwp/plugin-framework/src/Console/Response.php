<?php

namespace StellarWP\PluginFramework\Console;

use StellarWP\PluginFramework\Exceptions\ConsoleException;

/**
 * A representation of the result of running a console command.
 */
class Response
{
    /**
     * The resolved command that was invoked.
     *
     * @var string
     */
    protected $command;

    /**
     * STDERR from the command.
     *
     * @var string
     */
    protected $errors;

    /**
     * The exit code from the command.
     *
     * @var int
     */
    protected $exitCode;

    /**
     * STDOUT from the command.
     *
     * @var string
     */
    protected $output;

    /**
     * Whether or not to strip ANSI escape sequences from captured output.
     *
     * @var self::OUTPUT_NORMAL|self::OUTPUT_PLAIN
     */
    protected $outputMode = self::OUTPUT_NORMAL;

    /**
     * Return output as it was captured.
     */
    const OUTPUT_NORMAL = 'normal';

    /**
     * Return output without ANSI escape sequences.
     */
    const OUTPUT_PLAIN = 'plain';

    /**
     * Construct a new ConsoleResponse object.
     *
     * @param string $command   The resolved command that was invoked.
     * @param int    $exit_code The exit code from the command.
     * @param string $output    Optional. STDOUT from the command. Default is empty.
     * @param string $errors    Optional. STDERR from the command. Default is empty.
     */
    public function __construct($command, $exit_code, $output = '', $errors = '')
    {
        $this->command  = (string) $command;
        $this->exitCode = (int) $exit_code;
        $this->output   = (string) $output;
        $this->errors   = (string) $errors;
    }

    /**
     * Retrieve the exact command that was called.
     *
     * @return string The full command.
     */
    public function getCommand()
    {
        return $this->command;
    }

    /**
     * Retrieve the command errors.
     *
     * @return string STDERR from the command.
     */
    public function getErrors()
    {
        $output = trim($this->errors);

        return self::OUTPUT_PLAIN === $this->outputMode
            ? $this->stripAnsiEscapeSequences($output)
            : $output;
    }

    /**
     * Retrieve the exit code.
     *
     * @return int The exit code from the command.
     */
    public function getExitCode()
    {
        return $this->exitCode;
    }

    /**
     * Retrieve the command output.
     *
     * @return string STDOUT from the command.
     */
    public function getOutput()
    {
        $output = trim($this->output);

        return self::OUTPUT_PLAIN === $this->outputMode
            ? $this->stripAnsiEscapeSequences($output)
            : $output;
    }

    /**
     * Toggle the values of getOutput() and getExitCode() from standard to "plain" (e.g. no ANSI
     * escape sequences).
     *
     * @param self::OUTPUT_NORMAL|self::OUTPUT_PLAIN $mode The output mode.
     *
     * @return $this
     */
    public function setOutputMode($mode)
    {
        $this->outputMode = self::OUTPUT_PLAIN === $mode ? $mode : self::OUTPUT_NORMAL;

        return $this;
    }

    /**
     * Determine whether or not the command was successful.
     *
     * @param bool $throw Optional. If true, a ConsoleException will be thrown if the command was unsuccessful.
     *                    Default is false.
     *
     * @throws ConsoleException If $throw is true and the command exited with a non-zero exit code.
     *
     * @return bool True if the command was successful (a zero exit code) or false if something went wrong.
     */
    public function wasSuccessful($throw = false)
    {
        $successful = 0 === $this->exitCode;

        if ($throw && ! $successful) {
            throw new ConsoleException(sprintf(
                /* Translators: %1$d is the command's exit code. */
                __('Received a non-zero exit code: %1$d', 'stellarwp-framework'),
                $this->exitCode
            ), $this->exitCode);
        }

        return $successful;
    }

    /**
     * Strip ANSI escape sequences from a string.
     *
     * @param string $string The string to strip.
     *
     * @return string The value of $string, sans ANSI escape codes.
     */
    protected function stripAnsiEscapeSequences($string)
    {
        return (string) preg_replace('/\e\[(.*?)m/', '', $string);
    }
}
