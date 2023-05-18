# Testing Tools

Due to the tightly-coupled nature of WordPress, testing plugins is very different than "pure" unit testing. To help in this effort, the StellarWP Plugin Framework provides a number of tools to make testing easier:

## The base TestCase

The framework includes its own base `TestCase` class, which extends the WordPress core test suite's `WP_UnitTestCase` class (which is itself an extension of `PHPUnit\Framework\TestCase`). It's recommended that all test classes for individual plugins extend this class (or a sub-class of it):

```php
namespace Tests\Unit;

use StellarWP\PluginFramework\Support\Testing\TestCase;

class SomeTest extends TestCase
{
    // ...
}
```

For convenience, the base `TestCase` also includes a number of the traits in this document.

## Interacting with the DI container

It's very common that we need to work with [the Dependency Injection (DI) container](container.md) in our tests, so the `StellarWP\PluginFramework\Support\Testing\Concerns\InteractsWithContainer` trait will automatically create a fresh container instance in `$this->container` at the beginning of each test.

> **Note:** This trait is already included for you if you extend the base `TestCase` class.

```php
namespace Tests\Unit;

use StellarWP\PluginFramework\Support\Testing\Concerns\InteractsWithContainer;
use StellarWP\SomePlugin\SomeClass;
use WP_UnitTestCase;

class SomeTest extends WP_UnitTestCase
{
    use InteractsWithContainer;

    /**
     * @test
     */
    public function demo_container_resolutions_in_test_methods()
    {
        $instance = $this->container->get(SomeClass::class);

        $this->assertInstanceOf(SomeClass::class, $instance);
    }
}
```

