<?php

/**
 * An object-oriented representation of a WP REST API route.
 */

namespace Nexcess\MAPPS\Routes;

use WP_Error;
use WP_REST_Request;

/**
 * @property-read mixed[] $args
 * @property-read string  $id
 * @property-read string  $namespace
 * @property-read string  $route
 */
abstract class RestRoute {

	/**
	 * An array of additional arguments to pass to register_rest_route().
	 *
	 * @see self::getArgs()
	 *
	 * @var mixed[]
	 */
	protected $args;

	/**
	 * The route namespace.
	 *
	 * @var string
	 */
	protected $namespace = 'nexcess-mapps/v1';

	/**
	 * Supported HTTP methods for this route.
	 *
	 * @var string[]
	 */
	protected $methods;

	/**
	 * The REST route.
	 *
	 * @var string
	 */
	protected $route = '';

	/**
	 * Retrieve arguments for registering within the WP REST API.
	 *
	 * @return mixed[]
	 */
	public function getArgs() {
		return array_merge( [
			'permission_callback' => [ $this, 'authorizeRequest' ],
			'callback'            => [ $this, 'handleRequest' ],
			'methods'             => $this->methods,
		], (array) $this->args );
	}

	/**
	 * Get the route namespace.
	 *
	 * @return string
	 */
	public function getNamespace() {
		return $this->namespace;
	}

	/**
	 * Get the REST route.
	 *
	 * @return string
	 */
	public function getRoute() {
		return $this->route;
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
	 * @return bool|WP_Error True if authorized, false or a WP_Error object otherwise. If a
	 *                       WP_Error object is returned, it will use its error message. Otherwise,
	 *                       a default message will be used.
	 */
	abstract public function authorizeRequest( WP_REST_Request $request );

	/**
	 * The primary callback to execute for the route.
	 *
	 * @param WP_REST_Request $request The request object.
	 *
	 * @return mixed
	 */
	abstract public function handleRequest( WP_REST_Request $request );

	/**
	 * Returns the value of a bearer token.
	 *
	 * @param WP_REST_Request $request The request object.
	 *
	 * @return string|WP_Error Bearer token string if the bearer token is set,
	 *                         a WP_Error object describing the error otherwise.
	 */
	public static function getBearerToken( WP_REST_Request $request ) {
		$auth = $request->get_header( 'Authorization' );

		// Ensure an Authorization header is set.
		if ( empty( $auth ) ) {
			return new WP_Error(
				'mapps-rest-route-missing-auth-header',
				__( 'This route requires an Authorization header be passed with a valid bearer token.', 'nexcess-mapps' )
			);
		}

		// Ensure the header is a bearer token.
		if ( 0 !== mb_stripos( $auth, 'Bearer ' ) ) {
			return new WP_Error(
				'mapps-rest-route-missing-bearer-token',
				__( 'This route requires a valid bearer token in the Authorization header.', 'nexcess-mapps' )
			);
		}

		return trim( mb_substr( $auth, 7 ) );
	}

	/**
	 * Compare a bearer token against a known value.
	 *
	 * @param WP_REST_Request $request The request object.
	 * @param string          $value   The expected bearer token value.
	 *
	 * @return true|WP_Error True if the bearer token is set and matches $value, a WP_Error object
	 *                       describing the error otherwise.
	 */
	public static function verifyBearerToken( $request, $value ) {
		// Don't try to verify against an empty value.
		if ( empty( $value ) ) {
			return new WP_Error(
				'mapps-rest-route-expected-value-invalid',
				__( 'Refusing to compare a bearer token against an invalid value.', 'nexcess-mapps' )
			);
		}

		$bearer_token = self::getBearerToken( $request );

		if ( $bearer_token instanceof \WP_Error ) {
			return $bearer_token;
		}

		// Validate the bearer token against $value.
		return $bearer_token === $value
			? true
			: new WP_Error(
				'mapps-rest-route-invalid-bearer-token',
				__( 'The provided bearer token is invalid.', 'nexcess-mapps' )
			);
	}
}
