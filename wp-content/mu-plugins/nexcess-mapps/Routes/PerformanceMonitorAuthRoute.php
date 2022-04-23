<?php

/**
 * The `PeformanceMonitorRoute` returns data used to initialize
 * the UI React App and fill the timeline with a page of results.
 */

namespace Nexcess\MAPPS\Routes;

use Nexcess\MAPPS\Integrations\PerformanceMonitor\Api;

class PerformanceMonitorAuthRoute extends RestRoute {

	/**
	 * Supported HTTP methods for this route.
	 *
	 * @var string[]
	 */
	protected $methods = [
		'GET',
	];

	/**
	 * The REST route.
	 *
	 * @var string
	 */
	protected $route = '/performance-monitor/auth';

	/**
	 * @var \Nexcess\MAPPS\Integrations\PerformanceMonitor\Api
	 */
	protected $api;

	/**
	 * Constructor.
	 *
	 * @param \Nexcess\MAPPS\Integrations\PerformanceMonitor\Api $api Instance of the Api class.
	 */
	public function __construct( Api $api ) {
		$this->api = $api;
	}

	/**
	 * Determine whether or not the current request is authorized.
	 *
	 * This corresponds to the "permission_callback" argument within the WP REST API.
	 *
	 * @link https://developer.wordpress.org/rest-api/extending-the-rest-api/adding-custom-endpoints/#permissions-callback
	 *
	 * @param \WP_REST_Request $request The REST request.
	 *
	 * @return bool|\WP_Error
	 */
	public function authorizeRequest( \WP_REST_Request $request ) {
		$bearer_token = self::getBearerToken( $request );

		if ( is_string( $bearer_token ) ) {
			return $this->api->verifyToken( $bearer_token );
		}
		return $bearer_token;
	}

	/**
	 * Always return `true` for authorized requests.
	 *
	 * @param \WP_REST_Request $request The request object.
	 *
	 * @return mixed
	 */
	public function handleRequest( \WP_REST_Request $request ) {
		$token      = self::getBearerToken( $request );
		$route_urls = $this->api->getRouteUrls();
		$hash       = '';

		if ( is_string( $token ) && ! empty( $route_urls['authCallback'] ) ) {
			$hash = hash_hmac( 'sha256', $route_urls['authCallback'], $token );
		}

		return [
			'hash' => $hash,
		];
	}
}
