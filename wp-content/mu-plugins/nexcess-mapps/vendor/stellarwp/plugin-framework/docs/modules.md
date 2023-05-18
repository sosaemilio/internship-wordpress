# Working with Modules

Modules are the basic building block of plugins on the StellarWP Plugin Framework, designed to encapsulate features and functionality.

Modules are registered within each plugin's `Plugin::$modules` array, then [resolved through the Dependency Injection (DI) container](container.md). This allows us to dynamically load modules (as well as their dependencies) upon the plugin loading. We can also choose to conditionally load modules if they're not always needed ([see "Conditionally loading modules" below](#conditionally-loading-plugins)).

## Creating a new module

Module files live within the `Modules` namespace (e.g. `StellarWP\SomePlugin\Modules`), and extend the abstract `StellarWP\PluginFramework\Module` class.

All modules must contain a `setup()` method, which is the main entry point to the module. This method can do anything necessary to achieve the required functionality, but will usually be a series of `add_action()` and/or `add_filter()` calls.

A very simple integration may look something like this:

```php
namespace StellarWP\SomePlugin\Modules;

use StellarWP\PluginFramework\Modules\Module;

class CoffeeFeature extends Module
{
    /**
     * Perform any necessary setup for the module.
     *
     * This method is automatically called as part of Plugin::load_modules(), and is the
     * entry-point for all modules.
     *
     * @return void
     */
    public function setup()
    {
        add_action('admin_notices', [$this, 'renderAdminNotice']);
    }

    /**
     * Make sure admin users know how great coffee is.
     *
     * @return void
     */
    public function renderAdminNotice()
    {
        echo wpkses_post(sprintf(
            '<div class="notice notice-success is-dismissible"><p>%s</p></div>',
            __('Coffee: it\'s pretty great', 'stellarwp')
        ));
    }
}
```

Once you've written the module, [it should be registered within the DI container](container.md), then added to the `Plugin::$modules` array.

## Conditionally loading modules

In many cases, it may not be appropriate to load a module on every site. For example, a module containing functionality for some optional, paid add-on probably shouldn't be loaded on sites that have not purchased the add-on.

We can address this by implementing the `StellarWP\PluginFramework\Contracts\LoadsConditionally` interface:

```php
namespace StellarWP\PluginFramework\Contracts;

/**
 * Indicates that the given functionality should only load if a truth test passes.
 */
interface LoadsConditionally
{
    /**
     * Determine whether or not this extension should load.
     *
     * @return bool True if the extension should load, false otherwise.
     */
    public function shouldLoad();
}
```

Modules that implement this interface will have their `shouldLoad()` methods called during the loading process; if the method returns `false`, the module's `setup()` method will not be called and the module's resources will be freed up in memory.

Building on our `CoffeeFeature` example from earlier, we might [query our plugin's `Settings` object](settings.md) to see if the customer has an active "CoffeePass" subscription:

```php
namespace StellarWP\SomePlugin\Modules;

use StellarWP\PluginFramework\Contracts\LoadsConditionally;
use StellarWP\PluginFramework\Contracts\ProvidesSettings;
use StellarWP\PluginFramework\Modules\Module;

class CoffeeFeature extends Module implements LoadsConditionally
{
    /**
     * The plugin Settings object.
     *
     * @var ProvidesSettings $settings
     */
    protected $settings;

    /**
     * Construct a new instance of the module.
     *
     * @param ProvidesSettings $settings The plugin Settings object.
     */
    public function __construct(ProvidesSettings $settings)
    {
        $this->settings = $settings;
    }

    /**
     * Determine whether or not this extension should load.
     *
     * @return bool True if the extension should load, false otherwise.
     */
    public function shouldLoad()
    {
        return $this->settings->has_active_coffeepass_subscription;
    }

    // ...
}
```

Now, the class accepts an implementation of the `ProvidesSettings` interface (`$this->settings`) and will only load the module if `$this->settings->has_active_coffeepass_subscription` is "true".
