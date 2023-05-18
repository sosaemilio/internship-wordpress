<?php

namespace StellarWP\PluginFramework\Contracts;

use StellarWP\PluginFramework\Exceptions\RequestException;
use StellarWP\PluginFramework\Exceptions\ValidationException;

interface ManagesDomain
{
    /**
     * Change the domain name of the current site using the MAPPS API.
     *
     * @param string $domain The new domain for the site.
     *
     * @throws RequestException    If an unexected response is returned from the MAPPS API.
     * @throws ValidationException If given an invalid domain name.
     *
     * @return bool True if the request was issued successfully.
     */
    public function changeDomain($domain);

    /**
     * Determine if the given hostname has valid A, AAAA, and/or CNAME records that point to this site.
     *
     * @param string $hostname The hostname to inspect.
     *
     * @return bool True if at least one valid record exists, false otherwise.
     */
    public function hasValidDnsRecords($hostname);
}
