<?php

namespace StellarWP\PluginFramework\Extensions\Plugins;

use StellarWP\PluginFramework\Concerns\RendersTemplates;
use StellarWP\PluginFramework\Contracts\ProvidesSettings;
use StellarWP\PluginFramework\Extensions\Contracts\HasPageCacheExclusions;
use StellarWP\PluginFramework\Services\Apache;
use StellarWP\PluginFramework\Services\Cache;
use StellarWP\PluginFramework\Services\DropIn;
use StellarWP\PluginFramework\Services\Managers\ExtensionConfigManager;
use StellarWP\PluginFramework\Services\WPConfig;

/**
 * Plugin configuration for Cache Enabler by KeyCDN
 *
 * @link https://wordpress.org/plugins/cache-enabler/
 */
class CacheEnabler extends PluginConfig
{
    use RendersTemplates;

    /**
     * The Apache service.
     *
     * @var Apache
     */
    protected $apache;

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
     * The ExtensionConfig manager.
     *
     * @var ExtensionConfigManager
     */
    protected $manager;

    /**
     * The Settings instance.
     *
     * @var ProvidesSettings
     */
    protected $settings;

    /**
     * The section identifier used for rewrite rules in the site's Htaccess file.
     *
     * Note that this is intentionally mirroring the code snippets provided by Cache Enabler:
     *
     * @link https://www.keycdn.com/support/wordpress-cache-enabler-plugin#apache
     */
    const HTACCESS_MARKER = 'Cache Enabler';

    /**
     * The option key used by Cache Enabler in versions 1.5.0 and newer.
     */
    const OPTION_NAME = 'cache_enabler';

    /**
     * Construct a new instance of the plugin config.
     *
     * @param DropIn                 $dropIn   The DropIn service.
     * @param WPConfig               $config   The WPConfig service.
     * @param Apache                 $apache   The Apache service.
     * @param ProvidesSettings       $settings The Settings instance.
     * @param ExtensionConfigManager $manager  The ExtensionConfigManager instance.
     */
    public function __construct(
        DropIn $dropIn,
        WPConfig $config,
        Apache $apache,
        ProvidesSettings $settings,
        ExtensionConfigManager $manager
    ) {
        $this->dropIn   = $dropIn;
        $this->config   = $config;
        $this->apache   = $apache;
        $this->settings = $settings;
        $this->manager  = $manager;
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
        $this->dropIn->install('advanced-cache.php', $this->pluginDir . '/advanced-cache.php');

        // Add default configuration for Cache Enabler.
        update_option(self::OPTION_NAME, array_merge(
            $this->getDefaultConfiguration(),
            (array) get_option(self::OPTION_NAME, [])
        ));

        // WordPress won't try to load advanced-cache.php without this, so set it after the config.
        $this->config->setConstant('WP_CACHE', true);

        // Finally, attempt to add the appropriate rewrite rules to the Htaccess file for maximum performance.
        $this->updateHtaccessFile();
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
        $this->apache->removeHtaccessSection(self::HTACCESS_MARKER);
        $this->config->removeConstant('WP_CACHE');
        $this->dropIn->remove('advanced-cache.php', $this->pluginDir . '/advanced-cache.php');
    }

    /**
     * Get the default Cache Enabler configuration.
     *
     * @return Array<string,mixed> The default configuration for Cache Enabler.
     */
    public function getDefaultConfiguration()
    {
        return [
            'cache_expires'                      => 1,
            'cache_expiry_time'                  => 1, // One hour.
            'clear_site_cache_on_changed_plugin' => 0,
            'clear_site_cache_on_saved_comment'  => 0,
            'clear_site_cache_on_saved_post'     => 0,
            'clear_site_cache_on_saved_term'     => 0,
            'clear_site_cache_on_saved_user'     => 0,
            'compress_cache'                     => 1,
            'convert_image_urls_to_webp'         => 0,
            'excluded_cookies'                   => sprintf(
                '/^(?!wordpress_test_cookie)(%s).*/',
                implode('|', array_map([$this, 'pregQuote'], $this->getDefaultExcludedCookies()))
            ),
            'excluded_page_paths'                => sprintf(
                '/^\/(%s)\/?/',
                implode('|', array_map([$this, 'pregQuote'], $this->getDefaultExcludedPaths()))
            ),
            'excluded_post_ids'                  => '',
            'excluded_query_strings'             => sprintf(
                '/^\/(%s)\/?/',
                implode('|', array_map([$this, 'pregQuote'], $this->getDefaultExcludedQueryParams()))
            ),
            'minify_html'                        => 0,
            'minify_inline_css_js'               => 0,
            'mobile_cache'                       => 0,
            'version'                            => defined('CACHE_ENABLER_VERSION')
                ? constant('CACHE_ENABLER_VERSION')
                : null,
        ];
    }

    /**
     * Modify the default Cache Enabler settings page (captured in the output buffer), then flush.
     *
     * @return void
     */
    public function filterSettingsScreen()
    {
        /*
         * Find and remove the div.notice.notice-info with the KeyCDN branding.
         *
         * This will match a div.notice.notice-info and inner contents that include "KeyCDN".
         *
         * Note that this is *not* fool-proof, and may not work properly if the notice becomes more complex
         * in the future!
         */
        $pattern = '~\s+\<div[^>]class="notice notice-info"[^>]*>(.+KeyCDN.+?)\</div\>\s*?[\r\n]*~is';

        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        echo preg_replace($pattern, PHP_EOL, (string) ob_get_clean(), 1);
    }

