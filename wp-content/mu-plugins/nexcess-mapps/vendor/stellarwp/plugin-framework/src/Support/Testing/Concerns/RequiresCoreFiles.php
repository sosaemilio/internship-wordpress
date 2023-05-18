<?php

namespace StellarWP\PluginFramework\Support\Testing\Concerns;

use StellarWP\PluginFramework\Exceptions\FilesystemException;

/**
 * Automatically call require_once on WordPress core files that are needed for this test class.
 *
 * In order to implement this, add the following to your test class:
 *
 *     protected static $requiredFiles = [
 *         WP_INC . '/some-file.php',
 *     ];
 *
 * @property Array<int,string> $requiredFiles Files, relative to ABSPATH, which should be included before executing
 *                                            this test class.
 */
trait RequiresCoreFiles
{
    /**
     * @beforeClass
     *
     * @throws FilesystemException If the required file is not readable.
     */
    public static function loadRequiredFiles()
    {
        foreach (self::$requiredFiles as $file) {
            $path = ABSPATH . $file;

            if (! is_readable($path)) {
                throw new FilesystemException(sprintf('Unable to load required file %s', $path));
            }

            require_once $path;
        }
    }
}
