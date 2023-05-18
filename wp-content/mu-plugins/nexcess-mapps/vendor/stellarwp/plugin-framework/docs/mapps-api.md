# The Nexcess MAPPS API

The Nexcess Managed Applications (MAPPS) API is a service maintained by Nexcess' engineering team to aid in the installation and licensing of premium WordPress plugins and themes.

Each site on the Nexcess cloud platform is issued a JWT token (typically via SiteWorx's "mapp_token") that can be used to authenticate against the API. Meanwhile, the available plugins and themes will vary based on a customer's plan.

## The MappsApiClient service

To assist in interacting with the Nexcess MAPPS API, the StellarWP Plugin Framework includes the `StellarWP\Framework\Services\Nexcess\MappsApiClient` service class.

There are two major ways to interact with the service:

1. Use `getInstallationSteps()` and `getLicensingSteps()` to retrieve an array of shell commands to be executed
2. Use `install()` and/or `license()` to automatically execute the corresponding steps

Generally, the first method is better for more-interactive or involved installations, such as via WP-CLI. The `install()` and `license()` methods simply wrap up the execution of the commands, squashing most of the output.

### Example usage

The `MappsApiClient` should be injected into a command or module [via the Dependency Injection container](container.md):

```php
namespace StellarWP\SomePlugin\Modules;

use StellarWP\PluginFramework\Modules\Module;
use StellarWP\PluginFramework\Services\Nexcess\MappsApiClient;

class SomeModule extends Module
{
    /**
     * The MAPPS API client.
     *
     * @var MappsApiClient
     */
    protected $client;

    /**
     * Construct a new instance of the module.
     *
     * @param MappsApiClient $client The MAPPS API client.
     */
    public function __construct(MappsApiClient $client)
    {
        $this->client = $client;
    }
}
```

Once injected, we can access the client methods:

```php
public function installPlugins()
{
    $plugin_ids = [4, 8, 15, 16, 23, 42];

    foreach ($plugin_ids as $plugin_id) {
        $this->client
            ->install($plugin_id)
            ->license($plugin_id);
    }
}
```
