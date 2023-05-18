<?php

namespace StellarWP\PluginFramework\Console\Concerns;

/**
 * This trait is used to find (and cache) the proper WP-CLI binary for the site's current PHP version.
 *
 * For other shell executables, {@see HasShellDependencies}.
 */
trait InteractsWithWpCli
{
    /**
     * The full system path to the WP-CLI binary.
     *
     * @var string
     */
    private static $wpBinary;

    /**
     * Retrieve the PHP + WP-CLI binary combination we want to use while running WP-CLI.
     *
     * @return string The system path to a PHP binary.
     */
    protected function getWpBinary()
    {
        if (self::$wpBinary) {
            return self::$wpBinary;
        }

        /*
         * Construct an escaped string that expands the current PHP and WP-CLI binary paths.
         *
         * Note that we're using the PHP_BINDIR constant and adding "/php" instead of PHP_BINARY,
         * as the latter will point to PHP-FPM.
         *
         * The expected output of this will look something like:
         *
         *     /opt/remi/php73/root/usr/bin/php /usr/local/bin/wp
         */
        self::$wpBinary = sprintf(
            '%1$s %2$s',
            escapeshellarg(PHP_BINDIR . '/php'),
            // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.system_calls_shell_exec
            escapeshellarg(trim((string) shell_exec('command -v wp')))
        );

        return self::$wpBinary;
    }
}
