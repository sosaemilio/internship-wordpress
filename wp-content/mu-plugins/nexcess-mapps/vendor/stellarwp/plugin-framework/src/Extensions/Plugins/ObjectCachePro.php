<?php

namespace StellarWP\PluginFramework\Extensions\Plugins;

use StellarWP\PluginFramework\Contracts\ProvidesSettings;
use StellarWP\PluginFramework\Exceptions\WPConfigException;
use StellarWP\PluginFramework\Services\DropIn;
use StellarWP\PluginFramework\Services\Managers\CronEventManager;
use StellarWP\PluginFramework\Services\WPConfig;
use WP_Screen;

/**
 * Plugin configuration for Object Cache Pro by Till Krüss.
 *
 * @link https://objectcache.pro/
 */
class ObjectCachePro extends PluginConfig
{
    /**
     * The WPConfig service.
     *
     * @var WPConfig
     */
    protected $config;

    /**
     * The CronEventManager instance.
     *
     * @var CronEventManager
     */
    protected $cron;

    /**
     * The DropIn service.
     *
     * @var DropIn
     */
    protected $dropIn;

    /**
     * The ProvidesSettings object.
     *
     * @var ProvidesSettings
     */
    protected $settings;

    /**
     * Set our cron-related constants.
     */
    const CLEAN_CONSTANTS_CRON         = 'load_ocp_clean_constants';
    const CLEAN_CONSTANTS_CHECK_ACTION = 'StellarWP\\Extensions\\Plugins\\ObjectCachePro\\CleanConstantsCron';

    /**
     * @var array<string>
     */
    protected $freeVersionConstants = [
        'WP_REDIS_HOST',
        'WP_REDIS_PORT',
        'WP_REDIS_PATH',
        'WP_REDIS_SCHEME',
        'WP_REDIS_DATABASE',
        'WP_REDIS_PREFIX',
        'WP_CACHE_KEY_SALT',
        'WP_REDIS_MAXTTL',
        'WP_REDIS_CLIENT',
        'WP_REDIS_TIMEOUT',
        'WP_REDIS_READ_TIMEOUT',
        'WP_REDIS_IGNORED_GROUPS',
        'WP_REDIS_RETRY_INTERVAL',
        'WP_REDIS_GLOBAL_GROUPS',
        'WP_REDIS_METRICS_MAX_TIME',
        'WP_REDIS_IGBINARY',
        'WP_REDIS_SERIALIZER',
        'WP_REDIS_DISABLED',
        'WP_REDIS_DISABLE_METRICS',
        'WP_REDIS_DISABLE_BANNERS',
        'WP_REDIS_DISABLE_DROPIN_AUTOUPDATE',
        'WP_REDIS_GRACEFUL',
        'WP_REDIS_SELECTIVE_FLUSH',
        'WP_REDIS_UNFLUSHABLE_GROUPS',
    ];

    /**
     * Construct a new instance of the plugin config.
     *
     * @param ProvidesSettings $settings     The ProvidesSettings instance.
     * @param DropIn           $dropIn       The DropIn service.
     * @param WPConfig         $config       The WPConfig service.
     * @param CronEventManager $cron         The CronEventManager instance.
     */
    public function __construct(ProvidesSettings $settings, DropIn $dropIn, WPConfig $config, CronEventManager $cron)
    {
        $this->settings     = $settings;
        $this->dropIn       = $dropIn;
        $this->config       = $config;
        $this->cron         = $cron;
    }

    /**
     * Actions to perform every time the plugin is loaded.
     *
     * @return void
     */
    public function load()
    {
        add_filter('objectcache_omit_settings_pointer', '__return_true');
        add_filter('default_hidden_meta_boxes', [ $this, 'hideObjectCacheProDashboardWidget' ], 10, 2);

        // Register a daily cron event for maintenance tasks.
        if (
            $this->config->hasConstant('WP_REDIS_HOST')
            && ! get_option('stellarwp_load_ocp_cleaned_constants', false)
        ) {
            $this->cron->register(self::CLEAN_CONSTANTS_CRON)->scheduleEvents();
            add_action(self::CLEAN_CONSTANTS_CRON, [$this, 'runCleanConstantsCron']);
        }
    }

