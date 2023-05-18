# StellarWP Plugin Framework

The StellarWP Plugin Framework is meant to support the development of WordPress plugins across the StellarWP brands.

It's particularly-focused on the construction of platform-wide, must-use (MU) plugins as seen [in the Nexcess MAPPS MU plugin](https://github.com/liquidweb/nexcess-mapps), which runs across all Nexcess Managed WordPress and WooCommerce sites.

> ⚠️  **Heads Up!**<br>This repository is **not** a WordPress plugin by itself, but rather provides the underlying functionality for sophisticated, platform-wide plugins. Please see "Getting Started" below for details on building a new plugin with this framework.

## Getting started

Before anything else, it's important to [understand the architecture of these plugins](docs/architecture.md). With those fundamentals in place, the following documents will make a lot more sense:

* [Starting a new plugin with the StellarWP Plugin Framework](docs/scaffold-plugin.md)
* [Understanding the dependency injection (DI) container](docs/container.md)
* [Centralizing configuration with the Settings object](docs/settings.md)
* [The anatomy of Modules](docs/modules.md)
* [Writing CLI commands](docs/commands.md)
* [Logging errors](docs/logging.md)
* [Testing your new plugin](docs/testing.md)
* [Interacting with the Nexcess MAPPS API](docs/mapps-api.md)

## Contributing

If you would like to contribute to the StellarWP Plugin Framework itself, [please see the contributing guidelines](docs/contributing.md) for details on branching strategies, coding standards, and more.