This trait also sets up an instance of [our `TestLogger`](#inspecting-logs), available via `$this->logger`.

## Testing WP-CLI commands

Testing WP-CLI commands can be a bit tricky, but the `StellarWP\PluginFramework\Support\Testing\Concerns\TestsCommands` makes it easier by automatically handling the following:

* Prevent WP-CLI from exiting the PHP process (terminating the test runner in the process)
* Mock WP-CLI's logger, exposing it at `$this->mockLogger`
* Explicitly reset WP-CLI's registered commands and caches between tests

In addition to some custom PHPUnit assertions around the registration of commands or particular outputs, the trait also exposes the `cli()` command, which lets us invoke WP-CLI with defined methods _or_ by dynamically registering new commands:

```php
# Run an existing command.
$this->cli('plugin install', [
    'akismet',
    'jetpack',
]);

# Run a custom, invokable command.
$this->cli(SetupCommand::class, [
    '--provision',
]);

# Run a specific method on a command class.
$this->cli([SupportUsersCommand::class, 'delete'], [
    '--all',
]);
```

These commands will then be run in a `StellarWP\PluginFramework\Support\Buffer` instance, then return a `StellarWP\PluginFramework\Console\Response` object with all captured output.

## Working with Mockery

The framework has built-in support for [Mockery](https://docs.mockery.io/en/latest/index.html), a powerful tool for creating test doubles. However, [Mockery's default `Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration` trait](https://docs.mockery.io/en/latest/reference/phpunit_integration.html) is incompatible with [the `yoast/phpunit-polyfills` package](https://github.com/Yoast/PHPUnit-Polyfills/) required by the WordPress core test suite.

If you intend to use Mockery in your test suite, you should include the `StellarWP\PluginFramework\Support\Testing\Concerns\UsesMockery` trait.

> **Note:** This trait is already included for you if you extend the base `TestCase` class.

## Working with Reflection

[PHP's Reflection capabilities](https://www.php.net/manual/en/book.reflection.php) can often be useful in testing, especially if we need to invoke a normally-protected or private method. This way, we can access these methods in a test environment without exposing them as part of the public API of a class.

The `StellarWP\PluginFramework\Support\Testing\Concerns\UsesReflection` trait exposes a couple methods to help in using Reflection in our tests:

```php
# Get the value of a protected/private property.
$this->getProtectedProperty($object, 'someProp');
$this->getProtectedProperty(SomeClass::class, 'someStaticProp');

# Set the value of a protected/private property.
$this->setProtectedProperty($object, 'someProp', 'some value');
$this->setProtectedProperty(SomeClass::class, 'someStaticProp', 'some other value');

# Invoke a protected/private method.
$this->invokeProtectedMethod($object, 'someMethod', ...$args);
$this->invokeProtectedMethod(SomeClass::class, 'someStaticMethod', ...$args);
```

Common use-cases for these methods might include:

* Ensuring that data passed a class is mapped properly to class properties
* Priming a protected/private cache property to ensure subsequent requests return from cache
* Invoking protected methods that are outside of the public API but still require testing

> **Note:** This trait is already included for you if you extend the base `TestCase` class.

### Auto-wiring for tests

The StellarWP Plugin Framework generally avoids auto-wiring (e.g. using reflection to determine class dependencies and automatically injecting them) for the sake of performance: while reflection has certainly become more-performant in recent versions of PHP, the most-performant solution is to be explicit in our container definitions.

In tests, however, performance is less of a concern and it can be useful to dynamically resolve dependencies via reflection; the most common instance here would injecting test doubles as dependencies. For example, consider the following `CoffeeBar` class:

```php
class CoffeeBar
{
    protected $barista;
    protected $music;

    public function __construct(Barista $barista, MusicPlayer $music)
    {
        $this->barista = $barista;
        $this->music   = $music;
    }

    public function makeCoffee($order)
    {
        return $this->barista->prepareDrink($order);
    }

    // ...
}
```

If we want to test the `makeCoffee()` method, it might look something like this:

```php
use Mockery;

public function the_barista_should_make_coffee_if_the_inventory_is_available()
{
    $barista = Mockery::mock(Barista::class);
    $barista->shouldReceive('prepareDrink')
        ->once()
        ->with('cappuccino')
        ->andReturn(new Cappuccino());

    // Why are we creating a mock for the MusicPlayer?
    $instance = new CoffeeBar($barista, Mockery::mock(MusicPlayer::class));
    $this->assertInstanceOf(Cappuccino::class, $instance->makeCoffee('cappuccino'));
}
```

Note that we end up having to create a mock of `MusicPlayer`, despite the fact that we're never actually doing anything with it. Furthermore, if the `CoffeeBar::__construct()` arguments were ever to change, we'd have to update every instance of these tests.

Instead, we can inject our test double into the [DI container](container.md) and get the dependencies via the `getClassDependencies()` method:

```php
use Mockery;

public function the_barista_should_make_coffee_if_the_inventory_is_available()
{
    $barista = Mockery::mock(Barista::class);
    $barista->shouldReceive('prepareDrink')
        ->once()
        ->with('cappuccino')
        ->andReturn(new Cappuccino());

    // Inject our Barista mock into the DI container.
    $this->container->extend(Barista::class, $barista);

    // $this->getClassDependencies() will automatically retrieve our mocked $barista as well as a MusicPlayer.
    $instance = new CoffeeBar(...$this->getClassDependencies(CoffeeBar::class));
    $this->assertInstanceOf(Cappuccino::class, $instance->makeCoffee('cappuccino'));
}
```

Now, we're injecting our `$barista` mock into the DI container, so requests for `$container->get(Barista::class)` will return our mock. Then, we're using `$this->getClassDependencies(CoffeeBar::class)` to use reflection to find the dependencies of `CoffeeBar`, resolve them through the container, and inject them. We end up with an instance of `MusicPlayer` being injected without ever having to worry about defining it!

## Inspecting logs

Throughout the framework, we utilize a PSR-3-compliant logger class. However, it's often undesirable for logs to actually be printed during test execution. Furthermore, we may want to inspect the messages that have been logged.

To this end, we may replace the default logger in our test suite with an instance of `StellarWP\PluginFramework\Support\Testing\TestLogger`:

```php
use Psr\Log\LoggerInterface;
use StellarWP\PluginFramework\Support\Testing\TestLogger;

/**
 * @test
 */
public function it_should_log_an_error_when_things_go_awry()
{
    $logger   = new TestLogger();
    $instance = new SomeClass($logger);
    $instance->doSomethingPoorly();

    $this->assertCount(1, $logger->getMessages(), 'One message should have been logged.');
}
```

> **Note:** If you're using [the `InteractsWithContainer` trait](#interacting-with-the-di-container), a `TestLogger` will automatically be injected into the container for you.

## Stubbing HTTP requests

We rarely (if ever) actually want to make HTTP requests from within our test suite, as this can cause our test suites to be brittle, slow, and subject to the availability of external services. Instead, we can stub these responses via [the `pre_http_request` filter](https://developer.wordpress.org/reference/hooks/pre_http_request/):

```php
add_filter('pre_http_request', function ($preempt, $args, $url) {
    // If we return anything that's *not* false, the request will not actually be sent.
}, 10, 3);
```

The three arguments are:

<dl>
    <dt><code>mixed $preempt</code></dt>
    <dd>Whether or not to preempt the HTTP request. If anything other than <code>false</code> is returned from the callback, WordPress will not attempt to make the HTTP request.</dd>
    <dt><code>array $args</code></dt>
    <dd>The arguments that have been passed via <a href="https://developer.wordpress.org/reference/classes/WP_Http/request/"><code>WP_Http::request()</code></a>.</dd>
    <dt><code>string $url</code></dt>
    <dd>The URI being requested.</dd>
</dl>

There are a few common patterns you may see:

```php
use StellarWP\PluginFramework\Support\Testing\ResponseFactory;

# Stubbing a response.
add_filter('pre_http_request', function ($preempt) {
    return ResponseFactory::make(200, wp_json_encode([
        'success' => true,
        'data'    => [
            'some' => [
                'stubbed',
                'data',
            ],
        ],
    ]));
});

# Validating arguments.
add_filter('pre_http_request', function ($preempt, $args, $url) {
    $this->assertStringEndsWith('/some-path', $url, 'Expected the URL to end with "/some-path".');

    return ResponseFactory::make(200);
}, 10, 3);

# Asserting that an HTTP request was attempted.
$called = false;

add_filter('pre_http_request', function ($preempt) use (&$called) {
    $called = true;

    return ResponseFactory::make(200);
});

$this->assertTrue($called, 'Expected an HTTP request to have been attempted.');
```

In each of those examples, you may have noticed the `ResponseFactory::make()` method being used; the WordPress HTTP API expects responses to be formatted as multi-dimensional arrays, which isn't very type-safe:

```php
[
    'headers'  => [
        'Content-Type' => [
            'application/json',
        ],
    ],
    'body'     => '{"success":true,"data":{"some-key":"some-value"}}',
    'response' => [
        'code' => 200,
        'message' => 'OK',
    ],
    'cookies'  => [],
    'filename' => null,
]
```

Instead, the `StellarWP\PluginFramework\Support\Testing\ResponseFactory` class has a few methods to help you construct these responses with minimal intervention.

### Preventing outbound HTTP requests

When building services that depend on external resources, it's easy to forget to add [a `pre_http_request` filter](https://developer.wordpress.org/reference/hooks/pre_http_request/) in every test to block requests from actually being made through the WordPress HTTP API.

The `StellarWP\PluginFramework\Support\Testing\BlocksHttpRequests` trait automatically adds a `pre_http_request` filter at the absolute end of the filter chain that will throw a `RequestException` if a request has not yet been pre-empted.

> **Note:** This trait is already included for you if you extend the base `TestCase` class.

## Loading WordPress core files

Occasionally, WordPress will include functionality that will not be loaded by default and instead requires you to explicitly load the file via `require_once`; to get around this, you may add the `StellarWP\PluginFramework\Support\Testing\Concerns\RequiresCoreFiles` trait and a protected, static `$requiredFiles` array:

```php
use StellarWP\PluginFramework\Support\Testing\Concerns\RequiresCoreFiles;
use StellarWP\PluginFramework\Support\Testing\TestCase;

class SomeFeatureTest extends TestCase
{
    use RequiresCoreFiles;

    /**
     * Files, relative to ABSPATH, which should be included before executing this test class.
     *
     * @var Array<string>
     */
    protected static $requiredFiles = [
        WP_INC . '/some-file.php',
    ];
}
```

## Creating temporary files

Occasionally, it may be necessary to create a temporary file for the sake of testing filesystem operations.

The `StellarWP\PluginFramework\Support\Testing\Concerns\CreatesTempFiles` trait exposes two methods that allow you to create temporary files and directories that will be automatically removed at the conclusion of the test method:

```php
# Create a temporary directory within the system tmp dir.
echo $this->createTempDirectory('some-dir');
# => /tmp/some-dir/

# Create a temporary file and write contents to it.
$file = $this->createTempFile(WP_CONTENT_DIR . '/some-file.php');
fwrite($file, 'Here is some content');
fclose($file);
echo file_get_contents(WP_CONTENT_DIR . '/some-file.php');
# => Here is some content
```

## Custom setup methods for traits

Due to the way that the WordPress core test suite loads, the WordPress environment is unavailable until after the `set_up()` method runs. However, it's generally not advisable to override the `set_up()` fixture within testing traits, as a class may inherit conflicting definitions from multiple traits.

To get around this, the `set_up()` method in our base `TestCase` will inspect the traits being used and look for the presence of methods named `setUp{TraitName}`, which will be called immediately after WordPress is available:

```php
namespace Tests\Support\Concerns;

trait MySuperCoolTrait
{
    /**
     * @before
     */
    public function prepareTheAwesome()
    {
        // This will run for each test method, before WordPress is loaded.
    }

    /**
     * Effectively the equivalent of `set_up()`, but deferred until after WordPress has loaded
     * and without conflicts with other traits.
     */
    public function setUpMySuperCoolTrait()
    {
        // This will run for each test method, right *after* WordPress is loaded.
    }
}
```
