<?php

namespace StellarWP\PluginFramework\Support\Testing\Concerns;

use StellarWP\PluginFramework\Exceptions\ConfigurationException;

/**
 * Helpers and assertions for testing full-page caching solutions.
 *
 * Note that this trait assumes that implementing test classes are populating the `$cookieExclusions`,
 * `$pagePathExclusions`, and `$queryParamExclusions` as part of their setup.
 */
trait TestsPageCacheExclusions
{
    /**
     * A cache of the compiled regex for cookie exclusions.
     *
     * @var string
     */
    protected $cookieExclusions;

    /**
     * A cache of the compiled regex for page path exclusions.
     *
     * @var string
     */
    protected $pagePathExclusions;

    /**
     * A cache of the compiled regex for query param exclusions.
     *
     * @var string
     */
    protected $queryParamExclusions;

    /**
     * Assert that the presence of the given cookie would prevent the page from being cached.
     *
     * @param string $cookie  The cookie to be checked.
     * @param string $message Optional. Additional context should the assertion fail. Default is empty.
     *
     * @return $this
     */
    protected function assertCookieExcludedFromPageCache($cookie, $message = '')
    {
        $pattern = $this->getCookieExclusions();

        if (empty($message)) {
            $message = sprintf(
                'Based on current settings, cookie "%s" would not prevent the page from being cached.',
                $cookie
            );
        }

        $this->assertNotEmpty($pattern, 'The pattern for matching cookie exclusions should not be empty.');
        $this->assertMatchesRegularExpression($pattern, $cookie, $message);

        return $this;
    }

    /**
     * Assert that the presence of the given cookie would not prevent the page from being cached.
     *
     * @param string $cookie  The cookie to be checked.
     * @param string $message Optional. Additional context should the assertion fail. Default is empty.
     *
     * @return $this
     */
    protected function assertCookieNotExcludedFromPageCache($cookie, $message = '')
    {
        $pattern = $this->getCookieExclusions();

        if (empty($message)) {
            $message = sprintf(
                'Based on current settings, cookie "%s" would prevent the page from being cached.',
                $cookie
            );
        }

        $this->assertNotEmpty($pattern, 'The pattern for matching cookie exclusions should not be empty.');
        $this->assertDoesNotMatchRegularExpression($pattern, $cookie, $message);

        return $this;
    }

    /**
     * Assert that the given path would be excluded from the page cache based on the current settings.
     *
     * @param string $path    The path to be checked.
     * @param string $message Optional. Additional context should the assertion fail. Default is empty.
     *
     * @return $this
     */
    protected function assertPathExcludedFromPageCache($path, $message = '')
    {
        $pattern = $this->getPagePathExclusions();

        if (empty($message)) {
            $message = sprintf(
                'Based on current settings, path "%s" would not be excluded from the page cache.',
                $path
            );
        }

        $this->assertNotEmpty($pattern, 'The pattern for matching page path exclusions should not be empty.');
        $this->assertMatchesRegularExpression($pattern, $path, $message);

        return $this;
    }

    /**
     * Assert that the given path would not be excluded from the page cache based on the current settings.
     *
     * @param string $path    The path to be checked.
     * @param string $message Optional. Additional context should the assertion fail. Default is empty.
     *
     * @return $this
     */
    protected function assertPathNotExcludedFromPageCache($path, $message = '')
    {
        $pattern = $this->getPagePathExclusions();

        if (empty($message)) {
            $message = sprintf(
                'Based on current settings, path "%s" would be excluded from the page cache.',
                $path
            );
        }

        $this->assertNotEmpty($pattern, 'The pattern for matching page path exclusions should not be empty.');
        $this->assertDoesNotMatchRegularExpression($pattern, $path, $message);

        return $this;
    }

    /**
     * Assert that the given query param would be excluded from the page cache based on the current settings.
     *
     * @param string $query   The query string to be checked.
     * @param string $message Optional. Additional context should the assertion fail. Default is empty.
     *
     * @return $this
     */
    protected function assertQueryStringExcludedFromPageCache($query, $message = '')
    {
        $pattern = $this->getQueryParamExclusions();

        if (empty($message)) {
            $message = sprintf(
                'Based on current settings, the query string "%s" would not be excluded from the page cache.',
                $query
            );
        }

        $this->assertNotEmpty($pattern, 'The pattern for matching query param exclusions should not be empty.');
        $this->assertMatchesRegularExpression($pattern, $query, $message);

        return $this;
    }

    /**
     * Assert that the given query param would be excluded from the page cache based on the current settings.
     *
     * @param string $query   The query string to be checked.
     * @param string $message Optional. Additional context should the assertion fail. Default is empty.
     *
     * @return $this
     */
    protected function assertQueryStringNotExcludedFromPageCache($query, $message = '')
    {
        $pattern = $this->getQueryParamExclusions();

        if (empty($message)) {
            $message = sprintf(
                'Based on current settings, the query string "%s" would be excluded from the page cache.',
                $query
            );
        }

        $this->assertNotEmpty($pattern, 'The pattern for matching query param exclusions should not be empty.');
        $this->assertDoesNotMatchRegularExpression($pattern, $query, $message);

        return $this;
    }

    /**
     * Retrieve the pattern for matching cookie exclusions.
     *
     * @throws ConfigurationException If the $cookieExclusions property has not been initialized.
     *
     * @return string
     */
    protected function getCookieExclusions()
    {
        if (! isset($this->cookieExclusions)) {
            throw new ConfigurationException(sprintf(
                'Expected %s::$cookieExclusions property to have been populated.',
                static::class
            ));
        }

        return $this->cookieExclusions;
    }

    /**
     * Retrieve the pattern for matching page path exclusions.
     *
     * @throws ConfigurationException If the $pagePathExclusions property has not been initialized.
     *
     * @return string
     */
    protected function getPagePathExclusions()
    {
        if (! isset($this->pagePathExclusions)) {
            throw new ConfigurationException(sprintf(
                'Expected %s::$pagePathExclusions property to have been populated.',
                static::class
            ));
        }

        return $this->pagePathExclusions;
    }

    /**
     * Retrieve the pattern for matching query param exclusions.
     *
     * @throws ConfigurationException If the $queryParamExclusions property has not been initialized.
     *
     * @return string
     */
    protected function getQueryParamExclusions()
    {
        if (! isset($this->queryParamExclusions)) {
            throw new ConfigurationException(sprintf(
                'Expected %s::$queryParamExclusions property to have been populated.',
                static::class
            ));
        }

        return $this->queryParamExclusions;
    }
}
