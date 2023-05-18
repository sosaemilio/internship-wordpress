<?php

namespace StellarWP\PluginFramework\Services;

use StellarWP\PluginFramework\Contracts\ManagesDomain;
use StellarWP\PluginFramework\Exceptions\RequestException;
use StellarWP\PluginFramework\Exceptions\ValidationException;
use StellarWP\PluginFramework\Services\Nexcess\MappsApiClient;
use StellarWP\PluginFramework\Settings;

/**
 * A service for working with DNS and domains.
 */
class Domain implements ManagesDomain
{
    /**
     * The Nexcess MAPPS API client.
     *
     * @var MappsApiClient
     */
    protected $client;

    /**
     * The Settings instance.
     *
     * @var Settings
     */
    protected $settings;

    /**
     * A cache of the current site's IP address.
     *
     * @var ?string
     */
    private $currentIpAddress;

    /**
     * Construct the Domain service object.
     *
     * @param Settings       $settings
     * @param MappsApiClient $client
     */
    public function __construct(Settings $settings, MappsApiClient $client)
    {
        $this->settings = $settings;
        $this->client   = $client;
    }

    /**
     * Change the domain name of the current site using the MAPPS API.
     *
     * @param string $domain          The new domain for the site.
     * @param bool   $skip_validation Optional. If true, skip validating DNS records for the domain.
     *                                Default is false.
     *
     * @throws RequestException    If an unexected response is returned from the MAPPS API.
     * @throws ValidationException If given an invalid domain name.
     *
     * @return bool True if the request was issued successfully.
     */
    public function changeDomain($domain, $skip_validation = false)
    {
        $domain = trim($domain);

        if (! $this->validateDomain($domain)) {
            throw new ValidationException(sprintf('"%s" is not a valid domain name', $domain));
        }

        if (! $skip_validation) {
            if (! $this->hasValidDnsRecords($domain)) {
                throw new ValidationException(sprintf(
                    'No valid DNS records were found for "%s" that point to this site',
                    $domain
                ));
            }
        }

        try {
            $this->client->setDomain($domain);
        } catch (RequestException $e) {
            throw new RequestException(
                sprintf('Received an unexpected response: %s', $e->getMessage()),
                $e->getCode(),
                $e
            );
        }

        return true;
    }

    /**
     * Determine if the given hostname has valid A, AAAA, and/or CNAME records that point to this site.
     *
     * @param string $hostname The hostname to inspect.
     *
     * @return bool True if at least one valid record exists, false otherwise.
     */
    public function hasValidDnsRecords($hostname)
    {
        $records = $this->getDnsRecords($hostname, DNS_A | DNS_AAAA | DNS_CNAME);

        foreach ($records as $record) {
            if (empty($record['type'])) {
                continue;
            }

            if ('A' === $record['type'] && ! empty($record['ip'])) {
                if ($this->getCurrentIpAddress() === $record['ip']) {
                    return true;
                }
            } elseif ('CNAME' === $record['type'] && ! empty($record['target'])) {
                if ($this->settings->temp_domain === $record['target']) {
                    return true;
                }
            }
        }

        // If we haven't returned yet, we don't have a match.
        return false;
    }

    /**
     * Get the IP address for the current site.
     *
     * @return ?string Either the IP address for the site null if it could not be determined.
     */
    protected function getCurrentIpAddress()
    {
        if (! isset($this->currentIpAddress)) {
            $ip = gethostbyname($this->settings->temp_domain);

            $this->currentIpAddress = $ip === $this->settings->temp_domain ? $ip : null;
        }

        return $this->currentIpAddress;
    }

    /**
     * Retrieve DNS records for the given hostname.
     *
     * This is a wrapper around {@see dns_get_record()} with error handling.
     *
     * @param string $hostname The DNS hostname.
     * @param int    $type     Optional. The DNS record type. Default is DNS_ANY.
     *
     * @return Array<int,Array<string,scalar>> An array containing arrays representing records.
     */
    protected function getDnsRecords($hostname, $type = DNS_ANY)
    {
        // Append a trailing period to avoid ambiguity.
        if ('.' !== mb_substr($hostname, -1, 1)) {
            $hostname .= '.';
        }

        $records = dns_get_record($hostname, $type);

        return is_array($records) ? $records : [];
    }

    /**
     * Validate a domain.
     *
     * This is a temporary work-around for the fact that FILTER_VALIDATE_DOMAIN does not exist in
     * PHP < 7.0; once PHP 5.6 support is dropped, this method should be removed in favor of simply
     * using `filter_var($domain, FILTER_VALIDATE_DOMAIN)`.
     *
     * @todo Remove this method once PHP 5.6 support has been dropped.
     *
     * @param string $domain The domain to validate.
     *
     * @return bool True if the domain is semantically valid, false otherwise.
     */
    private function validateDomain($domain)
    {
        return defined('FILTER_VALIDATE_DOMAIN')
            // phpcs:ignore PHPCompatibility.Constants.NewConstants.filter_validate_domainFound
            ? (bool) filter_var($domain, FILTER_VALIDATE_DOMAIN)
            : (bool) filter_var($domain, FILTER_VALIDATE_URL);
    }
}
