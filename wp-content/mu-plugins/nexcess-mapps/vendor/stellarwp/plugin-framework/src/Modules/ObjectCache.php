<?php

/**
 * Object Cache module for Plugin Framework.
 */

namespace StellarWP\PluginFramework\Modules;

class ObjectCache extends Module
{
    /**
     * Perform any necessary setup for the module.
     *
     * This method is automatically called as part of Plugin::load_modules(), and is the
     * entry-point for all modules.
     *
     * @return void
     */
    public function setup()
    {
        add_filter('site_status_persistent_object_cache_url', [ $this, 'siteStatusPersistentObjectCacheUrl' ]);
    }

    /**
     * Filter the Persistent object cache URL.
     * Replace the original WordPress guide with our own.
     *
     * @return string URL to object cache guide.
     */
    public function siteStatusPersistentObjectCacheUrl()
    {
        /**
         * Allow this to be overwritten so other brands can link to their own resources.
         */
        return apply_filters(
            'stellarwp_hosting_persistent_object_cache_url',
            'https://www.nexcess.net/help/enabling-redis-object-caching/'
        );
    }
}
