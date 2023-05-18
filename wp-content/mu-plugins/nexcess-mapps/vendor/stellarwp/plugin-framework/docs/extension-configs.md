# The Extension Config API

Occasionally, it's necessary to customize the behavior of certain plugins or themes (collectively: extensions). Whether it's applying reasonable defaults or disabling functionality that's handled elsewhere, being able to conditionally modify how a third-party extension behaves is valuable.

To this end, the StellarWP Plugin Framework includes the Extension Config API, which consists of three parts:

1. Individual extension configurations
2. The `ExtensionConfigManager` service, which handles the registration of extension configurations
3. The `ExtensionConfig` module, which is responsible for tying extension configurations into WordPress

## How extension configurations work

An extension configuration is a class that implements the `StellarWP\PluginFramework\Extensions\Contracts\ConfiguresExtension` interface, which defines four methods:

```php
/**
 * Actions to perform upon activation of the extension.
 *
 * @return void
 */
public function activate();

/**
 * Actions to perform upon deactivation of the extension.
 *
 * @return void
 */
public function deactivate();

/**
 * Actions to perform every time the extension is loaded.
 *
 * @return void
 */
public function load();

/**
 * Actions to perform when the extension is updated.
 *
 * @return void
 */
public function update();
```

[Once an extension configuration has been defined](#defining-plugin-configurations), it should be registered within the `ExtensionConfigManager` class — for convenience, the manager will automatically copy the values of `Plugin::$plugins`:

```php
namespace StellarWP\SomePlugin;

use StellarWP\PluginFramework as Framework;

class Plugin extends Framework\Plugin
{
    /**
     * An array containing all registered modules.
     *
     * @var Array<int,class-string<Framework\Modules\Module>>
     */
    protected $modules = [
        Framework\Modules\ExtensionConfig::class, // Be sure to load the module, too!
    ];

    /**
     * An array containing all registered plugin configurations.
     *
     * @var Array<string,class-string<Framework\Extensions\Plugins\PluginConfig>>
     */
    protected $plugins = [
        'some-plugin/some-plugin.php' => Extensions\Plugins\SomePlugin::class,
    ];
}
```

When the `ExtensionConfig` module loads, it adds the appropriate hooks into WordPress for things like plugin (de)activation, updates, etc.; when those actions fire, callbacks within the module query the `ExtensionConfigManager` for related extensions and, if found, the corresponding methods will be called on the configuration.

### Example workflow

Assuming the configuration from above, this is what the activation of "some-plugin/some-plugin.php" would look like from start to finish:

1. The StellarWP MU plugin gets loaded
2. The StellarWP MU plugin loads its modules, calling the `setup()` method of each
3. `ExtensionConfig::setup()` adds the following hook:

    ```php
    add_action('activate_plugin', [$this, 'activatePlugin'], 10, 2);
    ```

4. WordPress finishes loading enough to run `activate_plugin('some-plugin/some-plugin.php')`
    * This function then fires the `activate_plugin` action
5. `ExtensionConfig::activatePlugin()` is invoked, with the first argument (`$plugin`) being "some-plugin/some-plugin.php"
6. `ExtensionConfig::activatePlugin()` calls `ExtensionConfigManager::hasPlugin('some-plugin/some-plugin.php')`
    * This method determines whether or not the given key exists in the mapping of plugins to configurations
    * If the result is `true`, `ExtensionConfig::activatePlugin()` will call `ExtensionConfigManager::resolvePlugin('some-plugin/some-plugin.php')`; otherwise, it will do nothing
7. The `ExtensionConfigManager` looks up the `ConfiguresExtension` implementation that is mapped to "some-plugin/some-plugin.php" and [resolves it through the DI container](container.md)
8. `ExtensionConfig::activatePlugin()` calls the `activate()` method on the resolved extension configuration

## Defining plugin configurations

All plugin extensions should extend the `StellarWP\PluginFramework\Extensions\Plugins\PluginConfig` class, which exposes the following methods:

<dl>
  <dt>activate([bool $network_wide = false])</dt>
  <dd>Runs when the plugin is activated, on <code>activate_plugin</code>.</dd>
  <dt>deactivate([bool $network_wide = false])</dt>
  <dd>Runs when the plugin is deactivated, on <code>deactivate_plugin</code>.</dd>
  <dt>load()</dt>
  <dd>Runs any time the plugin is loaded, on <code>plugin_loaded</code>.</dd>
  <dt>update()</dt>
  <dd>Runs after a successful plugin update, on <code>upgrader_process_complete</code>.</dd>
  <dd>Note that this will not be run if the plugin is upgraded manually outside of WordPress!</dd>
</dl>

All of these methods are optional, but available (the base class defines "no-op" versions to satisfy the `ConfiguresExtension` contract).

## Defining theme configurations

The Extension Configuration API has been built with theme support in mind, but this feature has not yet been implemented.

## Additional features

In addition to activation, deactivation, load-time, and update hooks, an extension can also be used to help inform the behavior of other components of the framework.

### Specify exclusions for full-page caching solutions

A full-page caching solution ([Cache Enabler](https://wordpress.org/plugins/cache-enabler/), [WP Rocket](https://wp-rocket.me/), etc.) generally works by installing an `advanced-cache.php` drop-in and using it to determine whether or not a page (based on request details like the path, query string parameters, cookies, etc.) should be cached; if so, `advanced-cache.php` will typically open an output buffer and, upon shutdown of the PHP process, save the contents to a static file on the filesystem.

On subsequent requests, the drop-in (or, better yet, the web server) can check to see if a static, cached response exists and serve that instead of fully-loading WordPress.

When used properly, a full-page caching solution can be very effective at reducing resource usage and delivering lightning-fast responses to [non-authenticated] customers hitting a primed cache. However, configuring a full-page cache requires careful fine-tuning for best performance, and it's crucial that sensitive pages (account/order details, carts, etc.) never get cached and served to a different user.

To this end, the Extension Config API exposes the `HasPageCacheExclusions` contract, which has three methods:

```php
/**
 * Retrieve a list of cookie prefixes that should be excluded from the page cache by default.
 *
 * @return Array<string> Cookie prefixes that should be excluded.
 */
public function getPageCacheCookieExclusions();

/**
 * Retrieve an array of paths that should be excluded by default from page caching solutions.
 *
 * All paths are assumed to be relative to the site root (e.g. "/") and will be treated as a
 * path prefix (e.g. "posts/" will match "/posts/*").
 *
 * @return Array<string> The path prefixes to exclude.
 */
public function getPageCachePathExclusions();

/**
 * Retrieve a list of query string parameters that should exclude a page from being cached.
 *
 * @return Array<string> Query string parameter names.
 */
public function getPageCacheQueryParamExclusions();
```

Each of these methods returns an array of strings, corresponding to the cookies, paths, and query params that should be excluded from full-page caching solutions.

For example, WooCommerce shouldn't cache pages if a customer is logged in, nor should it cache pages like the cart, checkout, or "My Account".

To add these considerations to the "should we cache this page?" evaulation, we can implement the `HasPageCacheExclusions` interface in our WooCommerce plugin config:

```php
namespace StellarWP\PluginFramework\Extensions\Plugins;

use StellarWP\PluginFramework\Extensions\Concerns\ProvidesPageCacheExclusions;
use StellarWP\PluginFramework\Extensions\Contracts\HasPageCacheExclusions;

/**
 * Plugin configuration for WooCommerce by Automattic.
 *
 * @link https://woocommerce.com/
 */
class WooCommerce extends PluginConfig implements HasPageCacheExclusions
{
    use ProvidesPageCacheExclusions;

    /**
     * Retrieve a list of cookie prefixes that should be excluded from the page cache by default.
     *
     * @return Array<string> Cookie prefixes that should be excluded.
     */
    public function getPageCacheCookieExclusions()
    {
        return [
            'woocommerce_',
            'wp_woocommerce_session',
        ];
    }

    /**
     * Retrieve an array of paths that should be excluded by default from page caching solutions.
     *
     * All paths are assumed to be relative to the site root (e.g. "/") and will be treated as a
     * path prefix (e.g. "posts/" will match "/posts/*").
     *
     * @return Array<string> The path prefixes to exclude.
     */
    public function getPageCachePathExclusions()
    {
        return [
            'addons',
            'administrator',
            'cart',
            'checkout',
            'login',
            'my-account',
            'resetpass',
            'store',
            'thank-you',
        ];
    }
}
```

> **Note:** The `ProvidesPageCacheExclusions` trait defines a default implementation of the `HasPageCacheExclusions` interface, letting us only worry about having to implement the methods that are meaningful for the configuration.

### Integration with page cache plugins

On the page cache side, these extension configurations should be relying on the `ExtensionConfigManager` service in order to get a list of registered extension configs, filtered by those that implement the `HasPageCacheExclusions` interface:

```php
use StellarWP\PluginFramework\Extensions\Contracts\HasPageCacheExclusions;

/**
 * Retrieve a list of paths that should be excluded from the page cache by default.
 *
 * These paths should be treated as prefixes: excluding "some-path" essentially tells Cache Enabler
 * "ignore any path matching '/some-path*'".
 *
 * @return Array<string>
 */
protected function getDefaultExcludedPaths()
{
    $paths = [
        // WordPress core.
        'wp-admin',
        'wp-cron.php',
        'wp-includes',
        'wp-json',
        'xmlrpc.php',
    ];

    // Append paths from registered extension configs.
    foreach ($this->manager->getExtensionsImplementing(HasPageCacheExclusions::class) as $plugin) {
        /** @var HasPageCacheExclusions $plugin */
        $paths = array_merge($paths, $plugin->getPageCachePathExclusions());
    }

    return array_unique($paths);
}
```

This will loop through all registered extensions that implement `HasPageCacheExclusions`, append them to the `$paths` array, then finally return a duplicate-free list of paths.

### Caveats

When working with the `HasPageCacheExclusions` interface, there are a few things you should be aware of:

First, the page cache extension configs will only look for other configs that have been [registered within the implementing plugin](#how-extension-configurations-work). If, for example, you want to have the WooCommerce exclusions considered, the `WooCommerce` extension needs to be registered within the `ExtensionConfigManager`.

Second, the exclusions will be added regardless of whether or not the given extension is active within a site. Duplicate rules will be flattened, but it's worth considering what kinds of plugins might be run on sites using a partner plugin. For example, it's highly unlikely that a LearnDash Cloud Edition site would be running MemberPress.

Finally, be aware that these exclusions are only applied when the page cache plugin's extension re-calculates its settings. In the case of Cache Enabler (the only page caching solution with a plugin config at the time of this writing), that would be upon the initial activation **or** if the "cache_enabler" option is removed. This is done to prevent user-provided settings from being trampled by those coming from the framework.
