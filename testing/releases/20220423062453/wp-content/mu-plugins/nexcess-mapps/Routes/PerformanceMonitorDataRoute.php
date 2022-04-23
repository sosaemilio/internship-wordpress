<?php

/**
 * The `PeformanceMonitorRoute` returns data used to initialize
 * the UI React App and fill the timeline with a page of results.
 */

namespace Nexcess\MAPPS\Routes;

use Nexcess\MAPPS\Integrations\PerformanceMonitor\UIData;

class PerformanceMonitorDataRoute extends RestRoute {

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
	protected $route = '/performance-monitor/data(?:/(?P<page>[0-9]{1,}))?';

	/**
	 * The REST route in a `sprintf` compatible format/.
	 *
	 * @var string
	 */
	protected $routeFormat = '/performance-monitor/data/%d';

	/**
	 * @var \Nexcess\MAPPS\Integrations\PerformanceMonitor\UIData
	 */
	protected $uiData;

	/**
	 * Constructor.
	 *
	 * @param \Nexcess\MAPPS\Integrations\PerformanceMonitor\UIData $ui_data Instance of a class responsible for generating all the data to be displayed by the UI application.
	 */
	public function __construct( UIData $ui_data ) {
		$this->uiData = $ui_data;
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
	 * @return bool
	 */
	public function authorizeRequest( \WP_REST_Request $request ) {
		return current_user_can( 'manage_options' );
	}

	/**
	 * The primary callback to execute for the route.
	 *
	 * @param \WP_REST_Request $request The request object.
	 *
	 * @return mixed
	 */
	public function handleRequest( \WP_REST_Request $request ) {
		$page = isset( $request['page'] ) ? intval( $request['page'] ) : 1;

		return $this->uiData->getAll( $page );
	}

	/**
	 * Returns a full path to the route with any symbols
	 * necessary for `sprintf` intepolation intact.
	 *
	 * @return string
	 */
	public function getRouteFormat() {
		return sprintf(
			'/%s/%s%s',
			rest_get_url_prefix(),
			$this->getNamespace(),
			$this->routeFormat
		);
	}
}
