<?php

/**
 * This service is responsible for communicating with the Liquid Web / Nexcess
 * (Plugin) Telemetry API. It provides a mechanism to integrate and register
 * data to be sent to the API.
 */

namespace Nexcess\MAPPS\Services;

use Nexcess\MAPPS\Concerns\MakesHttpRequests;
use Nexcess\MAPPS\Exceptions\RequestException;
use Nexcess\MAPPS\Exceptions\WPErrorException;
use Nexcess\MAPPS\Settings;

use const Nexcess\MAPPS\PLUGIN_VERSION;

class Telemetry {
	use MakesHttpRequests;

	/**
	 * @var \Nexcess\MAPPS\Settings
	 */
	protected $settings;

	/**
	 * @param \Nexcess\MAPPS\Settings $settings
	 */
	public function __construct( Settings $settings ) {
		$this->settings = $settings;
	}

	/**
	 * Construct the full URI to an API route.
	 *
	 * @param string $route The API endpoint.
	 *
	 * @return string The absolute URI for this route.
	 */
	public function route( $route = '/' ) {
		// Strip leading slashes.
		if ( 0 === mb_strpos( $route, '/' ) ) {
			$route = mb_substr( $route, 1 );
		}

		return esc_url_raw( sprintf( '%s/api/%2$s', $this->settings->telemetry_reporter_endpoint, $route ) );
	}

	/**
	 * Send a Site Report to the Telemetry API.
	 *
	 * @param mixed[] $report The gathered telemetry report data.
	 *
	 * @throws RequestException If the request fails.
	 *
	 * @return bool
	 */
	public function sendReport( array $report ) {

		// If we don't have a key, the report won't send successfully.
		if ( empty( $this->settings->telemetry_key ) ) {
			return false;
		}

		// Add the key to the report in the method the Telemetry API is expecting.
		$report['key'] = $this->settings->telemetry_key;

		try {
			// We're not going to check for a response code because we are using
			// 'blocking' = false. This means the request is sent and we don't
			// wait for the response, so there will be nothing in it.
			$response = $this->request( 'site_report', [
				'blocking' => false,
				'method'   => 'POST',
				'timeout'  => 900,
				'body'     => wp_json_encode( $report ),
			] );
		} catch ( RequestException $e ) {
			throw $e;
		} catch ( \Exception $e ) {
			throw new RequestException( $e->getMessage(), $e->getCode(), $e );
		}

		return true;
	}

	/**
	 * Send a request to the Telemetry API.
	 *
	 * @param string  $endpoint The API endpoint.
	 * @param mixed[] $args     Optional. WP HTTP API arguments, which will be merged with defaults.
	 *                          {@link https://developer.wordpress.org/reference/classes/WP_Http/request/#parameters}.
	 *
	 * @throws WPErrorException If an error occurs making the request.
	 *
	 * @return Array<string,mixed> An array containing the following keys: 'headers', 'body', 'response', 'cookies',
	 *                             and 'filename'. This is the same as {@see \WP_HTTP::request()}
	 */
	protected function request( $endpoint, $args = [] ) {
		$response = wp_remote_request(
			$this->route( $endpoint ),
			array_replace_recursive([
				'headers'    => [
					'Accept'       => 'application/json',
					'Content-Type' => 'application/json',
				],
				'user-agent' => sprintf( 'NexcessMAPPS/%1$s', PLUGIN_VERSION ),
			], $args)
		);

		if ( is_wp_error( $response ) ) {
			throw new WPErrorException( $response );
		}

		return $response;
	}

}
