<?php

namespace StellarWP\PluginFramework\Support\Testing\Concerns;

trait FiltersTestsByWordPressVersion
{
    /**
     * Automatically skip a test if running at least WordPress $version.
     *
     * @param string $version The version to check against.
     * @param string $message
     */
    public function requiresAtLeastWordPress($version, $message = '')
    {
        if (! $this->siteIsAtLeastWordPressVersion($version)) {
            $this->markTestSkipped($message ?: sprintf('This test requires WordPress >= %1$s.', $version));
        }
    }

    /**
     * Automatically skip a test if *not* running at least WordPress $version.
     *
     * @param string $version The version to check against.
     * @param string $message
     */
    public function requiresLessThanWordPress($version, $message = '')
    {
        if ($this->siteIsAtLeastWordPressVersion($version)) {
            $this->markTestSkipped($message ?: sprintf('This test requires WordPress < %1$s.', $version));
        }
    }

    /**
     * Determine if the current version of WordPress is at least $version.
     *
     * @param string $version The version to check against.
     *
     * @return bool
     */
    public function siteIsAtLeastWordPressVersion($version)
    {
        return $this->currentWordPressVersionIs('>=', $version);
    }

    /**
     * Check the WordPress version.
     *
     * @param string $operator The operator to use.
     * @param string $version  The version to check against.
     *
     * @return bool
     */
    public function currentWordPressVersionIs($operator, $version)
    {
        return version_compare($this->getNormalizedWordPressVersion(), $version, $operator);
    }

    /**
     * Normalize the WordPress core release number.
     *
     * Stable WordPress releases are in x.y.z format, but can have pre-release versions,
     * e.g. "5.4-RC4-47505-src".
     *
     * We want, for example. 5.4-RC4-47505-src to be considered equal to 5.4, so strip out
     * the pre-release portion.
     *
     * @return string WordPress version.
     */
    public function getNormalizedWordPressVersion()
    {
        global $wp_version;

        return preg_replace('/-.+$/', '', $wp_version);
    }
}
