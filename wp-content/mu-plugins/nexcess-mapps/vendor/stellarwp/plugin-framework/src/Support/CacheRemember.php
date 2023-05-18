<?php

/**
 * Helpers for dealing with Cache compatability.
 *
 * Original Source
 * https://github.com/stevegrunwell/wp-cache-remember
 *
 * Included directly due to incompatabilities between multiple libraries defining `wp_cache_remember` functions.
 *
 * Copyright 2018 Steve Grunwell
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy of this software and associated
 * documentation files (the "Software"), to deal in the Software without restriction, including without limitation
 * the rights to use, copy, modify, merge, publish, distribute, sublicense, and/or sell copies of the Software, and
 * to permit persons to whom the Software is furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in all copies or substantial portions of
 * the Software. THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR IMPLIED, INCLUDING BUT
 * NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT
 * SHALL THE AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY, WHETHER IN AN ACTION OF
 * CONTRACT, TORT OR OTHERWISE, ARISING FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
 * THE SOFTWARE.
 */

namespace StellarWP\PluginFramework\Support;

class CacheRemember
{
    /**
     * Retrieve a value from the object cache. If it doesn't exist, run the $callback to generate and
     * cache the value.
     *
     * @param string   $key      The cache key.
     * @param callable $callback The callback used to generate and cache the value.
     * @param string   $group    Optional. The cache group. Default is empty.
     * @param int      $expire   Optional. The number of seconds before the cache entry should expire.
     *                           Default is 0 (as long as possible).
     *
     * @return mixed The value returned from $callback, pulled from the cache when available.
     */
    public static function wpCacheRemember($key, $callback, $group = '', $expire = 0)
    {
        $found  = false;
        $cached = wp_cache_get($key, $group, false, $found);

        if (false !== $found) {
            return $cached;
        }

        $value = $callback();

        if (! is_wp_error($value)) {
            wp_cache_set($key, $value, $group, $expire);
        }

        return $value;
    }

    /**
     * Retrieve and subsequently delete a value from the object cache.
     *
     * @param string $key     The cache key.
     * @param string $group   Optional. The cache group. Default is empty.
     * @param mixed  $default Optional. The default value to return if the given key doesn't
     *                        exist in the object cache. Default is null.
     *
     * @return mixed The cached value, when available, or $default.
     */
    public static function wpCacheForget($key, $group = '', $default = null)
    {
        $found  = false;
        $cached = wp_cache_get($key, $group, false, $found);

        if (false !== $found) {
            wp_cache_delete($key, $group);

            return $cached;
        }

        return $default;
    }

    /**
     * Retrieve a value from transients. If it doesn't exist, run the $callback to generate and
     * cache the value.
     *
     * @param string   $key      The transient key.
     * @param callable $callback The callback used to generate and cache the value.
     * @param int      $expire   Optional. The number of seconds before the cache entry should expire.
     *                           Default is 0 (as long as possible).
     *
     * @return mixed The value returned from $callback, pulled from transients when available.
     */
    public static function rememberTransient($key, $callback, $expire = 0)
    {
        $cached = get_transient($key);

        if (false !== $cached) {
            return $cached;
        }

        $value = $callback();

        if (! is_wp_error($value)) {
            set_transient($key, $value, $expire);
        }

        return $value;
    }

    /**
     * Retrieve and subsequently delete a value from the transient cache.
     *
     * @param string $key     The transient key.
     * @param mixed  $default Optional. The default value to return if the given key doesn't
     *                        exist in transients. Default is null.
     *
     * @return mixed The cached value, when available, or $default.
     */
    public static function forgetTransient($key, $default = null)
    {
        $cached = get_transient($key);

        if (false !== $cached) {
            delete_transient($key);

            return $cached;
        }

        return $default;
    }

    /**
     * Retrieve a value from site transients. If it doesn't exist, run the $callback to generate
     * and cache the value.
     *
     * @param string   $key      The site transient key.
     * @param callable $callback The callback used to generate and cache the value.
     * @param int      $expire   Optional. The number of seconds before the cache entry should expire.
     *                           Default is 0 (as long as possible).
     *
     * @return mixed The value returned from $callback, pulled from transients when available.
     */
    public static function rememberSiteTransient($key, $callback, $expire = 0)
    {
        $cached = get_site_transient($key);

        if (false !== $cached) {
            return $cached;
        }

        $value = $callback();

        if (! is_wp_error($value)) {
            set_site_transient($key, $value, $expire);
        }

        return $value;
    }

    /**
     * Retrieve and subsequently delete a value from the site transient cache.
     *
     * @param string $key     The site transient key.
     * @param mixed  $default Optional. The default value to return if the given key doesn't
     *                        exist in the site transients. Default is null.
     *
     * @return mixed The cached value, when available, or $default.
     */
    public static function forgetSiteTransient($key, $default = null)
    {
        $cached = get_site_transient($key);

        if (false !== $cached) {
            delete_site_transient($key);

            return $cached;
        }

        return $default;
    }
}
