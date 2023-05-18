# Automated Testing Process

As the StellarWP Plugin Framework is used to support plugins across multiple StellarWP brands, it's crucial that we maintain the highest software quality as possible. To this end, the framework has an extensive set of automated testing tools—[run on every push via GitHub Actions](https://github.com/stellarwp/plugin-framework/actions)—including the following:

* Unit and integration tests via [PHPUnit](https://phpunit.de), using the [WordPress core test framework](https://make.wordpress.org/core/handbook/testing/automated-testing/phpunit/)
* Coding standards and PHP/WP compatibility checks via [PHP_CodeSniffer](https://github.com/squizlabs/PHP_CodeSniffer) and [PHP-CS-Fixer](https://cs.symfony.com/) (provided via the [stellarwp/coding-standards package](https://github.com/stellarwp/coding-standards))
* Static code analysis via [PHPStan](https://phpstan.org/)

## PHPUnit

Due to the tightly-coupled nature of WordPress, our "unit" tests are more along the lines of integration tests in the classical sense, but we use this test suite to answer the question "does this component behave how we expect it to?"

Each module, service, and command should have a corresponding test class in the `tests/Unit/` directory, with explicit `@covers` annotations, e.g.:

```php
# tests/Unit/Modules/SomeModuleTest.php

namespace Tests\Unit\Modules;

use StellarWP\PluginFramework\SomeModule;
use Tests\TestCase;

/**
 * @covers StellarWP\PluginFramework\SomeModule
 *
 * @group Modules
 */
class SomeModuleTest extends TestCase
{
    // ...
}
```

You may execute the PHPUnit test suites with the following:

```sh
$ composer test:unit
```

### Tips for writing effective tests

An effective test will have a clear purpose: "**given** these conditions, **when** I do `$action` **then** `$result` should happen".

For example, consider the following method:

```php
public function toast(Food $food, int $level = 2)
{
    if (! $food instanceof BreadProduct) {
        throw new InvalidArgumentException('Quit putting weird things in the toaster!');
    }

    if (0 > $level || 10 < $level) {
        throw new RangeException('Invalid level (this toaster does not got to 11)');
    }

    for ($i = 0; $i < $level; $i++) {
        $food = $this->applyHeat($food);
    }

    $this->eject();

    return $food;
}
```

There are a few different logical paths through `toast`, our "System Under Test" (SUT):

1. Given a piece of bread and a level of 2, when I run `toast($food, $level)` I should get toast (the "happy path")
2. Given a bowl of soup and a level of 2, when I run `toast($food, $level)` I should receive an `InvalidArgumentException`
3. Given a piece of bread and a level of 20, when I run `toast($food, $level)` I should receive a `RangeException`

Our tests for these paths might look like this:

```php
/**
 * @test
 */
public function it_should_produce_toast_when_given_a_bread_product_and_a_valid_level(): void
{
    $toaster = new Toaster();
    $food    = new Bread();
    $level   = 2;

    $result = $toaster->toast($food, $level);

    $this->assertInstanceOf(Toast::class, $result);
    $this->assertSame($level, $result->getBrowningLevel());
}

/**
 * @test
 */
public function it_should_throw_an_InvalidArgumentException_if_given_a_bad_food(): void
{
    $toaster = new Toaster();
    $food    = new Soup();
    $level   = 2;

    $this->expectException(InvalidArgumentException::class);
    $toaster->toast($food, $level);
}

/**
 * @test
 */
public function it_should_throw_a_RangeException_if_given_an_invalid_level(): void
{
    $toaster = new Toaster();
    $food    = new Bread();
    $level   = 20;

    $this->expectException(RangeException::class);
    $toaster->toast($food, $level);
}
```

You'll notice that each of these test methods follows a similar pattern of "Arrange - Act - Assert"

1. **Arrange:** Set up any dependencies for the test
    * Initialize objects, declare mocks, etc.
2. **Act:** Execute the System Under Test
3. **Assert:** Make assertions against the results

This outline is illustrated most-clearly in our first test method:

```php
/**
 * @test
 */
public function it_should_produce_toast_when_given_a_bread_product_and_a_valid_level(): void
{
    // ARRANGE
    $toaster = new Toaster();
    $food    = new Bread();
    $level   = 2;

    // ACT
    $result = $toaster->toast($food, $level);

    // ASSERT
    $this->assertInstanceOf(Toast::class, $result);
    $this->assertSame($level, $result->getBrowningLevel());
}
```

In the latter two tests, we're using PHPUnit's `expectException()` helper, which effectively behaves like this:

```php
try {
    // ARRANGE
    $toaster = new Toaster();
    $food    = new Soup();
    $level   = 2;

    // ACT
    $toaster->toast($food, $level);

// ASSERT
} catch (InvalidArgumentException $e) {
    // Make some other assertions here, then return so we don't get caught by $this->fail().
    return;
}

$this->fail('Did not catch the expected InvalidArgumentException');
```

When writing your tests (especially for modules), consider the following questions:

* Does the module's `setup()` command register the expected hooks?
* [If the module implements `LoadsConditionally`](modules.md#conditionally-loading-modules), does `shouldLoad()` pass under the right conditions?
  - Conversely, does the method return `false` if one or more condition is not met?
* Do individual methods work as expected? Given a set input, do we receive the expected output?
* Should this functionality be available to all user levels? If not, are we testing that the functionality loads for a user with the appropriate capabilities and does not load for one without?

### Integration tests

The integration test suite is more focused on how our plugin integrates with the larger WordPress ecosystem. If something's supposed to happen when multiple plugins are in-play (for instance, "how does the plugin behave when Jetpack is active?"), this is where we need to test that.

When it comes to drawing the line between unit tests and integration tests within WordPress, consider whether or not the StellarWP plugin is interacting with anything outside of itself (or WordPress core); if the System Under Test (SUT) depends on making a web request or functionality from a third-party plugin, this is most likely an integration test.

### Additional testing tools

The StellarWP Plugin Framework provides a number of tools to aid in testing plugins. [Please see the "Testing Tools" documentation for further details](testing-tools.md).

## Coding Standards

Our coding standards are inherited from [the stellarwp/coding-standards package](https://github.com/stellarwp/coding-standards), and include automated checks for PHP and WordPress compatibility.

You may run coding standards checks at any time by running:

```sh
$ composer test:standards
```

## Static Code Analysis

[PHPStan](https://phpstan.org/) is used as part of our pipeline to give us further insight into the code quality, such as unused parameters, undefined function calls, and more.

You may run static code analysis by running the following:

```sh
$ composer test:analysis
```

The configuration for this process is located in [the `phpstan.neon.dist` file](../phpstan.neon.dist).