    /**
     * Runs the cleanup process to remove unnecessary constants.
     *
     * @return mixed
     */
    public function runCleanConstantsCron()
    {
        // Make sure that we don't run this action multiple times in one request.
        if (did_action(self::CLEAN_CONSTANTS_CHECK_ACTION)) {
            return;
        }

        // Fire the action to avoid a loop.
        do_action(self::CLEAN_CONSTANTS_CHECK_ACTION);

        // Remove the constants that are no longer needed.
        $count = 0;
        foreach ($this->freeVersionConstants as $constant) {
            if ($this->config->hasConstant($constant)) {
                $this->config->removeConstant($constant);
                $count++;
            }
        }

        update_option('stellarwp_load_ocp_cleaned_constants', true);
        return $count;
    }

    /**
     * Hide the Object Cache Pro dashboard widget by default.
     *
     * @param string[]  $hidden The meta boxes hidden by default.
     * @param WP_Screen $screen The current WP_Screen object.
     *
     * @return Array<string>
     */
    public function hideObjectCacheProDashboardWidget(array $hidden, WP_Screen $screen)
    {
        if (in_array($screen->id, [ 'dashboard', 'dashboard-network' ], true)) {
            $hidden[] = 'dashboard_rediscachepro';
        }

        return $hidden;
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
        if (! $this->settings->redis_host || ! $this->settings->redis_port) {
            return;
        }

        if (! $this->dropIn->install('object-cache.php', $this->pluginDir . '/stubs/object-cache.php')) {
            return;
        }

        $this->writeConfig();
        wp_cache_flush();
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
        $this->dropIn->remove('object-cache.php', $this->pluginDir . '/stubs/object-cache.php');
        $this->config->removeConstant('WP_REDIS_CONFIG');
        $this->config->removeConstant('WP_REDIS_DISABLED');
        wp_cache_flush();
    }

    /**
     * Writes the Object Cache Pro configuration constants to the wp-config.php file.
     *
     * @return bool True when the config is successfully written to, false otherwise.
     */
    public function writeConfig()
    {
        try {
            $this->config->setConfig('constant', 'WP_REDIS_CONFIG', $this->getRedisConfig(), [
                'raw' => true,
            ]);
            $this->config->setConfig('constant', 'WP_REDIS_DISABLED', 'false', ['raw' => true]);
        } catch (WPConfigException $e) {
            return false;
        }

        return true;
    }

    /**
     * Generate the configuration needed for Object Cache Pro.
     *
     * @return string Configuration as a string
     */
    public function getRedisConfig()
    {
        $license    = ! is_array(get_option('object_cache_pro_license', ''))
                      ? strval(get_option('object_cache_pro_license', ''))
                      : '';
        $redis_host = $this->settings->redis_host;
        $redis_port = $this->settings->redis_port;

        // Use the socket if it is available, otherwise continue with the default IP:port.
        if (file_exists($this->settings->redis_socket) && is_readable($this->settings->redis_socket)) {
            $redis_host = $this->settings->redis_socket;
            $redis_port = '0';
        }

        $config_array = [
            'token'            => "'{$license}'",
            'host'             => "'{$redis_host}'",
            'port'             => "'{$redis_port}'",
            'database'         => "'0'",
            'maxttl'           => '86400 * 7',
            'timeout'          => '1.0',
            'read_timeout'     => '1.0',
            'retry_interval'   => 10,
            'retries'          => 3,
            'backoff'          => "'smart'",
            'compression'      => "'zstd'",
            'serializer'       => "'igbinary'",
            'async_flush'      => 'true',
            'split_alloptions' => 'true',
            'prefetch'         => 'true',
            'debug'            => 'false',
            'save_commands'    => 'false',
        ];

        $array_string = "[\n";
        foreach ($config_array as $key => $value) {
            $array_string .= "\t'{$key}' => {$value},\n";
        }
        $array_string .= ']';

        return $array_string;
    }
}
