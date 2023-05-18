<?php

namespace StellarWP\PluginFramework\Support\Testing\Concerns;

use StellarWP\PluginFramework\Support\PHPVersions;

trait FiltersTestsByPHPVersion
{
    /**
     * Automatically skip a test if running PHP $version or higher.
     *
     * @param string $version The version to check against.
     * @param string $message
     */
    public function requiresAtLeastPHP($version, $message = '')
    {
        if (version_compare(PHP_VERSION, $version, '<')) {
            $this->markTestSkipped($message ?: sprintf('This test requires PHP %s or newer.', $version));
        }
    }

    /**
     * Automatically skip a test if running an EOL version of PHP.
     *
     * @param string $message
     */
    public function requiresCurrentPHP($message = '')
    {
        if (PHPVersions::hasReachedEOL(PHP_MAJOR_VERSION . '.' . PHP_MINOR_VERSION)) {
            $this->markTestSkipped($message ?: 'This test requires a current version of PHP.');
        }
    }

    /**
     * Automatically skip a test if *not* running PHP $version or lower.
     *
     * @param string $version The version to check against.
     * @param string $message
     */
    public function requiresLessThanPHP($version, $message = '')
    {
        if (version_compare(PHP_VERSION, $version, '>=')) {
            $this->markTestSkipped($message ?: sprintf('This test requires PHP %s or older.', $version));
        }
    }

    /**
     * Automatically skip a test if *not* running an EOL version of PHP.
     *
     * @param string $message
     */
    public function requiresOutdatedPHP($message = '')
    {
        if (! PHPVersions::hasReachedEOL(PHP_MAJOR_VERSION . '.' . PHP_MINOR_VERSION)) {
            $this->markTestSkipped($message ?: 'This test requires an outdated version of PHP.');
        }
    }
}
