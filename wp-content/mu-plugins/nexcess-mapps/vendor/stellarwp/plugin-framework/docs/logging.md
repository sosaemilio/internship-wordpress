# Error logging

The StellarWP Plugin Framework includes a [PSR-3 compliant logger](https://www.php-fig.org/psr/psr-3/) for handling all log messages originating from within the plugin.


## Log levels and methods

As the logger follows PSR-3, the following [RFC 5424-inspired](https://tools.ietf.org/html/rfc5424) methods are available (in descending order of severity):

* `Logger::emergency()`
* `Logger::alert()`
* `Logger::critical()`
* `Logger::error()`
* `Logger::warning()`
* `Logger::notice()`
* `Logger::info()`
* `Logger::debug()`

All of those methods are aliases for `Logger::log()` with their method name as the `$level` argument. That method, meanwhile, checks the current value of the `error_reporting` directive and, in instances where `E_NOTICE` is not present, will suppress notice, info, and debug messages.

Furthermore, `Logger::debug()` will not write messages unless `E_NOTICE` is present **and** `WP_DEBUG` is true.


## Injecting the logger

The logger itself can be injected into integrations and services via [the Dependency Injection container](container.md):

```php
namespace StellarWP\PluginStarter\Modules;

use StellarWP\PluginStarter\Services\Logger;

class SomeModule extends Module
{

    /**
     * @var Logger
     */
    protected $logger;

    /**
     * Create a new instance of the module.
     *
     * @param Logger $logger
     */
    public function __construct(Logger $logger)
    {
        $this->logger = $logger;
    }

    /**
     * Some method that writes to logs.
     */
    public function someMethod()
    {
        if ( true === true ) {
            $this->logger->info('All is right with the world');
        } else {
            $this->logger->emergency('True is no longer true, facts are meaningless.');
        }
    }
}
```


