<?php

/**
 * Controls for VisualComparison.
 */

namespace StellarWP\PluginFramework\Modules;

use StellarWP\PluginFramework\Contracts\ProvidesSettings;
use StellarWP\PluginFramework\Exceptions\InvalidUrlException;
use StellarWP\PluginFramework\Services\Logger;
use StellarWP\PluginFramework\Support\VisualRegressionUrl;

class VisualComparison extends Module
{
    /**
     * @var Logger
     */
    protected $logger;

    /**
     * @var ProvidesSettings
     */
    protected $settings;

    /**
     * The settings group.
     */
    const SETTINGS_GROUP = 'stellarwp_visual_comparison';

    /**
     * The option name used to store custom URLs.
     */
    const SETTING_NAME = 'stellarwp_visual_regression_urls';

    /**
     * The maximum number of URLs permitted per site.
     */
    const MAXIMUM_URLS = 10;

    /**
     * @param ProvidesSettings $settings
     * @param Logger           $logger
     */
    public function __construct(ProvidesSettings $settings, Logger $logger)
    {
        $this->settings = $settings;
        $this->logger   = $logger;
    }

    /**
     * Perform any necessary setup for the integration.
     *
     * This method is automatically called as part of Plugin::loadIntegration(), and is the
     * entry-point for all integrations.
     */
    public function setup()
    {
        add_filter('option_' . static::SETTING_NAME, [ $this, 'expandOptionValue' ]);
    }

    /**
     * Automatically expand the contents of self::SETTING_NAME to an array of
     * VisualRegressionUrl objects.
     *
     * @param array<mixed> $value The option value.
     *
     * @return array<VisualRegressionUrl> An array of regression URLs.
     */
    public function expandOptionValue($value)
    {
        if (! is_array($value)) {
            $value = json_decode($value, true) ?: [];
        }

        $values = array_map(function ($entry) {
            if (! is_array($entry)) {
                return '';
            }

            $path = ! empty($entry['path']) ? $entry['path'] : false;

            if ($path) {
                $path = new VisualRegressionUrl(
                    strval($path),
                    ! empty($entry['description']) ? strval($entry['description']) : ''
                );
            }

            return $path;
        }, (array) $value);

        return array_values(array_filter($values));
    }

    /**
     * Retrieve the URLs that should be checked during visual comparison.
     *
     * @return VisualRegressionUrl[]
     */
    public function getUrls()
    {
        $urls = get_option(static::SETTING_NAME, false);

        // Only if the option isn't set do we want to generate the default urls.
        if (empty($urls)) {
            $urls = (array) $this->getDefaultUrls();
        }

        if (static::MAXIMUM_URLS < count($urls)) { /** @phpstan-ignore-line */
            $this->logger->warning(sprintf(
                // phpcs:ignore Generic.Files.LineLength.TooLong
                'Visual regression testing is currently limited to %1$d URLs, but %2$s were provided. Only the first %1$d will be processed.',
                static::MAXIMUM_URLS,
                count($urls) /** @phpstan-ignore-line */
            ));
        }

        /** @var VisualRegressionUrl[] */
        return $urls;
    }

    /**
     * Resolves path trailing slashes based on permalink_structure option for url array.
     *
     * @param string[] $urls
     *
     * @return string[]
     */
    public function resolveTrailingSlashes($urls)
    {
        $link_structure = strval(get_option('permalink_structure')) ?: '';
        if (! $urls || ! $link_structure) {
            return $urls;
        }

        $ends_with_slash = '/' === mb_substr($link_structure, -1);

        $resolved = [];
        foreach ($urls as $url) {
            $query = '';
            if (false !== mb_strpos($url, '?')) {
                $parsed_url = wp_parse_url($url);
                $url        = ! empty($parsed_url['path']) ? $parsed_url['path'] : $url;
                $query      = ! empty($parsed_url['query']) ? $parsed_url['query'] : $query;
            }

            $url = untrailingslashit($url);

            if (! $url || $ends_with_slash) {
                $url .= '/';
            }

            $resolved[] = $url . ($query ? '?' . $query : '');
        }

        return $resolved;
    }

