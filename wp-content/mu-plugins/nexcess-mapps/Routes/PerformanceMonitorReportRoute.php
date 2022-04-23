<?php

/**
 * The `PeformanceMonitorRoute` returns data used to initialize
 * the UI React App and fill the timeline with a page of results.
 */

namespace Nexcess\MAPPS\Routes;

use Nexcess\MAPPS\Integrations\PerformanceMonitor\Api;
use WP_Error;

class PerformanceMonitorReportRoute extends RestRoute {

	/**
	 * Supported HTTP methods for this route.
	 *
	 * @var string[]
	 */
	protected $methods = [
		'POST',
	];

	/**
	 * The REST route.
	 *
	 * @var string
	 */
	protected $route = '/performance-monitor/report';

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
	 * The primary callback to execute for the route.
	 *
	 * @param \WP_REST_Request $request The request object.
	 */
	public function handleRequest( \WP_REST_Request $request ) {
		$report_string = $request->get_body();
		$report_json   = json_decode( $report_string );

		if ( empty( $report_json->requestedUrl ) ) {
			return new WP_Error(
				'incorrect_lighthouse_report_format',
				'Incorrect Lighthouse Report Format',
				[ 'status' => 400 ]
			);
		}

		$this->api->saveReport( $report_json->requestedUrl, $report_string );
		return true;
	}
}
