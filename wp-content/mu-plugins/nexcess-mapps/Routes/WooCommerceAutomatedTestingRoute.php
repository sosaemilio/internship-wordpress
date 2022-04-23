<?php

namespace Nexcess\MAPPS\Routes;

use Nexcess\MAPPS\Integrations\WooCommerceAutomatedTesting;
use WP_REST_Request;

class WooCommerceAutomatedTestingRoute extends RestRoute {

	/**
	 * @var \Nexcess\MAPPS\Integrations\WooCommerceAutomatedTesting
	 */
	protected $integration;

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
	protected $route = '/woocommerce-automated-testing';

	/**
	 * Create the route instance.
	 *
	 * @param \Nexcess\MAPPS\Integrations\WooCommerceAutomatedTesting $integration The WooCommerceAutomatedTesting integration.
	 */
	public function __construct( WooCommerceAutomatedTesting $integration ) {
		$this->integration = $integration;
	}

	/**
	 * Determine whether or not the current request is authorized.
	 *
	 * This corresponds to the "permission_callback" argument within the WP REST API.
	 *
	 * @param WP_REST_Request $request The REST request.
	 *
	 * @return bool|\WP_Error True if authorized, false or a WP_Error object otherwise. If a
	 *                        WP_Error object is returned, it will use its error message. Otherwise,
	 *                        a default message will be used.
	 */
	public function authorizeRequest( WP_REST_Request $request ) {
		$options = get_option( WooCommerceAutomatedTesting::OPTION_NAME );
		$api_key = ! empty( $options['api_key'] ) ? $options['api_key'] : '';

		return self::verifyBearerToken( $request, $api_key );
	}

	/**
	 * The primary callback to execute for the route.
	 *
	 * @param WP_REST_Request $request The request object.
	 *
	 * @return mixed
	 */
	public function handleRequest( WP_REST_Request $request ) {
		return $this->integration->getSiteInfo();
	}
}
