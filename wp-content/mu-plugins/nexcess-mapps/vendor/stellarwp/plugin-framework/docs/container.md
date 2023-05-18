# Dependency Injection Container

The StellarWP Plugin Framework uses [a PSR-11-compatible Dependency Injection (DI) container](https://www.php-fig.org/psr/psr-11/) to aid in resolving dependencies as needed throughout the application.

> **Note:** [The official PHP-FIG interfaces for PSR-11 use scalar typehints and require PHP >= 7.2](https://github.com/php-fig/container), so we're not able to _actually_ implement the interfaces [based on our PHP compatibility constraints](php-compatibility.md).

## What is Dependency Injection?

In its simplest terms, Dependency Injection is providing dependencies to an object rather than making the object try to create/retrieve them.

For example, a plugin [module](modules.md) might receive an implementation of [the `StellarWP\PluginFramework\Contracts\ProvidesSettings` interface](settings.md) in its constructor:

```php
namespace StellarWP\PluginFramework\Modules;

use StellarWP\PluginFramework\Contracts\ProvidesSettings;

class SomeModule extends Module
{
  /**
   * @var ProvidesSettings
   */
  protected $settings;

  /**
   * @param ProvidesSettings $settings
   */
  public function __construct(ProvidesSettings $settings)
  {
    $this->settings = $settings;
  }
}
```

By injecting the `ProvidesSettings` instance, we're able to re-use a single instance across the plugin and more-easily inject [test doubles](https://phpunit.readthedocs.io/en/9.5/test-doubles.html) in our tests.

Now, compare this to a version of the same class that _doesn't_ use dependency injection:

```php
namespace StellarWP\PluginFramework\Modules;

use StellarWP\PluginFramework\Contracts\ProvidesSettings;
use StellarWP\PluginFramework\Settings;

class SomeModule extends Module
{
  /**
   * @var ProvidesSettings
   */
  protected $settings;

  public function __construct() {
    $this->settings = new Settings();
  }
}
```

Under this model, each instance of the module (or its subclasses) will be responsible for instantiating their own instance of the `Settings` object, we're tied to the concrete `StellarWP\PluginFramework\Settings` class, and we lack the ability to inject test doubles.

Furthermore, if the `Settings` class changes its constructor method signature, we'd have to update calls to `new Settings()` throughout the application.

This is one of the major benefits of a DI container: we can define how an object gets constructed in one place, and then recursively resolve dependencies.

### Dependency Injection vs Service Location

It's worth mentioning that the container is designed to be used for Dependency Injection, **not** as a Service Locater.

What's a Service Locater? Imagine instead of injecting the `Settings` instance into our modules, we instead injected the entire `Container` object. Instead of giving the class the tools it needs to do its job, we're instead throwing the entire application at it and saying "here, you figure it out."

[The PSR-11 meta documentation has a good breakdown of these patterns](https://www.php-fig.org/psr/psr-11/meta/#4-recommended-usage-container-psr-and-the-service-locator).

The only place in our plugins that we should be using the DI container as a Service Locater is within the main `Plugin` class, as it's responsible for loading all of the various modules and commands.

## Using the container

In its most simple application, imagine we need to create an instance of the `StellarWP\SomePlugin\Modules\Parfait` class. Normally, we might do something like this:

```php
use StellarWP\PluginFramework\Modules\Fruit;
use StellarWP\PluginFramework\Modules\Parfait;
use StellarWP\PluginFramework\Modules\Yogurt;

$yogurt  = new Yogurt();
$fruit   = new Fruit();
$parfait = new Parfait($yogurt, $fruit);
```

While two dependencies might not seem so bad, what happens if `Fruit` or `Yogurt` have dependencies of their own? What happens when we realize that the parfait is lacking granola? Our application code would be littered with a bunch of new objects being instantiated everywhere.

Within the DI container, we can define how an `Parfait` module class gets instantiated:

```php
namespace StellarWP\PluginFramework;

return [
  // ...
  Modules\Parfait::class => function ($app) {
    return new Modules\Parfait(
        $app->make(Yogurt::class),
        $app->make(Fruit::class)
    );
  },
];
```

When the container is asked for an instance of `StellarWP\PluginFramework\Modules\Parfait`, it will call the closure we've defined, passing the Container instance itself (as `$app`) into the callable. We then use the container to resolve its dependencies.

In a step-by-step guide, asking the container for a `Parfait` instance might look something like this:

1. Do we have a cached instance?
    * If yes, return it.
2. Do we know how to resolve `Parfait`?
    * If not, throw a `StellarWP\PluginFramework\ContainerNotFoundException`
3. Execute the definition for `Parfait`
    1. Ask the container for a `Yogurt` instance (resolving its dependencies recursively, if needed)
    2. Ask the container for an `Fruit` instance (resolving its dependencies recursively, if needed)
4. Cache, then return our newly-constructed `Parfait` instance.

Once the class and its dependencies are defined in the container, constructing an instance becomes as simple as this:

```php
use StellarWP\PluginFramework\Container;
use StellarWP\PluginFramework\Modules\Parfait;

$container = new Container();
$instance  = $container->get(Parfait::class);
```

## Caching instances of dependencies

As mentioned earlier, the container will try to cache resolved instances of dependencies:

```php
use StellarWP\PluginFramework\Container;
use StellarWP\PluginFramework\Modules\Parfait;

$container = new Container();
$instance1 = $container->get(Parfait::class);
$instance2 = $container->get(Parfait::class);

var_dump($instance1 === $instance2);
#=> true
```

Normally, this is fine for our needs. If you _do_ need a fresh instance, you may create one using the `make()` method:

```php
use StellarWP\PluginFramework\Container;
use StellarWP\PluginFramework\Modules\Parfait;

$container = new Container();
$instance1 = $container->make(Parfait::class);
$instance2 = $container->make(Parfait::class);

var_dump($instance1 === $instance2);
#=> false
```

> **Note:** When calling `Container::get()` on an abstract, it will enable caching of the requested object as well as any dependencies. For this reason, it's generally best to call `$app->make()` within resolution callbacks, as this will cache items when resolving recursively via `get()` and exclude them from the cache when their parent(s) are resolved via `make()`.

If the cached version of a dependency should be removed for any reason, the `forget()` method can be used to remove it. This method can also be chained:

```php
use StellarWP\PluginFramework\Container;
use StellarWP\PluginFramework\Modules\Parfait;

$container = new Container();
$instance1 = $container->get(Parfait::class);
$instance2 = $container->forget(Parfait::class)->get(Parfait::class);

var_dump($instance1 === $instance2);
#=> false
```

## Testing

The use of a DI container also makes testing easier, especially when we leverage the `extend()` method.

This method lets us override the DI container's definition for a given key, letting us inject test doubles and/or known values.

For example, imagine that we wanted to create a stub for some food-ordering service class. We don't want our test suite to actually make orders every time we run the tests, so we might create our test double and inject it into the DI container via the `extend()` method:

```php
use Mockery;
use StellarWP\SomePlugin\Modules\LateNightEats;
use StellarWP\SomePlugin\Services\DoorSprint;

/**
 * @test
 */
public function it_should_place_an_order_via_the_DoorSprint_service()
{
    $toppings = ['lettuce', 'ketchup', 'pickle', 'mustard', 'tomato'];

    $service = Mockery::mock(DoorSprint::class);
    $service->shouldReceive('placeOrder')
        ->once()
        ->with([
            [
                'item'    => 'burger',
                'options' => [
                    'cheese'               => 'cheddar',
                    'temperature'          => 'medium',
                    'side'                 => 'fries',
                    'special_instructions' => null,
                    'toppings'             => $toppings,
                ]
            ]
        ]);

    $this->container->extend(DoorSprint::class, $service);

    $order = $this->container->get(LateNightEats::class);
    $order->addBurger('medium', $toppings)
        ->withCheese('cheddar')
        ->withSide('fries');

    $receipt = $order->submit();
}
```

In this example, we're extending the definition of the `DoorSprint` service within the container so that our mock is returned when requested by the `LateNightEats` module. Our mock is then asserting that it receives a call to `placeOrder` with the expected parameters.