    /**
     * Actions to perform every time the plugin is loaded.
     *
     * @return void
     */
    public function load()
    {
        // Refresh the Htaccess file any time plugin settings change.
        // @phpstan-ignore-next-line Function is also used directly, so we're ok with it having a return.
        add_action('update_option_' . self::OPTION_NAME, [$this, 'updateHtaccessFile']);

        // Remove some branding on the Cache Enabler settings screen.
        add_action('settings_page_cache-enabler', function () {
            ob_start();
        }, 9);
        add_action('settings_page_cache-enabler', [$this, 'filterSettingsScreen'], 11);

        // Listen for requests to flush the page cache from the Cache service.
        // @phpstan-ignore-next-line Function is also used directly, so we're ok with it having a return.
        add_action(Cache::ACTION_PURGE_PAGE_CACHE, [$this, 'purgePageCache']);
    }

    /**
     * Purge the page cache.
     *
     * @return bool True if the cache was purged, false otherwise.
     */
    public function purgePageCache()
    {
        // @phpstan-ignore-next-line
        if (class_exists('Cache_Enabler') && method_exists('Cache_Enabler', 'clear_complete_cache')) {
            \Cache_Enabler::clear_complete_cache();

            return true;
        }

        return false;
    }

    /**
     * Update the Htaccess file based on the current configuration.
     *
     * @return bool True if the file was updated, false otherwise.
     */
    public function updateHtaccessFile()
    {
        return $this->apache->writeHtaccessSection(
            self::HTACCESS_MARKER,
            $this->getRewriteRulesForCurrentConfiguration(),
            true
        );
    }

    /**
     * Retrieve a list of cookie prefixes that should be excluded from the page cache by default.
     *
     * @return Array<string>
     */
    protected function getDefaultExcludedCookies()
    {
        $cookies = [
            // WordPress core.
            'comment_author_',
            'wordpress_',
            'wp-postpass_',
            'wp-resetpass-',
            'wp-settings-',
        ];

        // Append cookies from registered extension configs.
        foreach ($this->manager->getExtensionsImplementing(HasPageCacheExclusions::class) as $plugin) {
            /** @var HasPageCacheExclusions $plugin */
            $cookies = array_merge($cookies, $plugin->getPageCacheCookieExclusions());
        }

        return array_unique($cookies);
    }

    /**
     * Retrieve a list of paths that should be excluded from the page cache by default.
     *
     * These paths should be treated as prefixes: excluding "some-path" essentially tells Cache Enabler
     * "ignore any path matching '/some-path*'".
     *
     * @return Array<string>
     */
    protected function getDefaultExcludedPaths()
    {
        $paths = [
            // WordPress core.
            'wp-admin',
            'wp-cron.php',
            'wp-includes',
            'wp-json',
            'xmlrpc.php',
        ];

        // Append paths from registered extension configs.
        foreach ($this->manager->getExtensionsImplementing(HasPageCacheExclusions::class) as $plugin) {
            /** @var HasPageCacheExclusions $plugin */
            $paths = array_merge($paths, $plugin->getPageCachePathExclusions());
        }

        return array_unique($paths);
    }

    /**
     * Retrieve a list of query string parameters that should exclude a page from the cache by default.
     *
     * @return Array<string>
     */
    protected function getDefaultExcludedQueryParams()
    {
        $params = [
            '_ga',
            '_ke',
            'age-verified',
            'cn-reloaded',
            'fb_action_ids',
            'fb_action_types',
            'fb_source',
            'fbclid',
            'gclid',
            'mc_cid',
            'mc_eid',
            'ref',
            'usqp',
            'utm_campaign',
            'utm_content',
            'utm_expid',
            'utm_medium',
            'utm_source',
            'utm_term',
        ];

        // Append params from registered extension configs.
        foreach ($this->manager->getExtensionsImplementing(HasPageCacheExclusions::class) as $plugin) {
            /** @var HasPageCacheExclusions $plugin */
            $params = array_merge($params, $plugin->getPageCacheQueryParamExclusions());
        }

        return array_unique($params);
    }

    /**
     * Get the Apache rewrite rules that match the current configuration.
     *
     * @return string Rewrite rules that may be passed to the Apache service.
     */
    protected function getRewriteRulesForCurrentConfiguration()
    {
        $template = trailingslashit($this->settings->framework_path)
            . 'resources/snippets/cache-enabler-rewrite-rules.php';

        // @phpstan-ignore-next-line
        $settings = class_exists('Cache_Enabler') && method_exists('Cache_Enabler', 'get_settings')
            ? \Cache_Enabler::get_settings()
            : get_option(self::OPTION_NAME, []);
        $defaults = $this->getDefaultConfiguration();

        // Cache_Enabler::get_settings() can return false if it was unable to write defaults, so use ours instead.
        $settings = is_array($settings)
            ? array_merge($defaults, $settings)
            : $defaults;

        ob_start();
        $this->renderTemplate($template, [
            'compress'        => (bool) $settings['compress_cache'],
            'excludedCookies' => call_user_func(
                [$this->apache, 'regExpToRewriteCond'],
                $settings['excluded_cookies']
            ),
            'excludedQueries' => call_user_func(
                [$this->apache, 'regExpToRewriteCond'],
                $settings['excluded_query_strings']
            ),
            'mobile'          => (bool) $settings['mobile_cache'],
            'webp'            => (bool) $settings['convert_image_urls_to_webp'],
        ]);
        $content = ob_get_clean();

        return trim((string) $content);
    }

    /**
     * Escape special regex characters, using "/" as the delimiter.
     *
     * @param string $pattern The pattern to escape.
     *
     * @return string The escaped pattern.
     */
    protected function pregQuote($pattern)
    {
        return preg_quote($pattern, '/');
    }
}
