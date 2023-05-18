<?php

/**
 * Enable symlinking of desired dependencies into place in the WordPress test environment.
 */

namespace StellarWP\PluginFramework\Support\Testing\Concerns;

use Tests\Exceptions\DependencyActivationException;
use Tests\Exceptions\MissingDependencyException;

trait InstallsDependencies
{
    use FiltersTestsByPHPVersion;
    use FiltersTestsByWordPressVersion;
    use ManagesSymlinks;

    /**
     * Plugins that should be installed.
     *
     * @var string[]
     */
    protected static $plugins = [];

    /**
     * A cache of plugins that have already caused activation issues.
     *
     * @var string[]
     */
    protected static $skippedPlugins = [];

    /**
     * Plugins that have been activated via activatePlugin().
     *
     * @var array
     */
    private $activatedPlugins = [];

    /**
     * Cached plugin data from WordPress' get_plugins() function.
     *
     * @var array
     */
    private $pluginData;

    /**
     * Install the plugins into the test environment.
     *
     * Plugins are "installed" by symlinking the Composer-installed versions into the WordPress
     * test environment.
     *
     * @throws MissingDependencyException If the plugin can't be linked.
     *
     * @beforeClass
     */
    public static function installPlugins()
    {
        $plugin_root   = PROJECT_ROOT . '/vendor/';
        $wp_plugin_dir = WP_CONTENT_DIR . '/plugins/';

        foreach (static::$plugins as $plugin) {
            $target = $plugin_root . $plugin;
            $link   = $wp_plugin_dir . basename($plugin);

            try {
                if (! self::symlink($target, $link, 'class')) {
                    throw new MissingDependencyException('PHP symlink() call failed.');
                }
            } catch (MissingDependencyException $e) {
                throw new MissingDependencyException(sprintf(
                    'Unable to symlink %1$s to %2$s: %3$s',
                    $link,
                    $target,
                    $e->getMessage()
                ), $e->getCode(), $e);
            }
        }
    }

    /**
     * Reset the $activatedPlugins array between tests.
     *
     * @before
     */
    protected function resetActivatedPlugins()
    {
        $this->activatedPlugins = [];
    }

    /**
     * Deactivate any plugins that were activated as part of a test.
     *
     * @after
     */
    protected function deactivatePluginsAfterTest()
    {
        if (! empty($this->activatedPlugins)) {
            deactivate_plugins($this->activatedPlugins);
        }
    }

    /**
     * Retrieve and cache WordPress' get_plugins() call.
     *
     * @return array
     */
    protected function getPlugins()
    {
        if (null === $this->pluginData) {
            wp_cache_flush();
            $this->pluginData = get_plugins();
        }

        return $this->pluginData;
    }

    /**
     * Symlink a file into the site's mu-plugins directory.
     *
     * @param string $plugin   The file that should be symlinked into the mu-plugins directory.
     *                         This should point to a single file, and be an absolute system path.
     * @param string $filename Optional. An alternative filename for the target symlink. Default is
     *                         empty, which will use basename($plugin).
     *
     * @throws MissingDependencyException If the plugin can't be linked.
     */
    public function installMuPlugin($plugin, $filename = '')
    {
        try {
            $target = PROJECT_ROOT . '/vendor/' . $plugin;
            $link   = WP_CONTENT_DIR . '/mu-plugins/' . $filename ?: basename($plugin);

            if (! self::symlink($target, $link)) {
                throw new MissingDependencyException('PHP symlink() call failed.');
            }
        } catch (MissingDependencyException $e) {
            throw new MissingDependencyException(sprintf(
                'Unable to symlink mu-plugin %1$s to %2$s: %3$s',
                $link,
                $target,
                $e->getMessage()
            ), $e->getCode(), $e);
        }
    }

    /**
     * Symlink a theme into the site's themes directory.
     *
     * @param string $theme The theme to symlink.
     *
     * @throws MissingDependencyException If the plugin can't be linked.
     */
    public function installTheme($theme)
    {
        try {
            $target = PROJECT_ROOT . '/vendor/' . $theme;
            $link   = WP_CONTENT_DIR . '/themes/' . basename($theme);

            if (! self::symlink($target, $link)) {
                throw new MissingDependencyException('PHP symlink() call failed.');
            }
        } catch (MissingDependencyException $e) {
            throw new MissingDependencyException(sprintf(
                'Unable to symlink theme %1$s to %2$s: %3$s',
                $link,
                $target,
                $e->getMessage()
            ), $e->getCode(), $e);
        }
    }

