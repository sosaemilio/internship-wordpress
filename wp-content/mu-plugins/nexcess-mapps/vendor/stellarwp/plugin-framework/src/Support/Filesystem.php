<?php

namespace StellarWP\PluginFramework\Support;

use StellarWP\PluginFramework\Exceptions\FilesystemException;
use StellarWP\PluginFramework\Exceptions\WPErrorException;
use WP_Filesystem_Base;

use function WP_Filesystem;

/**
 * Utilities for working with the WordPress filesystem.
 */
class Filesystem
{
    /**
     * Initialize the WordPress filesystem.
     *
     * This is a wrapper around {@see WP_Filesystem()}, but with built-in error handling and inclusion
     * of necessary files.
     *
     * @throws FilesystemException If anything goes wrong.
     * @throws WPErrorException    If WP_Filesystem() fails.
     *
     * @return WP_Filesystem_Base The initialized WP_Filesystem variant.
     */
    public static function init()
    {
        /** @var WP_Filesystem_Base $wp_filesystem */
        global $wp_filesystem;

        // We already have an instance, so return early.
        if (! empty($GLOBALS['wp_filesystem']) && $GLOBALS['wp_filesystem'] instanceof WP_Filesystem_Base) {
            return $GLOBALS['wp_filesystem'];
        }

        try {
            require_once ABSPATH . '/wp-admin/includes/file.php';

            $filesystem = WP_Filesystem();

            if (null === $filesystem) {
                throw new FilesystemException('The provided filesystem method is unavailable.');
            }

            if (false === $filesystem) {
                if ($wp_filesystem->errors->has_errors()) {
                    throw new WPErrorException($wp_filesystem->errors);
                }

                throw new FilesystemException('Unspecified failure.');
            }

            if (! is_object($wp_filesystem) || ! $wp_filesystem instanceof WP_Filesystem_Base) {
                throw new FilesystemException('The global $wp_filesystem is not an instance of WP_Filesystem_Base');
            }
        } catch (\Exception $e) {
            throw new FilesystemException(
                sprintf('There was an error initializing the WP_Filesystem class: %1$s', $e->getMessage()),
                $e->getCode(),
                $e
            );
        }

        return $wp_filesystem;
    }

    /**
     * Determine whether or not a file is a broken symlink.
     *
     * @param string $filepath The filepath to inspect.
     *
     * @return bool True if the file is a symlink with a missing target, false otherwise.
     */
    public static function isBrokenSymlink($filepath)
    {
        if (! is_link($filepath)) {
            return false;
        }

        return ! file_exists((string) readlink($filepath));
    }
}
