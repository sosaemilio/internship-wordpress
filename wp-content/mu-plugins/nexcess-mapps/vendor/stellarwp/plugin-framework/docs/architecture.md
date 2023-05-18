# Plugin Architecture

The StellarWP Plugin Framework provides the building blocks for sophisticated WordPress plugins, but it's the plugins themselves that tie everything together.

Typically, a plugin built on the framework will have the following structure:

```
plugin-name/
| - Console/
| | - Commands/
| | | - Setup.php
| - Modules/
| | - SomeModule.php
| - Container.php
| - Plugin.php
| - Settings.php
tests/
| - Unit/
| | - ExampleTest.php
| - bootstrap.php
| - TestCase.php
composer.json
plugin-name.php
README.md
```

In this structure, `plugin-name.php` represents the main entry-point into the plugin, responsible for kicking off everything else. Details regarding this and other key files can be found further down in this document.

In an MU plugin configuration, `plugin-name.php` would live at `wp-content/mu-plugins/plugin-name.php`; as a standard plugin, this entire directory structure would exist under `wp-content/plugins/plugin-name/` (e.g. `wp-content/plugins/plugin-name/plugin-name.php`, `wp-content/plugins/plugin-name/plugin-name/Settings.php`, etc.).

You may see an example of this structure in [the stellarwp/plugin-starter repository](https://github.com/stellarwp/plugin-starter), which also serves as the template [when scaffolding a new plugin](scaffold-plugin.md).

## Key concepts

Before working on a StellarWP plugin, it's important to understand a few key architecture concepts:

### Namespacing

With few exceptions, **everything** in StellarWP plugins should utilize PHP namespaces (generally `StellarWP\{TheIndividualPluginName}`). Namespacing our code prevents conflicts with external code and removes any reason for anti-patterns like class-based "pseudo-namespaces".

### Autoloading

These plugins utilize [PSR-4 autoloading](https://www.php-fig.org/psr/psr-4/), which leverages PHP's autoloading capabilities to prevent the unnecessary loading of files.

Essentially, instead of littering the codebase with `require_once` statements and hard-coded paths, we define the autoloader pattern. Then, upon referencing a PHP class definition that doesn't yet exist in-memory, PHP knows where in the filesystem to look and will automatically load the necessary file.

Rather than writing our own autoloader, the plugins are designed around using [the optimized autoloader automatically generated via Composer](https://getcomposer.org/doc/articles/autoloader-optimization.md), which get bundled with the plugin build.

### Dependency Injection

The StellarWP Plugin Framework and its consumer plugins utilize a PSR-11 Dependency Injection (DI) container in order to resolve services. [Please see our container documentation for further details](container.md).

## Plugin files

There are a number of important files in each StellarWP plugin that deserve special attention:

### plugin-name.php: The plugin bootstrap file

The `plugin-name.php` file is the main entry point into the plugin, and has a short-list of duties:

1. Define plugin-wide constants (if applicable)
2. Require the [Composer-generated autoloader](#autoloading)
3. Initialize [the Dependency Injection container](#dependency-injection)
4. Execute the `Plugin::init()` method

Though this list is brief, this is a very important file: a missing or error-filled bootstrap file could cause the entire plugin to fail and potentially take down a customer site!

### Container.php: The DI container configuration

The `Container.php` contains the definition for [the plugin's Dependency Injection (DI) container](container.md).

### Plugin.php: The Plugin class definition

This is the plugin-specific implementation of [the framework's `StellarWP\PluginFramework\Plugin` class](../src/Plugin.php). While it may be used to override any functionality in its parent, it will generally contain definitions for two properties: `$commands` and `$modules`.

A default `Plugin.php` will look something like this:

```php
namespace StellarWP\PluginStarter;

use StellarWP\PluginFramework\Plugin as BasePlugin;

/**
 * The main plugin instance.
 */
class Plugin extends BasePlugin
{
    /**
     * An array containing all registered WP-CLI commands.
     *
     * @var Array<string,string>
     */
    protected $commands = [
        'stellarwp setup' => Console\Commands\Setup::class,
    ];

    /**
     * An array containing all registered modules.
     *
     * @var Array<string>
     */
    protected $modules = [
        // Register modules here (e.g.: Modules\SomeModule::class).
    ];
}
```

In this example, we're exposing [the `Console\Commands\Setup` WP-CLI command](#setup-command) class via `wp stellarwp setup` ([see "Writing CLI Commands" for further details](commands.md)). Additionally, we have a place to register [any number of modules](modules.md) to be loaded by our plugin.

### Settings.php: The global plugin configuration

The StellarWP Plugin Framework encourages the use of [the `Settings` object](../src/Settings.php), which acts as a central repository for details about the current site. This could include any number of things, including:

* Current and temporary domains
* MAPPS API token
* Nocworx cloud account ID
* PHP version

The `Settings` object is able to retrieve (and cache) details from the SiteWorx environment, while also providing an interface through which Settings can be injected during testing (via its constructor method). The `Settings` class is also easily-extended:

```php
namespace StellarWP\MyPlugin;

use StellarWP\PluginFramework\Settings as BaseSettings;

/**
 * A global settings object that can retrieve details about a site from its environment.
 *
 * @property-read int    $cloud_account_id        The NocWorx cloud account ID.
 * @property-read bool   $is_php_gte8             Is the current site running on PHP 8.0 or newer?
 * @property-read string $plugin_version          The current plugin version.
 * @property-read string $some_expensive_property Some property that's expensive to calculate.
 */
class Settings extends BaseSettings
{
    /**
     * Load all custom settings.
     *
     * This method gets called as part of $this->load(), and can override any of the default settings
     * (but can still be overridden via $this->overrides).
     *
     * @param Array<string,mixed> $settings Current settings. These are provided for reference and may
     *                                      be returned, but it's not required to do so.
     *
     * @return Array<string,mixed> Custom settings.
     */
    protected function loadSettings(array $settings)
    {
        return [
            'plugin_version'          => '1.0.0-dev',
            'cloud_account_id'        => (int) $this->getSiteWorxSetting('cloud_account'),
            'some_expensive_property' => function () {
                // This closure will be executed and its return value cached the first time it's needed.
            },
            'is_php_gte8'             => version_compare('8.0', $settings['php_version'], '<='),
        ];
    }
}
```

As you'll notice in the example above, the settings can be defined in a number of different ways:

1. Hard-coded values
2. Values read from SiteWorx
3. Lazily-evaluated closures (only run when the value is requested, then cached)
4. Inline conditionals

The `Settings` object then exposes settings both as object properties and via the `getSetting()` method:

```php
var_dump($settings->cloud_account_id);
#=> (int) 123456

var_dump($settings->getSetting('cloud_account_id'));
#=> (int) 123456
```

If a setting is undefined, its value will be `null` _unless_ a default passed as the second argument of `getSetting()`:

```php
var_dump($settings->property_does_not_exist);
#=> NULL

var_dump($settings->getSetting('property_does_not_exist', 'default'));
#=> string(7) "default"
```

Furthermore, the `Settings` object makes settings immutable, preventing customers from trying to unlock features by overriding details about their environment:

```php
$settings->has_super_awesome_add_on_feature = true;
#=> ConfigurationException: Setting "has_super_awesome_add_on_feature" may not be modified
```

There are only three ways to modify settings without touching the class definition:

1. Passing settings via the class constructor
2. Overriding the `Settings` class definition in the DI container
3. By setting `stellarwp_{setting_name}` environment variables

The first two options often get used in automated tests, but would require significant work from a customer to interfere with. Similarly, the third option is useful for development environments, but environment variables will not be considered in any environment where SiteWorx is available.


### Setup.php: The post-provisioning command

When running on the Nexcess Cloud, the final step of the provisioning of a new site is a call to `wp stellarwp setup --provision` by NocWorx. This command is responsible for kicking off any application-specific configuration that might be necessary. Possible steps might include:

* Installing, activating, and/or configuring plugins
* Enabling caches
* Importing site content

It's generally recommended to make the setup command responsible for running a series of discrete sub-commands, as this makes it easier to re-run steps that might fail as well as reduce the chances of one command's failure preventing everything else from running.

## Further reading

* [The Dependency Injection (DI) Container](container.md)
* [Writing CLI Commands](commands.md)
* [Working with Modules](modules.md)
* [Automated Testing Process](testing.md)
* [Error logging](logging.md)
