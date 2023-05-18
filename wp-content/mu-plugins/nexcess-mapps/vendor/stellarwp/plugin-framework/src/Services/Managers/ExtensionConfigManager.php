<?php

namespace StellarWP\PluginFramework\Services\Managers;

use StellarWP\PluginFramework\Concerns\RegistersExtensions;
use StellarWP\PluginFramework\Container;
use StellarWP\PluginFramework\Exceptions\ConfigurationException;
use StellarWP\PluginFramework\Extensions\Contracts\ConfiguresExtension;
use StellarWP\PluginFramework\Extensions\Plugins\PluginConfig;

/**
 * Manager responsible for the registration of extension configurations.
 */
class ExtensionConfigManager
{
    use RegistersExtensions;

    /**
     * The DI container.
     *
     * @var Container
     */
    protected $container;

    /**
     * A cache of filtered, resolved extensions.
     *
     * @var Array<class-string,Array<ConfiguresExtension>>
     */
    private $filterCache = [];

    /**
     * Construct a new instance of the ExtensionConfigManager.
     *
     * @param Container $container The DI container instance.
     */
    public function __construct(Container $container)
    {
        $this->container = $container;
    }

    /**
     * Retrieve all registered extensions that implement the given interface.
     *
     * @param class-string $interface The interface to look for.
     *
     * @return Array<ConfiguresExtension> An array of resolved extension configurations.
     */
    public function getExtensionsImplementing($interface)
    {
        if (! isset($this->filterCache[$interface])) {
            $extensions = [];

            foreach ($this->plugins as $plugin) {
                if (is_a($plugin, (string) $interface, true)) {
                    $extensions[] = $this->container->get($plugin);
                }
            }

            /** @var Array<ConfiguresExtension> $extensions */
            $this->filterCache[$interface] = $extensions;
        }

        return $this->filterCache[$interface];
    }

    /**
     * Retrieve a single plugin config.
     *
     * @param string $plugin The plugin name.
     *
     * @return ?class-string<PluginConfig> Either the registered PluginConfig class name or null if
     *                                     no config class is registered.
     */
    public function getPlugin($plugin)
    {
        return ! empty($this->plugins[$plugin]) ? $this->plugins[$plugin] : null;
    }

    /**
     * Determine whether or not a configuration is registered for the given plugin.
     *
     * @param mixed $plugin The plugin name.
     *
     * @return bool True if there's a registered PluginConfig for this plugin, false otherwise.
     */
    public function hasPlugin($plugin)
    {
        return is_scalar($plugin) && ! empty($this->plugins[$plugin]);
    }

    /**
     * Register a new plugin configuration.
     *
     * @param string                     $plugin The plugin to configure (e.g. "jetpack/jetpack.php").
     * @param class-string<PluginConfig> $config The PluginConfig class to apply.
     *
     * @return self
     */
    public function registerPlugin($plugin, $config)
    {
        if (! isset($this->plugins[$plugin]) || $this->plugins[$plugin] !== $config) {
            $this->plugins[$plugin] = $config;

            $this->filterCache = [];
        }

        return $this;
    }

    /**
     * Register a batch of plugin configurations.
     *
     * @param Array<string,class-string<PluginConfig>> $plugins Plugins to be registered.
     *
     * @return $this
     */
    public function registerPlugins($plugins)
    {
        $current       = $this->plugins;
        $this->plugins = array_merge($this->plugins, $plugins);

        if ($current !== $this->plugins) {
            $this->filterCache = [];
        }

        return $this;
    }

    /**
     * Resolve a plugin configuration through the DI container.
     *
     * @param string $plugin The plugin name.
     *
     * @throws ConfigurationException If attempting to resolve a plugin that has not been registered
     *                                or the resolution does not implement the ConfiguresPlugin interface.
     *
     * @return PluginConfig
     */
    public function resolvePlugin($plugin)
    {
        if (! $config = $this->getPlugin($plugin)) {
            throw new ConfigurationException(sprintf(
                'The ExtensionConfigManager does not have a definition for "%s", did you forget to register it?',
                $plugin
            ));
        }

        $instance = $this->container->get($config);

        if (! $instance instanceof PluginConfig) {
            throw new ConfigurationException(sprintf(
                'Resolved plugin configuration, %s, is not an instance of %s.',
                get_class($instance),
                PluginConfig::class
            ));
        }

        return $instance->setPluginDir($this->getPluginPath($plugin));
    }

    /**
     * Get the absolute system path to the given $plugin directory.
     *
     * @param string $plugin The plugin string to parse.
     *
     * @return string The system path to that plugin directory, without a trailing slash.
     */
    protected function getPluginPath($plugin)
    {
        $dir = dirname($plugin);

        if ('.' === $dir) {
            $dir = '';
        }

        return untrailingslashit(WP_PLUGIN_DIR . '/' . $dir);
    }
}
