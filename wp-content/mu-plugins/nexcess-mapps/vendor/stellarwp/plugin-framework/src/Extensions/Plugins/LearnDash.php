<?php

namespace StellarWP\PluginFramework\Extensions\Plugins;

use StellarWP\PluginFramework\Extensions\Concerns\ProvidesPageCacheExclusions;
use StellarWP\PluginFramework\Extensions\Contracts\HasPageCacheExclusions;

/**
 * Plugin configuration for LearnDash by StellarWP.
 *
 * @link https://www.learndash.com/
 */
class LearnDash extends PluginConfig implements HasPageCacheExclusions
{
    use ProvidesPageCacheExclusions;

    /**
     * Retrieve a list of cookie prefixes that should be excluded from the page cache by default.
     *
     * @return Array<string> Cookie prefixes that should be excluded.
     */
    public function getPageCacheCookieExclusions()
    {
        return [
            'learndash_',
            'learndash-',
            'ld_',
            'ldadv-',
        ];
    }

    /**
     * Retrieve an array of paths that should be excluded by default from page caching solutions.
     *
     * All paths are assumed to be relative to the site root (e.g. "/") and will be treated as a
     * path prefix (e.g. "posts/" will match "/posts/*").
     *
     * @return Array<string> The path prefixes to exclude.
     */
    public function getPageCachePathExclusions()
    {
        return [
            'courses',
            'groups',
            'lessons',
            'quizzes',
            'sfwd-',
            'topic/',
        ];
    }
}
