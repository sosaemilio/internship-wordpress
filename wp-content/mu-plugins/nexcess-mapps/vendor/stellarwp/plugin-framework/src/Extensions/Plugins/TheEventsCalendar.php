<?php

namespace StellarWP\PluginFramework\Extensions\Plugins;

use StellarWP\PluginFramework\Extensions\Concerns\ProvidesPageCacheExclusions;
use StellarWP\PluginFramework\Extensions\Contracts\HasPageCacheExclusions;

/**
 * Plugin configuration for The Events Calendar by StellarWP.
 *
 * @link https://theeventscalendar.com/
 */
class TheEventsCalendar extends PluginConfig implements HasPageCacheExclusions
{
    use ProvidesPageCacheExclusions;

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
            'events/',
        ];
    }
}
