<?php

namespace StellarWP\PluginFramework;

use Psr\Log\LoggerInterface;
use StellarWP\AdminNotice\DismissalHandler;
use StellarWP\PluginFramework\Concerns\RegistersCommands;
use StellarWP\PluginFramework\Concerns\RegistersExtensions;
use StellarWP\PluginFramework\Concerns\RegistersModules;
use StellarWP\PluginFramework\Console\Contracts\IsContextAware;
use StellarWP\PluginFramework\Console\WPCommand;
use StellarWP\PluginFramework\Contracts\LoadsConditionally;
use StellarWP\PluginFramework\Contracts\ProvidesSettings;
use StellarWP\PluginFramework\Contracts\PublishesAdminNotices;
use StellarWP\PluginFramework\Modules\Module;
use StellarWP\PluginFramework\Services\Managers\CronEventManager;
use StellarWP\PluginFramework\Services\Managers\ExtensionConfigManager;
use StellarWP\PluginFramework\Services\Managers\MetaBoxManager;
use WP_CLI;

/**
 * The base plugin class.
 */
class Plugin
{
    use RegistersCommands;
    use RegistersExtensions;
    use RegistersModules;

    /**
     * The plugin's DI container instance.
     *
     * @var Container
     */
    protected $container;

    /**
     * The plugin's logger instance.
     *
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * Base command names for WP-CLI. This allows things like `wp nxmapps` or `wp nexcess-mapps` (or both) for commands.
     *
     * @var array<string>
     */
    protected $command_namespaces = [];

    /**
     * Construct a new instance of the plugin.
     *
     * @param Container       $container The DI container instance.
     * @param LoggerInterface $logger    The PSR-3 logger for the plugin.
     */
    public function __construct(Container $container, LoggerInterface $logger)
    {
        $this->container = $container;
        $this->logger    = $logger;
    }

    /**
     * Bootstrap the plugin.
     *
     * @return self
     */
    public function init()
    {
        // Inject $this->plugins into the ExtensionConfigManager.
        if (! empty($this->plugins)) {
            /** @var ExtensionConfigManager $extensionManager */
            $extensionManager = $this->container->get(ExtensionConfigManager::class);
            $extensionManager->registerPlugins($this->plugins);
        }

        // Load all registered modules.
        $this->loadModules();

        // If we're in a WP-CLI context, register commands within WP-CLI.
        if (defined('WP_CLI') && WP_CLI) {
            $this->loadCommands();
        }

        /*
         * On init, register queued cron events.
         *
         * Should this list grow, we may want to revisit the blatant use of the Service Locator pattern here.
         */
        add_action('init', [$this->container->get(CronEventManager::class), 'scheduleEvents']);

        // Register meta boxes.
        $metaBoxManager = $this->container->get(MetaBoxManager::class);
        add_action('wp_dashboard_setup', [$metaBoxManager, 'registerMetaBoxes']);
        add_action('add_meta_boxes', [$metaBoxManager, 'registerMetaBoxes']);

        // Register default scripts and styles.
        add_action('wp_enqueue_scripts', [$this, 'registerAdminScriptsAndStyles'], 0);
        add_action('admin_enqueue_scripts', [$this, 'registerAdminScriptsAndStyles'], 0);

        return $this;
    }

    /**
     * Register default admin scripts and styles.
     *
     * This is meant for general scripts and styles that can be used across multiple modules. Scripts
     * and styles meant for a specific use-case should be registered within the corresponding
     * modules.
     *
     * @return void
     */
    public function registerAdminScriptsAndStyles()
    {
        /** @var ProvidesSettings $settings */
        $settings = $this->container->get(ProvidesSettings::class);

        wp_register_style(
            'stellarwp-forms',
            $settings->framework_url . 'dist/css/forms.css',
            [],
            $settings->plugin_version,
            'all'
        );

        // Remove default <button> styling within the WP Admin Bar.
        wp_add_inline_style(
            'admin-bar',
            '.stellarwp-admin-bar-inline-form button {color: inherit; background: none; border: none; cursor:pointer;}'
        );
    }

    /**
     * Load all registered commands.
     *
     * @return void
     */
    protected function loadCommands()
    {
        // If we have command namespaces, prefix each command with the namespace.
        if (! empty($this->command_namespaces)) {
            foreach ($this->command_namespaces as $namespace) {
                foreach ($this->getCommands() as $name => $command) {
                    $this->loadCommand($namespace . ' ' . $name, $command);
                }
            }
            return;
        }

        // This is if we don't have any command namespaces, so no prefixes needed.
        foreach ($this->getCommands() as $name => $command) {
            $this->loadCommand($name, $command);
        }
    }

    /**
     * Load an individual command.
     *
     * @param string $name    The command name.
     * @param string $command The container abstract for the command class.
     *
     * @return bool True if the module was loaded, false otherwise.
     */
    protected function loadCommand($name, $command)
    {
        try {
            /** @var WPCommand&callable $instance */
            $instance = $this->container->get($command);

            // Commands may implement the LoadsConditionally interface.
            if ($instance instanceof LoadsConditionally) {
                if (! $instance->shouldLoad()) {
                    $this->container->forget($command);
                    unset($instance);
                    return false;
                }
            }

            // Context-aware commands should know how they're registered.
            if ($instance instanceof IsContextAware) {
                $instance->setCommandNamespace($name);
            }

            WP_CLI::add_command($name, $instance);
        } catch (\Exception $e) {
            $this->logger->warning($e->getMessage());
            $this->container->forget($command);
            return false;
        }

        return true;
    }

    /**
     * Load all registered modules.
     *
     * @return void
     */
    protected function loadModules()
    {
        foreach ($this->getModules() as $module) {
            $this->loadModule($module);
        }
    }

    /**
     * Load an individual module.
     *
     * @param string $module The module to load.
     *
     * @return bool True if the module was loaded, false otherwise.
     */
    protected function loadModule($module)
    {
        try {
            /** @var Module&callable $instance */
            $instance = $this->container->get($module);

            // Modules may implement the LoadsConditionally interface.
            if ($instance instanceof LoadsConditionally) {
                if (! $instance->shouldLoad()) {
                    $this->container->forget($module);
                    unset($instance);
                    return false;
                }
            }

            // Queue the DismissalHandler if the module publishes admin notices.
            if ($instance instanceof PublishesAdminNotices) {
                DismissalHandler::listen();
            }

            $instance->setup();
        } catch (\Exception $e) {
            $this->logger->warning($e->getMessage());
            $this->container->forget($module);
            return false;
        }

        return true;
    }
}
