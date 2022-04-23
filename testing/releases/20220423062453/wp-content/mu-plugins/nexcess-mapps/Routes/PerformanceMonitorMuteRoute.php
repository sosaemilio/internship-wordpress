<?php

/**
 * The `PeformanceMonitorRoute` returns data used to initialize
 * the UI React App and fill the timeline with a page of results.
 */

namespace Nexcess\MAPPS\Routes;

use Nexcess\MAPPS\Integrations\PerformanceMonitor\Generator\InsightGenerator;

class PerformanceMonitorMuteRoute extends RestRoute {

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
	protected $route = '/performance-monitor/mute/(?P<type>[a-z-]{1,})';

	/**
	 * The REST route in a `sprintf` compatible format/.
	 *
	 * @var string
	 */
	protected $routeFormat = '/performance-monitor/mute/%s';

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
	 * @return bool `true` if the type is newly muted, `false` otherwise.
	 */
	public function handleRequest( \WP_REST_Request $request ) {
		if ( is_string( $request['type'] ) ) {
			$muted_insights   = get_option( InsightGenerator::MUTED_INSIGHTS_OPTION_KEY, [] );
			$is_muted         = isset( $muted_insights[ $request['type'] ] );
			$is_muted_expired = $is_muted && time() - $muted_insights[ $request['type'] ] > InsightGenerator::MUTED_INSIGHTS_DURATION;

			if ( $is_muted_expired ) {
				unset( $muted_insights[ $request['type'] ] );
			}
			if ( ! $is_muted || $is_muted_expired ) {
				$muted_insights[ $request['type'] ] = time();
			}
			return update_option( InsightGenerator::MUTED_INSIGHTS_OPTION_KEY, $muted_insights, false );
		}
		return false;
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
