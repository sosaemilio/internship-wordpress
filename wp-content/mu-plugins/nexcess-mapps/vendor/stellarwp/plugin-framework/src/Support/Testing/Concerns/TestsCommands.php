<?php

namespace StellarWP\PluginFramework\Support\Testing\Concerns;

use PHPUnit\Framework\Assert;
use PHPUnit\Framework\Exception as PHPUnitException;
use StellarWP\PluginFramework\Console\Response;
use StellarWP\PluginFramework\Support\Buffer;
use WP_CLI;
use WP_CLI\ExitException;
use WP_CLI\Loggers\Base as BaseLogger;

use function WP_CLI\Utils\parse_str_to_argv;

/**
 * Methods to help in testing WP-CLI commands.
 *
 * This trait will ensure that WP-CLI will not exit on errors, replace the logger with a mock
 * (available via $this->mockLogger), and reset WP-CLI command registries between tests.
 */
trait TestsCommands
{
    /**
     * A mocked instance of the stubbed WP-CLI logger.
     *
     * @var \Mockery\Mock
     */
    protected $mockLogger;

    /**
     * A snapshot of all registered WP-CLI commands at the beginning of the test.
     *
     * @var Array<string|int>
     */
    private $commandSnapshot;

    /**
     * @before
     */
    protected function configureWPCLI()
    {
        // Take a snapshot of all currently-registered commands.
        $this->commandSnapshot = array_keys(WP_CLI::get_root_command()->get_subcommands());

        // Ensure that WP-CLI never actually calls exit().
        $this->setProtectedProperty(WP_CLI::class, 'capture_exit', true);

        $this->mockLogger = $this->mock(BaseLogger::class)
            ->shouldIgnoreMissing();

        // Explicitly (re)set WP-CLI between tests.
        WP_CLI::set_logger($this->mockLogger);
        $this->setProtectedProperty(WP_CLI::class, 'hooks', []);
        $this->setProtectedProperty(WP_CLI::class, 'hooks_passed', []);
        $this->setProtectedProperty(WP_CLI::class, 'deferred_additions', []);

        // Turn off console colorization.
        $this->setConsoleColorization(false);

        // Force the runner to always be loaded.
        WP_CLI::get_runner()->init_config();
    }

    /**
     * @after
     */
    protected function resetWPCLI()
    {
        // Unregister any commands that weren't present at the beginning of the test.
        $command = WP_CLI::get_root_command();

        foreach (array_diff(array_keys($command->get_subcommands()), $this->commandSnapshot) as $name) {
            $command->remove_subcommand($name);
        }
    }

    /**
     * Assert that the given command has not been registered.
     *
     * @param string $command The command name to look for.
     * @param string $message Optional context to include if the assertion fails. Default is empty.
     *
     * @return void
     */
    protected function assertCommandWasNotRegistered($command, $message = '')
    {
        if (! $message) {
            $message = sprintf('Failed asserting that "%s" was not among registered WP-CLI commands.', $command);
        }

        $subcommand = WP_CLI::get_runner()->find_command_to_run(explode(' ', $command));

        Assert::assertFalse(is_array($subcommand), $message);
    }
    /**
     * Assert that the given command has been registered.
     *
     * @param string $command The command name to look for.
     * @param string $message Optional context to include if the assertion fails. Default is empty.
     *
     * @return void
     */
    protected function assertCommandWasRegistered($command, $message = '')
    {
        if (! $message) {
            $message = sprintf('Failed asserting that "%s" was among registered WP-CLI commands.', $command);
        }

        $subcommand = WP_CLI::get_runner()->find_command_to_run(explode(' ', $command));

        Assert::assertTrue(is_array($subcommand), $message);
    }

    /**
     * Assert that the given string contains an ASCII table row representation of the given data.
     *
     * @param Array<scalar> $row     An array of cells within a single row.
     * @param string        $output  The console output.
     * @param string        $message Optional context to include if the assertion fails. Default is empty.
     *
     * @return void
     */
    protected function assertContainsTableRow(array $row, $output, $message = '')
    {
        $pattern = $this->buildAsciiTableRowRegex($row);
        $this->assertMatchesRegularExpression($pattern, $output, $message);
    }

