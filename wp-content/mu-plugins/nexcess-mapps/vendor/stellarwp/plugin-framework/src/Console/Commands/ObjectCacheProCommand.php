<?php

namespace StellarWP\PluginFramework\Console\Commands;

use StellarWP\PluginFramework\Console\WPCommand;
use StellarWP\PluginFramework\Extensions\Plugins\ObjectCachePro as Plugin;

/**
 * Commands for managing Object Cache Pro licensing.
 */
class ObjectCacheProCommand extends WPCommand
{
    /**
     * @var Plugin
     */
    private $plugin;

    /**
     * Create a new command instance.
     *
     * @param Plugin $plugin
     */
    public function __construct(Plugin $plugin)
    {
        $this->plugin = $plugin;
    }

    /**
     * Activate the Object Cache Pro License.
     *
     * ## OPTIONS
     *
     * <license>
     * : License to activate
     *
     * ## EXAMPLES
     *
     * $ wp nxmapps object-cache-pro activate abcdefghijklm1234567890
     * Success: Activated Object Cache Pro License.
     *
     * @param string[] $args Top-level arguments.
     *
     * @return void
     */
    public function activate($args)
    {
        list($license_key) = $args;

        // OCP uses object-cache-pro.php as the entry file in older versions, so check for both variations.
        if (
            ! array_key_exists('object-cache-pro/object-cache-pro.php', get_plugins())
            && ! array_key_exists('object-cache-pro/redis-cache-pro.php', get_plugins())
        ) {
            $this->error(__('Object Cache Pro must be installed first.', 'stellarwp-framework'));
        }

        update_option('object_cache_pro_license', sanitize_text_field($license_key));
        $this->line(__('Object Cache Pro license stored. ✅', 'stellarwp-framework'));

        if (
            ! is_plugin_active('object-cache-pro/object-cache-pro.php')
            && ! is_plugin_active('object-cache-pro/redis-cache-pro.php')
        ) {
            $this->line(__('Activating Object Cache Pro...', 'stellarwp-framework'));
            $this->wp('plugin activate object-cache-pro --quiet');
            $this->line(__('Object Cache Pro activated. ✅', 'stellarwp-framework'));
        }

        $this->line(__('Installing Object Cache Pro drop-in....', 'stellarwp-framework'));
        $this->line(__('Setting configuration in wp-config.php....', 'stellarwp-framework'));
        $wrote = $this->plugin->writeConfig();
        if ($wrote) {
            $this->success(__('You are all set to begin using Object Cache Pro! 🎉', 'stellarwp-framework'));
        } else {
            $this->warning(
                __(
                    'There was an issue writing the Object Cache Pro configuration to your wp-config.php file;
                    it will need adjusted manually. 
                    Please ensure the following constants are added to your wp-config.php:',
                    'stellarwp-framework'
                )
            );
            $this->newline();
            $this->line("define( 'WP_REDIS_CONFIG', " . $this->plugin->getRedisConfig() . " );
define( 'WP_REDIS_DISABLED', false );");
            $this->newline();
        }
    }
}
