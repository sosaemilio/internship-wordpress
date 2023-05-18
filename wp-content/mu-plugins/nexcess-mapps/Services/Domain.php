<?php

namespace Nexcess\MAPPS\Services;

use StellarWP\PluginFramework\Support\Branding;
use Tribe\WME\Sitebuilder\Contracts\ManagesDomain;
use Tribe\WME\Sitebuilder\Services\Domain as BaseDomainService;
use WP_Error;

class Domain extends BaseDomainService implements ManagesDomain {

	/**
	 * @var MappsApiClient
	 */
	protected $client;

	/**
	 * Construct the integration.
	 *
	 * @param MappsApiClient $client
	 */
	public function __construct( MappsApiClient $client ) {
		$this->client = $client;
	}

	/**
	 * Make a request to change the domain of the site.
	 *
	 * @param string $domain
	 *
	 * @return true|WP_Error
	 */
	public function renameDomain( $domain ) {
		if ( empty( $domain ) ) {
			return new WP_Error(
				'mapps-change-domain-failure',
				__( 'Unable to update the site with an empty domain.', 'nexcess-mapps' )
			);
		}

		try {
			$this->client->renameDomain( $domain );
		} catch ( \Exception $e ) {
			return new WP_Error(
				'mapps-change-domain-failure',
				sprintf(
				/* Translators: %1$s is the branded company name, %2$s is the API error message. */
					__( 'The %1$s API returned an error: %2$s', 'nexcess-mapps' ),
					Branding::getCompanyName(),
					$e->getMessage()
				)
			);
		}

		return true;
	}

	/**
	 * Confirm the domain is usable for the site.
	 *
	 * @param string $domain
	 *
	 * @return array Data indicating the various states of validation checks, or an empty array if unsuccessful.
	 */
	public function isDomainUsable( $domain ) {
		if ( empty( $domain ) ) {
			return [];
		}

		try {
			$response = $this->client->checkDomainUsable( $domain );

			$data = [
				'domain' => $domain,
			];

			// Return all properties from response body.
			foreach ( $response as $prop => $value ) {
				$data[ $prop ] = $value;
			}

			return $data;
		} catch ( \Exception $e ) {
			return [];
		}
	}

	/**
	 * Search available domains based on provided domain name.
	 *
	 * @param string $domain
	 *
	 * @return array|WP_Error
	 */
	public function searchAvailableDomains( $domain ) {
		if ( empty( $domain ) ) {
			return new WP_Error(
				'mapps-search-domain-empty',
				__( 'Search domain provided is empty.', 'nexcess-mapps' )
			);
		}

		try {
			return $this->client->searchAvailableDomains( $domain );
		} catch ( \Exception $e ) {
			return new WP_Error(
				'mapps-search-domain-failure',
				sprintf(
				/* Translators: %1$s is the branded company name, %2$s is the API error message. */
					__( 'The %1$s API returned an error: %2$s', 'nexcess-mapps' ),
					Branding::getCompanyName(),
					$e->getMessage()
				)
			);
		}
	}

	/**
	 * Create purchase flow on Nexcess.
	 *
	 * @param array[] $domains
	 * @param string  $return_url
	 * @param string  $callback_url
	 * @param string  $abort_url
	 *
	 * @return array|WP_Error
	 */
	public function createPurchaseFlow( $domains, $return_url, $callback_url, $abort_url = '' ) {
		if ( empty( $domains ) || empty( $return_url ) || empty( $callback_url ) ) {
			return new WP_Error(
				'mapps-create-purchase-flow-empty-params',
				__( 'One or more of the required parameters are empty.', 'nexcess-mapps' )
			);
		}

		try {
			return $this->client->createPurchaseFlow( $domains, $return_url, $callback_url, $abort_url );
		} catch ( \Exception $e ) {
			return new WP_Error(
				'mapps-create-purchase-flow-failure',
				sprintf(
				/* Translators: %1$s is the branded company name, %2$s is the API error message. */
					__( 'The %1$s API returned an error: %2$s', 'nexcess-mapps' ),
					Branding::getCompanyName(),
					$e->getMessage()
				)
			);
		}
	}
}
