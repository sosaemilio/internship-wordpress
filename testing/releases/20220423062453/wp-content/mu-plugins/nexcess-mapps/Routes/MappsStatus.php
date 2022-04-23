<?php

/**
 * A brief description of your REST API route.
 */

namespace Nexcess\MAPPS\Routes;

use Nexcess\MAPPS\Settings;
use WP_REST_Request;

class MappsStatus extends RestRoute {

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
	protected $route = '/status';

	/**
	 * @var \Nexcess\MAPPS\Settings
	 */
	protected $settings;

	/**
	 * Create a new instance of the route.
	 *
	 * @param \Nexcess\MAPPS\Settings $settings
	 */
	public function __construct( Settings $settings ) {
		$this->settings = $settings;
	}

	/**
	 * Determine whether or not the current request is authorized.
	 *
	 * This corresponds to the "permission_callback" argument within the WP REST API.
	 *
	 * @link https://developer.wordpress.org/rest-api/extending-the-rest-api/adding-custom-endpoints/#permissions-callback
	 *
	 * @param WP_REST_Request $request The REST request.
	 *
	 * @return bool|\WP_Error True if authorized, false or a WP_Error object otherwise. If a
	 *                        WP_Error object is returned, it will use its error message. Otherwise,
	 *                        a default message will be used.
	 */
	public function authorizeRequest( WP_REST_Request $request ) {
		return true;
	}

	/**
	 * The primary callback to execute for the route.
	 *
	 * @param WP_REST_Request $request The request object.
	 *
	 * @return mixed
	 */
	public function handleRequest( WP_REST_Request $request ) {
		return [
			'environment' => $this->settings->environment,
			'version'     => $this->settings->mapps_version,
		];
	}
}