    /**
     * Get the default URLs to check during visual comparison.
     *
     * @return array<mixed>
     */
    protected function getDefaultUrls()
    {
        $urls = [
            new VisualRegressionUrl('/', 'Homepage'),
        ];

        // If the site has a static front page, explicitly grab its page_for_posts.
        if ('page' === get_option('show_on_front')) {
            $urls[] = new VisualRegressionUrl(
                get_permalink(intval(get_option('page_for_posts', ''))) ?: '',
                'Page for posts'
            );
        }

        $urls = array_merge(
            $urls,
            $this->getDefaultPostUrls(),
            $this->getDefaultTaxonomyUrls()
        );

        if (is_plugin_active('woocommerce/woocommerce.php')) {
            $urls = array_merge($urls, $this->getDefaultWooCommerceUrls());
        }

        // Limit the defaults to the maximum number of URLs.
        $urls = array_slice($urls, 0, static::MAXIMUM_URLS);

        /**
         * Filter the default URLs provided to the Visual Comparison tool.
         *
         * @param array<VisualRegressionUrl> $urls An array of VisualComparisonUrl objects to be checked.
         */
        return (array) apply_filters('stellarwp_default_visual_regression_urls', $urls);
    }

    /**
     * Get that represent various post types.
     *
     * @return array<VisualRegressionUrl>
     *
     * @global $wpdb
     */
    protected function getDefaultPostUrls()
    {
        global $wpdb;

        $post_urls  = [];
        $post_types = get_post_types([
            'public' => true,
        ], 'names');

        // Find one example of each public post type.
        $results = $wpdb->get_results($wpdb->prepare(
            "
                SELECT p.post_type, p.ID
                FROM {$wpdb->posts} p
                WHERE p.post_type IN (" . implode(', ', array_fill(0, count($post_types), '%s')) . ")
                AND p.post_status IN ('publish', 'inherit')
                GROUP BY p.post_type
            ",
            $post_types
        ));

        foreach ($results as $post) {
            $post_urls[] = new VisualRegressionUrl(get_permalink($post->ID) ?: '', 'Single ' . $post->post_type);
        }

        return $post_urls;
    }

    /**
     * Select URLs to represent taxonomy terms.
     *
     * @return array<VisualRegressionUrl>
     *
     * @global $wpdb
     */
    protected function getDefaultTaxonomyUrls()
    {
        global $wpdb;

        $tax_urls   = [];
        $taxonomies = get_taxonomies([
            'publicly_queryable' => true,
        ]);

        $results = $wpdb->get_results($wpdb->prepare(
            "
                SELECT t.taxonomy, t.term_id
                FROM {$wpdb->term_taxonomy} t
                WHERE t.taxonomy IN (" . implode(', ', array_fill(0, count($taxonomies), '%s')) . ')
                AND t.count > 0
                GROUP BY t.taxonomy
            ',
            $taxonomies
        ));

        foreach ($results as $term) {
            $link = get_term_link((int) $term->term_id, $term->taxonomy);

            if (! is_wp_error($link)) {
                $tax_urls[] = new VisualRegressionUrl($link, ucwords($term->taxonomy) . ' archive');
            }
        }

        return $tax_urls;
    }

    /**
     * Get the default WooCommerce-specific URLs to check during visual comparison.
     *
     * @return array<VisualRegressionUrl>
     */
    protected function getDefaultWooCommerceUrls()
    {
        $pages = [
            'woocommerce_shop_page_id'      => 'Shop',
            'woocommerce_cart_page_id'      => 'Cart',
            'woocommerce_checkout_page_id'  => 'Checkout',
            'woocommerce_myaccount_page_id' => 'My Account',
        ];
        $urls  = [];

        foreach ($pages as $option => $name) {
            try {
                $page_id = intval(get_option($option, false));

                if (! $page_id) {
                    continue;
                }

                $urls[] = new VisualRegressionUrl(get_permalink($page_id) ?: '', $name);
            } catch (InvalidUrlException $e) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch
                // Skip over the URL.
            }
        }

        return $urls;
    }
}
