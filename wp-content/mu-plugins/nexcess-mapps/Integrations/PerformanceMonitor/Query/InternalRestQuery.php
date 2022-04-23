<?php

namespace Nexcess\MAPPS\Integrations\PerformanceMonitor\Query;

/**
 * The `InternalRestQuery` class is responsible for making an internal
 * REST API request.
 */
class InternalRestQuery {

	/**
	 * Last set of response headers returned by the REST API.
	 *
	 * @var Array<mixed>
	 */
	protected $lastHeaders = [];

	/**
	 * Makes an internal request using the REST API.
	 *
	 * @param string       $endpoint
	 * @param string       $method
	 * @param Array<mixed> $params
	 *
	 * @return Array<mixed>
	 */
	public function request( $endpoint, $method = 'GET', $params = [] ) {
		$request = new \WP_REST_Request( $method, $endpoint );
		$request->set_query_params( $params );

		$response = rest_do_request( $request );
		$server   = rest_get_server();

		$response_headers = $response->get_headers();
		$response_data    = $server->response_to_data( $response, false );

		$this->lastHeaders = $response_headers;

		return $response_data;
	}

	/**
	 * Returns the last set of HTTP headers returned
	 * by the REST API.
	 */
	public function getLastHeaders() {
		return $this->lastHeaders;
	}
}