    /**
     * Assert that the given string contains an ASCII table row representation of the given data.
     *
     * @param Array<scalar> $row     An array of cells within a single row.
     * @param string        $output  The console output.
     * @param string        $message Optional context to include if the assertion fails. Default is empty.
     *
     * @return void
     */
    protected function assertDoesNotContainsTableRow(array $row, $output, $message = '')
    {
        $pattern = $this->buildAsciiTableRowRegex($row);
        $this->assertDoesNotMatchRegularExpression($pattern, $output, $message);
    }

    /**
     * Invoke a WP-CLI command.
     *
     * This will find the command as registered, resolve it through the container, then invoke the
     * specified method.
     *
     * @param string|class-string|callable $command The WP-CLI command to execute. This can be the name of a
     *                                              previously-registered command, a WP_CLI command class, or some
     *                                              other callable.
     * @param Array<string>                $args    Optional. Arguments to pass to the command (as they would be passed
     *                                              via the CLI, e.g. "--key=value"). Default is empty.
     *
     * @throws \InvalidArgumentException If given a command that cannot be resolved to callable,
     *         PHPUnitException If one is caught in our error handling.
     *
     * @return Response
     */
    protected function cli($command, array $args = [])
    {
        // If given a class name, resolve it through the DI container.
        if (is_string($command) && class_exists($command)) {
            $command = $this->container->get($command);
        } elseif (is_array($command) && isset($command[0]) && is_string($command[0]) && class_exists($command[0])) {
            $command[0] = $this->container->get($command[0]);
        }

        // If we have a callable, register it within WP-CLI under the "test:" namespace.
        if (is_callable($command)) {
            $name = uniqid('test:');
            $this->registerCliCommand($name, $command);
            $command = $name;
        } else {
            throw new \InvalidArgumentException(sprintf(
                'Command is not callable: %s',
                var_export($command, true) // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_var_export
            ));
        }

        if (! is_string($command)) {
            throw new \InvalidArgumentException('Expected the command to resolve to a string name.');
        }

        $config = WP_CLI::get_configurator();
        $args   = $config->parse_args(array_merge(parse_str_to_argv($command), $args));
        $exit   = 0;
        $buffer = new Buffer(function ($args) use (&$exit) {
            try {
                WP_CLI::get_runner()->run_command(...$args);
            } catch (ExitException $e) {
                $exit = $e->getCode();
            } catch (PHPUnitException $e) {
                throw $e;
            } catch (\Exception $e) {
                $exit = 1;
                throw $e;
            }
        });

        $buffer->run($args);
        $error = $buffer->getError();

        // Don't silence PHPUnit exceptions or risk debugging being a total pain.
        if ($error instanceof PHPUnitException) {
            throw $error;
        }

        return new Response($command, $exit, $buffer->getOutput(), $error ? $error->getTraceAsString() : '');
    }

    /**
     * Register a WP-CLI command.
     *
     * @see WP_CLI::add_command()
     *
     * @param string   $name     The command name.
     * @param callable $callable The WP-CLI method.
     *
     * @return bool True if the command was registered, false if it was deferred.
     */
    protected function registerCliCommand($name, $callable)
    {
        return WP_CLI::add_command($name, $callable);
    }

    /**
     * Set the colorization state of the WP-CLI runner.
     *
     * @param bool $colorize True if colors should be enabled, false otherwise.
     *
     * @return self
     */
    protected function setConsoleColorization($colorize)
    {
        $this->setProtectedProperty(WP_CLI::get_runner(), 'colorize', $colorize);

        return $this;
    }

    /**
     * Construct a regular expression for a single table row.
     *
     * @param Array<scalar> $row The contents of the table row.
     *
     * @return string A regular expression that can be used to match this row in ASCII tables.
     */
    private function buildAsciiTableRowRegex(array $row)
    {
        // Construct a regular expression for each cell.
        $cells = array_map(function ($cell) {
            return '\s*?' . preg_quote(trim($cell), '/') . '\s*?';
        }, $row);

        // Look for a matching line with minimal concern for whitespace.
        return '/\n?\|?' . implode('[\s\|]+?', $cells) . '\|?/';
    }
}