    /**
     * Activate a plugin within WordPress by the directory slug.
     *
     * @param string $plugin The plugin to activate.
     * @param bool   $silent Optional. Whether to silently activate, thereby skipping
     *                       activation hooks. Default is false.
     *
     * @throws DependencyActivationException If unable to activate the plugin.
     */
    protected function activatePlugin($plugin, $silent = false)
    {
        // If we already know this will fail, skip early.
        if (isset(self::$skippedPlugins[$plugin])) {
            $this->markTestSkipped(self::$skippedPlugins[$plugin]);
        }

        if ('jetpack' === $plugin) {
            /*
             * The minimum WordPress version was raised to 5.2 in Jetpack 8.0 (Dec 3, 2019).
             */
            $this->requiresAtLeastWordPress(5.2, 'Modern versions of Jetpack require WordPress 5.2 or newer, skipping.'); // phpcs:ignore Generic.Files.LineLength.TooLong
        } elseif ('woocommerce' === $plugin) {
            /*
             * WooCommerce 7.1 (released in November 2022) dropped support for PHP < 7.4, so don't let
             * tests even try to activate it in environments running PHP 5.6.
             */
            $this->requiresAtLeastPHP('7.4', 'Supported versions of WooCommerce require PHP 7.4 or newer.');
        }

        $plugin_file = $this->getPluginFile($plugin);
        $activated   = activate_plugin($plugin_file, '', false, $silent);

        // Unexpected plugin output detected.
        if (is_wp_error($activated)) {
            switch ($activated->get_error_code()) {
                case 'plugin_wp_incompatible':
                    self::$skippedPlugins[$plugin] = $activated->get_error_message();
                    $this->markTestSkipped($activated->get_error_message());
                    // Skipping the test throws a SkippedTestError|SyntheticSkippedError and
                    // will result in exiting this function.
                    // no break.
                case 'unexpected_output':
                    $message = sprintf(
                        "Unable to activate %1\$s due to unexpected output:\n%2\$s",
                        $plugin,
                        $activated->get_error_data('unexpected_output')
                    );
                    break;
                default:
                    $message = sprintf(
                        "Unable to activate %1\$s:\n%2\$s",
                        $plugin,
                        wp_strip_all_tags($activated->get_error_message())
                    );
            }

            self::$skippedPlugins[$plugin] = sprintf(
                'Skipping activation of %1$s due to previous activation error: %2$s',
                $plugin,
                $message
            );

            throw new DependencyActivationException($message);
        }

        // Track the plugin so we can deactivate it at the end of the test.
        $this->activatedPlugins[] = $plugin_file;
    }

    /**
     * Deactivate a plugin within WordPress by the directory slug.
     *
     * @param string $plugin The plugin to deactivate.
     *
     * @throws MissingDependencyException If the plugin can't be linked.
     */
    protected function deactivatePlugin($plugin)
    {
        $file = $this->getPluginFile($plugin);

        if (! is_plugin_active($file)) {
            throw new MissingDependencyException(sprintf(
                'Plugin %1$s (%2$s) cannot be deactivated, as it is not currently active.',
                $plugin,
                $file
            ));
        }

        deactivate_plugins($this->getPluginFile($plugin));
    }

    /**
     * Pretend the given plugin is active.
     *
     * For tests that only need to pass a `is_plugin_active()` check, this method injects the given
     * plugin slug into the "active_plugins" option via filter. The plugin will not actually be
     * activated, and none of its functionality will be available.
     *
     * @param string $plugin The plugin name (e.g. "woocommerce/woocommerce.php").
     */
    protected function mockPluginActivation($plugin)
    {
        add_filter('option_active_plugins', function ($plugins) use ($plugin) {
            if (! in_array($plugin, $plugins, true)) {
                $plugins[] = $plugin;
            }

            return $plugins;
        });
    }

    /**
     * Pretend the given plugin is not active.
     *
     * The opposite of `mockPluginActivation()`.
     *
     * @see mockPluginActivation
     *
     * @param string $plugin The plugin name (e.g. "woocommerce/woocommerce.php").
     */
    protected function mockPluginDeactivation($plugin)
    {
        add_filter('option_active_plugins', function ($plugins) use ($plugin) {
            return array_filter($plugins, function ($item) use ($plugin) {
                return $item !== $plugin;
            });
        });
    }

    /**
     * Retrieve the plugin file for a given slug.
     *
     * For example, calling the method on "jetpack" should return "jetpack/jetpack.php" if Jetpack
     * is installed on the site.
     *
     * @param string $plugin The plugin slug.
     *
     * @throws MissingDependencyException If no matching dependency was found.
     *
     * @return string The plugin filename.
     */
    protected function getPluginFile($plugin)
    {
        $plugins = array_filter($this->getPlugins(), function ($file) use ($plugin) {
            return 0 === mb_strpos($file, $plugin . '/');
        }, ARRAY_FILTER_USE_KEY);

        if (empty($plugins)) {
            throw new MissingDependencyException(sprintf(
                'Unable to find a plugin matching "%s" to activate.',
                $plugin
            ));
        }

        return key($plugins);
    }
}
