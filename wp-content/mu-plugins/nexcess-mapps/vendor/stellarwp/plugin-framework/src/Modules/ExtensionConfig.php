<?php

namespace StellarWP\PluginFramework\Modules;

use Psr\Log\LoggerInterface;
use StellarWP\PluginFramework\Exceptions\ConfigurationException;
use StellarWP\PluginFramework\Services\Managers\ExtensionConfigManager;
use WP_Upgrader;

/**
 * Enable custom rules for plugins and themes.
 */
class ExtensionConfig extends Module
{
    /**
     * The Logger instance.
     *
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * The ExtensionConfigManager instance.
     *
     * @var ExtensionConfigManager
     */
    protected $manager;

    /**
     * Construct a new ExtensionConfig instance.
     *
     * @param ExtensionConfigManager $manager
     * @param LoggerInterface        $logger
     */
    public function __construct(ExtensionConfigManager $manager, LoggerInterface $logger)
    {
        $this->manager = $manager;
        $this->logger  = $logger;
    }

    /**
     * Perform any necessary setup for the module.
     *
     * @return void
     */
    public function setup()
    {
        // Plugins.
        add_action('activate_plugin', [$this, 'activatePlugin'], 10, 2);
        add_action('deactivate_plugin', [$this, 'deactivatePlugin'], 10, 2);
        add_action('plugin_loaded', [$this, 'loadPlugin'], 10);
        add_action('upgrader_process_complete', [$this, 'updatePlugins'], 10, 2);
    }

    /**
     * Apply any post-activation configurations for plugins.
     *
     * @param string $plugin       The plugin name.
     * @param bool   $network_wide Optional. Was the plugin activated network-wide? Default is false.
     *
     * @return void
     */
    public function activatePlugin($plugin, $network_wide = false)
    {
        if ($this->manager->hasPlugin($plugin)) {
            try {
                $config = $this->manager->resolvePlugin($plugin);
                $config->activate($network_wide);
            } catch (ConfigurationException $e) {
                $this->logger->warning($e->getMessage());
            } catch (\Exception $e) {
                $this->logger->error(sprintf(
                    'Encountered an error during activation of %1$s (extension %2$s): %3$s',
                    $plugin,
                    get_class($config),
                    $e->getMessage()
                ));
            }
        }
    }

    /**
     * Apply any post-deactivation configurations for plugins.
     *
     * @param string $plugin       The plugin name.
     * @param bool   $network_wide Optional. Was the plugin activated network-wide? Default is false.
     *
     * @return void
     */
    public function deactivatePlugin($plugin, $network_wide = false)
    {
        if ($this->manager->hasPlugin($plugin)) {
            try {
                $config = $this->manager->resolvePlugin($plugin);
                $config->deactivate($network_wide);
            } catch (ConfigurationException $e) {
                $this->logger->warning($e->getMessage());
            } catch (\Exception $e) {
                $this->logger->error(sprintf(
                    'Encountered an error during deactivation of %1$s (extension %2$s): %3$s',
                    $plugin,
                    get_class($config),
                    $e->getMessage()
                ));
            }
        }
    }

    /**
     * Apply any post-activation configurations for plugins.
     *
     * @param string $plugin The plugin name.
     *
     * @return void
     */
    public function loadPlugin($plugin)
    {
        if (! is_scalar($plugin)) {
            return;
        }

        // The plugin_loaded hook includes the full system path.
        $plugin = str_replace(trailingslashit(WP_PLUGIN_DIR), '', $plugin);

        if ($this->manager->hasPlugin($plugin)) {
            try {
                $config = $this->manager->resolvePlugin($plugin);
                $config->load();
            } catch (ConfigurationException $e) {
                $this->logger->warning($e->getMessage());
            } catch (\Exception $e) {
                $this->logger->error(sprintf(
                    'Encountered an error during the loading of %1$s (extension %2$s): %3$s',
                    $plugin,
                    get_class($config),
                    $e->getMessage()
                ));
            }
        }
    }

    /**
     * Handle plugin updates.
     *
     * @param WP_Upgrader  $upgrader The WP_Upgrader instance.
     * @param Array<mixed> $data     Details about the upgraded plugin(s).
     *
     * @return void
     */
    public function updatePlugins(WP_Upgrader $upgrader, array $data)
    {
        if (empty($data['plugins'])) {
            return;
        }

        foreach ((array) $data['plugins'] as $plugin) {
            if (! is_scalar($plugin) || ! $this->manager->hasPlugin($plugin)) {
                continue;
            }

            try {
                $config = $this->manager->resolvePlugin((string) $plugin);
                $config->update();
            } catch (ConfigurationException $e) {
                $this->logger->warning($e->getMessage());
            } catch (\Exception $e) {
                $this->logger->error(sprintf(
                    'Encountered an error while upgrading %1$s (extension %2$s): %3$s',
                    $plugin,
                    get_class($config),
                    $e->getMessage()
                ));
            }
        }
    }
}
