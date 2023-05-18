<?php

namespace StellarWP\PluginFramework\Extensions\Contracts;

/**
 * Indicates that an extensions has paths, cookies, etc. that should be excluded from page caching solutions.
 */
interface HasPageCacheExclusions
{
    /**
     * Retrieve a list of cookie prefixes that should be excluded from the page cache by default.
     *
     * @return Array<string> Cookie prefixes that should be excluded.
     */
    public function getPageCacheCookieExclusions();

    /**
     * Retrieve an array of paths that should be excluded by default from page caching solutions.
     *
     * All paths are assumed to be relative to the site root (e.g. "/") and will be treated as a
     * path prefix (e.g. "posts/" will match "/posts/*").
     *
     * @return Array<string> The path prefixes to exclude.
     */
    public function getPageCachePathExclusions();

    /**
     * Retrieve a list of query string parameters that should exclude a page from being cached.
     *
     * @return Array<string> Query string parameter names.
     */
    public function getPageCacheQueryParamExclusions();
}
