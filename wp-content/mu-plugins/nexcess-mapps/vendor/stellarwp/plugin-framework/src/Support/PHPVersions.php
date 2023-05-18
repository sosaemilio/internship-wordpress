<?php

namespace StellarWP\PluginFramework\Support;

use DateTimeImmutable;
use DateTimeInterface;
use Exception;
use StellarWP\PluginFramework\Exceptions\InvalidPHPVersionException;

class PHPVersions
{
    /**
     * An array of all (supported) PHP versions and their EOL dates.
     *
     * @link https://www.php.net/supported-versions
     *
     * @var array<string,string|DateTimeImmutable>
     */
    private static $versions = [
        '5.6' => '2018-12-31',
        '7.0' => '2019-01-10',
        '7.1' => '2019-12-01',
        '7.2' => '2020-11-30',
        '7.3' => '2021-12-06',
        '7.4' => '2022-11-28',
        '8.0' => '2023-11-26',
        '8.1' => '2024-11-25',
    ];

    /**
     * Test whether the current PHP version is supported.
     *
     * @param string            $version The version number in MAJOR.MINOR format.
     * @param DateTimeInterface $date    The date the version reached EOL.
     *
     * @return bool True if the PHP version has reached EOL, false otherwise.
     */
    public static function hasReachedEOL($version, DateTimeInterface $date = null)
    {
        try {
            $eol = self::getEOLDate($version);
        } catch (Exception $e) {
            return false;
        }

        return ($date ?: current_datetime()) >= $eol;
    }

    /**
     * Retrieve the EOL date for the given PHP version.
     *
     * @param string $version The PHP version.
     *
     * @throws InvalidPHPVersionException If the PHP version is undefined.
     *
     * @return DateTimeImmutable An object representing the PHP version's EOL date.
     */
    public static function getEOLDate($version)
    {
        if (! isset(self::$versions[ $version ])) {
            throw new InvalidPHPVersionException(sprintf(
                'No EOL date has been set for PHP version "%1$s".',
                $version
            ));
        }

        if (self::$versions[ $version ] instanceof DateTimeImmutable) {
            return self::$versions[ $version ];
        }

        if (! is_string(self::$versions[ $version ])) {
            return new DateTimeImmutable();
        }

        self::$versions[ $version ] = new DateTimeImmutable(self::$versions[ $version ]);

        return self::$versions[ $version ];
    }
}
