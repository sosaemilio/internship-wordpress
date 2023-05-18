<?php

namespace StellarWP\PluginFramework\Extensions\Plugins;

use StellarWP\PluginFramework\Services\DropIn;
use StellarWP\PluginFramework\Services\WPConfig;
use StellarWP\PluginFramework\Settings;

/**
 * Plugin configuration for Redis Object Cache by Till Krüss.
 *
 * @link https://wordpress.org/plugins/redis-cache/
 */
class RedisCache extends PluginConfig
{
    /**
     * The WPConfig service.
     *
     * @var WPConfig
     */
    protected $config;

    /**
     * The DropIn service.
     *
     * @var DropIn
     */
    protected $dropIn;

    /**
     * The Settings object.
     *
     * @var Settings
     */
    protected $settings;

    /**
     * Construct a new instance of the plugin config.
     *
     * @param Settings $settings The settings object.
     * @param DropIn   $dropIn   The DropIn service.
     * @param WPConfig $config   The WPConfig service.
     */
    public function __construct(Settings $settings, DropIn $dropIn, WPConfig $config)
    {
        $this->settings = $settings;
        $this->dropIn   = $dropIn;
        $this->config   = $config;
    }

    /**
     * Actions to perform upon plugin activation.
     *
     * @param bool $network_wide Optional. Is the plugin being activated network-wide?
     *                           Default is false.
     *
     * @return void
     */
    public function activate($network_wide = false)
    {
        if (empty($this->settings->redis_host) || empty($this->settings->redis_port)) {
            return;
        }

        try {
            $this->config
                ->setConstant('WP_REDIS_HOST', $this->settings->redis_host)
                ->setConstant('WP_REDIS_PORT', $this->settings->redis_port)
                ->setConstant('WP_REDIS_DISABLE_BANNERS', true)
                ->setConstant('WP_REDIS_DISABLE_COMMENT', true);

            $this->dropIn->install('object-cache.php', $this->pluginDir . '/includes/object-cache.php');
        } catch (\Exception $e) {
            // If an error occurs, attempt to back out the configuration.
            $this->deactivate($network_wide);
        }
    }

    /**
     * Actions to perform upon plugin deactivation.
     *
     * @param bool $network_wide Optional. Is the plugin being deactivated network-wide?
     *                           Default is false.
     *
     * @return void
     */
    public function deactivate($network_wide = false)
    {
        $this->dropIn->remove('object-cache.php', $this->pluginDir . '/includes/object-cache.php');

        $this->config
            ->removeConstant('WP_REDIS_HOST')
            ->removeConstant('WP_REDIS_PORT')
            ->removeConstant('WP_REDIS_DISABLE_BANNERS')
            ->removeConstant('WP_REDIS_DISABLE_COMMENT');
    }
}
