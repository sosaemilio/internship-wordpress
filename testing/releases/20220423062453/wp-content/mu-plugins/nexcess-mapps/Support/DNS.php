<?php

/**
 * Helper for working with DNS lookups.
 */

namespace Nexcess\MAPPS\Support;

class DNS {

	/**
	 * Get the IP address for a given domain.
	 *
	 * This is a wrapper around {@see gethostbyname()} with error handling.
	 *
	 * @param string $hostname The host name.
	 *
	 * @return string Either the IPv4 address or an empty string if the lookup failed.
	 */
	public function getIpByHost( $hostname ) {
		$hostname = trim( $hostname );
		$ip       = $this->callGetHostByName( $hostname );

		return $ip !== $hostname ? $ip : '';
	}

	/**
	 * Get DNS records for the given domain.
	 *
	 * This is a wrapper around {@see dns_get_record()} with error handling.
	 *
	 * @param string $hostname The DNS hostname.
	 * @param int    $type     Optional. The DNS record type. Default is DNS_ANY.
	 *
	 * @return array[] An array containing arrays representing records.
	 */
	public function getRecords( $hostname, $type = DNS_ANY ) {
		// Append a trailing period to avoid ambiguity.
		if ( '.' !== mb_substr( $hostname, -1, 1 ) ) {
			$hostname .= '.';
		}

		$records = $this->callDnsGetRecord( $hostname, $type );

		return is_array( $records ) ? $records : [];
	}

	/**
	 * Call dns_get_record() directly.
	 *
	 * This method directly proxies arguments to {@see dns_get_records()}, and only exists for
	 * the sake of testing.
	 *
	 * @param mixed ...$args
	 *
	 * @return array[]|bool
	 */
	protected function callDnsGetRecord( ...$args ) {
		return dns_get_record( ...$args );
	}

	/**
	 * Call {@see gethostbyname()} directly.
	 *
	 * @param string $hostname The host name.
	 *
	 * @return string Either the IPv4 address or the unmodified $hostname if the lookup failed.
	 */
	protected function callGetHostByName( $hostname ) {
		return gethostbyname( $hostname );
	}
}
