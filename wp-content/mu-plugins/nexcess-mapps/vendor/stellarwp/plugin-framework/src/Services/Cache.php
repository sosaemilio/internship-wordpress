<?php

namespace StellarWP\PluginFramework\Services;

use Psr\Log\LoggerInterface;
use StellarWP\PluginFramework\Exceptions\RequestException;
use StellarWP\PluginFramework\Services\Nexcess\MappsApiClient;
use wpdb;

/**
 * A wrapper around purging various caches.
 */
class Cache
{
    /**
     * The MAPPS API client.
     *
     * @var MappsApiClient
     */
    protected $client;

    /**
     * The Logger instance.
     *
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * The action hook that is fired when purging database transients.
     */
    const ACTION_PURGE_DATABASE_TRANSIENTS = 'StellarWP\\Cache\\PurgePageCache';

    /**
     * The action hook that is fired when flushing the object cache.
     */
    const ACTION_PURGE_OBJECT_CACHE = 'StellarWP\\Cache\\PurgeObjectCache';

    /**
     * The action hook that is fired when purging the page cache.
     */
    const ACTION_PURGE_PAGE_CACHE = 'StellarWP\\Cache\\PurgePageCache';

    /**
     * The action hook that is fired when purging the system cache(s).
     */
    const ACTION_PURGE_SYSTEM_CACHE = 'StellarWP\\Cache\\PurgePageCache';

    /**
     * Construct a new instance of the Cache service.
     *
     * @param MappsApiClient  $client The MAPPS API client.
     * @param LoggerInterface $logger The logger instance.
     */
    public function __construct(MappsApiClient $client, LoggerInterface $logger)
    {
        $this->client = $client;
        $this->logger = $logger;
    }

    /**
     * Purge all known caches.
     *
     * @return $this
     */
    public function purgeAll()
    {
        $this->purgeObjectCache();
        $this->purgePageCache();
        $this->purgePlatformCache();
        $this->purgeDatabaseTransients();

        return $this;
    }

    /**
     * Purge all transients from the database.
     *
     * When an external object cache (Redis, Memcached, etc.) is used, transients will be stored in
     * the external cache, not in the database. As such, this method should generally be chained with
     * $this->purgeObjectCache() if you want to ensure that all transients are cleared.
     *
     * @return bool True if database transients were purged (or were not present), false otherwise.
     */
    public function purgeDatabaseTransients()
    {
        /** @var wpdb $wpdb */
        global $wpdb;

        $rows = $wpdb->query($wpdb->prepare(
            // @phpstan-ignore-next-line
            "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_%'"
        ));

        if (false === $rows) {
            return false;
        }

        do_action(self::ACTION_PURGE_DATABASE_TRANSIENTS);

        return true;
    }

    /**
     * Purge the object cache (e.g. Redis).
     *
     * @return bool True if the object cache was flushed successfully, false otherwise.
     */
    public function purgeObjectCache()
    {
        $flushed = wp_cache_flush();

        do_action(self::ACTION_PURGE_OBJECT_CACHE);

        return $flushed;
    }

    /**
     * Purge the page cache.
     *
     * Since this will vary heavily between page caching solutions, we'll look for known cache-
     * clearing methods and invoke them if found.
     *
     * @return bool True unless an error is emitted from a page cache plugin.
     */
    public function purgePageCache()
    {
        try {
            do_action(self::ACTION_PURGE_PAGE_CACHE);
        } catch (\Exception $e) {
            $this->logger->error(sprintf('Caught exception while trying to clear page cache: %s', $e->getMessage()));

            return false;
        }

        return true;
    }

    /**
     * Purge all caches owned by the hosting platform.
     *
     * This will generally include the nginx microcache, CDN cache(s), and portions of the PHP OPcache.
     *
     * @return bool True if the purge request was accepted, false otherwise.
     */
    public function purgePlatformCache()
    {
        try {
            $this->client->purgeCaches();
        } catch (RequestException $e) {
            $this->logger->warning(sprintf(
                'Received an error attempting to purge system cache: %s',
                $e->getMessage()
            ));

            return false;
        }

        do_action(self::ACTION_PURGE_SYSTEM_CACHE);

        return true;
    }
}
