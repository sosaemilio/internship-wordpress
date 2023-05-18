# Handling settings, configuration, and environment variables

Plugins built atop the StellarWP Plugin Framework will almost certainly have their own sub-classes of the `StellarWP\PluginFramework\Settings` object, as this is typically where the plugin retrieves any details it needs about the current site.

Interaction with the `Settings` object itself is pretty straight-forward: it maintains an array of details about the current site, exposing these via the `get()` method and magic properties:

```php
use StellarWP\PluginFramework\Settings;

$settings = new Settings();

var_dump($settings->get('php_version'), $settings->mapps_api_url);
#=> string(3) "8.1"
#=> string(58) "https://fbbc473f-a386-4cd1-8b0c-c55f9006d7a8.mock.pstmn.io"
```

## Customizing plugin settings

The base `Settings` class is designed to be extended, offering a `loadSettings()` method whose return value is merged in with default settings:

```php
namespace StellarWP\SomePlugin;

use StellarWP\PluginFramework\Settings as BaseSettings;

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
            'nocworx_customer_id' => $this->getSiteWorxSetting('customer_id'),
            'plugin_version'      => '1.2.3',
        ];
    }
}
```

By adding these values in `loadSettings()`, they become available as properties on the object:

```php
var_dump($settings->nocworx_customer_id, $settings->get('plugin_version')));
#=> int(12345)
#=> string(5) "1.2.3"
```

Notice that `loadSettings()` receives an array of the existing settings, making it possible to define settings based on other settings and letting us centralize these common conditionals in a single place:

```php
protected function loadSettings(array $settings)
{
    return [
        'has_live_domain' => $settings['domain'] !== $this->getSiteWorxSetting('temp_domain'),
    ];
}
```

### Lazy-loading settings

The `Settings` object also has the ability to "lazy load" settings by defining their values as callables. This is most beneficial when a setting is only needed in a few places and may require additional work to process (such as a call to an external system):

```php
protected function loadSettings(array $settings)
{
    return [
        // Will call $this->getSubscriptionStatus() on every page load, which is terrible.
        'subscription_status_direct' => $this->getSubscriptionStatus(),

        // Will only call $this->getSubscriptionStatus() when $settings->subscription_status_lazy is referenced.
        'subscription_status_lazy' => [$this, 'getSubscriptionStatus'],
    ];
}
```

Lazy loading is especially powerful when combined with the functions from [the WP Cache Remember package](https://github.com/stevegrunwell/wp-cache-remember), as we can easily cache the results on the few occasions that we do need to retrieve them:

```php
protected function loadSettings(array $settings)
{
    return [
        // Only call $this->subscriptionStatus() when referenced, using a cached version when available.
        'subscription_status_cached' => function () {
            return wp_cache_remember(
                'subscription_status',
                [$this, 'getSubscriptionStatus'],
                'stellarwp', HOUR_IN_SECONDS
            );
        },
    ];
}
```

## Reading details from SiteWorx

One of the major benefits of using the `Settings` object is built-in support for reading details about a site from SiteWorx (the underlying system infrastructure).

Upon calling `getSiteWorxSetting()`, the `Settings` object will invoke the SiteWorx shell script and cache the results. From temp domain to NocWorx customer ID, SiteWorx is the canonical source of information about a site on the Nexcess cloud.

To see all available settings on a cloudhost, SSH into the server and run the following:

```sh
$ siteworx -u -o pretty -n -c Overview -a listAccountConfig
```

Available configurations will vary across platforms and servers, but should include the necessary details about the site's relationship to the Nexcess cloud platform.

### Fallback to environment variables

In the event that SiteWorx is unavailable (e.g. during local development), the `Settings` object is designed to fall back to local environment variables prefixed with "siteworx_". For example, if you need to set the value of SiteWorx's "plan_name" in development, you may add the following to [your `stellarwp-bootstrap.php` file](scaffold-plugin.md#next-steps):

```php
putenv('siteworx_plan_name=PremiumPlan');
```
