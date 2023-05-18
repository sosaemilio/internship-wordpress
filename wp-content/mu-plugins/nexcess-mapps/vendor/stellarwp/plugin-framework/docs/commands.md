# Writing CLI Commands

The StellarWP Plugin Framework bundles a number of custom [WP-CLI](https://wp-cli.org) commands, meant to aid with common support tasks and/or site provisioning.

[Like modules](modules.md), WP-CLI commands are resolved through [our Dependency Injection (DI) container](container.md), enabling us to define which dependencies are required for the command to run.

## The base WPCommand class

Custom WP-CLI commands should extend the base `StellarWP\PluginFramework\Console\WPCommand` class, which provides a few benefits:

* Better testability, as we're not relying on direct calls to static methods on the `WP_CLI` class.
* The ability to define custom output methods (such as `step()`), giving us a more consistent look.
* The ability to chain output commands in a fluent interface:

	```php
	# Before
	WP_CLI::log('');
	WP_CLI::log('Some message');
	WP_CLI::halt(1);

	# After
	$this->newline()->log('Some message')->halt(1);
	```

When defining new methods on the base class, make sure to mark the methods as **protected**, as WP-CLI will interpret any public methods as sub-commands!

### Context-aware commands

Sometimes, it's helpful for commands to know how they're registered within WP-CLI.

For a concrete example, look at [the `SupportUserCommand` class](support-users.md): upon creating a new support user, it provides instructions on how to delete this user once it's no longer needed.

```none
$ wp stellarwp support-user create
Success: A new support user has been created:

	url: https://example.com/wp-login.php
	username: stellarwp_support_62214076d5a21
	password: s0me$tr0ngP4$$w0rd!

This user will automatically expire in 24 hours. You may also remove it manually by running:

	$ wp stellarwp support-user delete 67
```

Thanks to the `StellarWP\PluginFramework\Console\Contracts\IsContextAware` interface, it's able to call `$this->getCommandNamespace()` to discover it lives under "stellarwp support-user" within WP-CLI.

For convenience, a `StellarWP\PluginFramework\Console\Concerns\IsContextAware` trait is available with a default implementation:

```php
use StellarWP\PluginFramework\Console\Concerns\IsContextAware;
use StellarWP\PluginFramework\Console\Contracts\IsContextAware as IsContextAwareContract;
use StellarWP\PluginFramework\WPCommand;

class MyCommand extends WPCommand implements IsContextAwareContract
{
    use IsContextAware;

    // ...
}
```

## Registering WP-CLI commands

Once a WP-CLI command has been written, it can be registered within the plugin via the `Plugin::$commands` array:

```php
/**
 * An array containing all registered WP-CLI commands.
 *
 * @var Array<string,string>
 */
protected $commands = [
    // Assume FirstCommand defines an __invoke() magic method.
    'stellarwp first-command'  => Console\Commands\FirstCommand::class,

    'stellarwp second-command' => [Console\Commands\SecondCommand::class, 'someMethod'],

    // Assume there are multiple public methods on ThirdCommand.
    'stellarwp third-command'  => Console\Commands\ThirdCommand::class,
];
```

In this array, the key represents the WP-CLI sub-command name, whereas the value is the command's callable; this may be a single class name (if the command class defines an `__invoke()` method) or an individual method.

To invoke the commands defined above:

```sh
# FirstCommand::__invoke()
$ wp stellarwp first-command

# SecondCommand::someMethod()
$ wp stellarwp second-command

# ThirdCommand::burger() and ThirdCommand::fries()
$ wp stellarwp third-command burger
$ wp stellarwp third-command fries
```

> 🏆  **Pro-tip:**<br>Registered WP-CLI commands will be resolved through the DI container, so you may use the class constructor to inject any services you might need.

## Invoking the CLI

Sometimes, it's necessary to invoke CLI commands (WP-CLI or otherwise) during regular, web request-based actions.

In order to do this safely, the StellarWP Plugin Framework exposes the `StellarWP\PluginFramework\Command` class, which is a wrapper around [PHP's `proc_open()`](php.net/manual/en/function.proc-open.php). Using this wrapper has a few advantages:

1. Built-in escaping for shell commands and arguments
2. Standardized capture of the command's exit code, as well as STDOUT and STDERR via [the `Response` object](#the-response-object)
3. Commands are automatically prefixed with both `nice` and `timeout`, ensuring it plays nice with the web server
4. Commands prefixed with "wp" will automatically be expanded to use the full system path to the WP-CLI binary

### The Response object

When invoking a CLI command via the `Command` class, the return value will be wrapped in [a `StellarWP\PluginFramework\Console\Response` object](../src/Console/Response.php), exposing the following methods:

<dl>
<dt>getExitCode(): int</dt>
<dd>Return the exit code from the command (0-255)</dd>
<dt>getOutput(): string</dt>
<dd>Return the contents of STDOUT</dd>
<dt>getErrors(): string</dt>
<dd>Return the contents of STDERR</dd>
<dt>wasSuccessful([bool $throw = false]): bool</dt>
<dd>True if the exit code was zero, false otherwise.</dd>
<dd>Passing <code>true</code> to the optional <code>$throw</code> parameter will cause a <code>StellarWP\PluginFramework\Exceptions\ConsoleException</code> to be thrown if a non-zero exit code was encountered.</dd>
</dl>

## Testing commands

Traditionally, [WP-CLI recommends using Behat to test commands](https://make.wordpress.org/cli/handbook/guides/commands-cookbook/#writing-tests). However, Behat starts to become cumbersome when we need to perform more-sophisticated actions, and wouldn't tie well into our DI container.

Instead, our test suite includes the `StellarWP\PluginFramework\Concerns\TestsCommands` trait, which bootstraps the WP-CLI package directly and lets us register our commands—including test doubles—and test using PHPUnit:

```php
use StellarWP\MyPlugin\Console\Commands\PrintPHPVersion;
use StellarWP\MyPlugin\Settings;

/**
 * @test
 */
public function the_PrintPHPVersion_command_should_print_the_PHP_version_from_Settings()
{
    // Provide the container a new definition for the Settings object.
    $this->container->extend(Settings::class, new Settings([
        'php_version' => '6.0',
    ]));

    // Invoke the PrintPHPVersion WP-CLI command
    $response = $this->cli(PrintPHPVersion::class);

    // Check for a non-zero exit code
    $this->assertTrue($response->wasSuccessful());
    $this->assertSame('PHP version: 6.0', $response->getOutput());
}
```

The `TestsCommands::cli()` command can accept any of the following:

1. A command name (e.g. "some-command --some-option")
2. A command class (e.g. `MyCommandClass`), which would then be resolved through the DI container
3. A callable in array form (e.g. `[ $instance, 'methodName' ]` or `[ MyCommandClass::class, 'methodName' ]`)

Under the hood, anything that isn't a string will be passed to `TestsCommands::registerCliCommand()` with a `test:` command name, then resolved within WP-CLI. If you need to pass arguments to the command being tested, you should explicitly register the command before calling it:

```php
$this->registerCliCommand('my-command', MyCommand::class);
$this->cli('my-command arg1 arg2 --format=list');
```

The `TestsCommands::registerCliCommand()` method can also be used to register other commands. For example, if `SomeCommand` relies on calling `wp stellarwp some-other-command`, you could inject that command before calling `$this->cli()`:

```php
$this->registerCliCommand('stellarwp some-other-command', SomeOtherCommand::class);
$this->cli(SomeCommand::class);
```
