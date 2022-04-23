<?php

/**
 * This endpoint validates the storebuilder username.
 */

namespace Nexcess\MAPPS\Routes;

use Nexcess\MAPPS\Integrations\StoreBuilder\StoreBuilderUser;
use WP_REST_Request;

class StoreBuilderAppUsername extends RestRoute {

	/**
	 * Supported HTTP methods for this route.
	 *
	 * @var string[]
	 */
	protected $methods = [
		'POST',
	];

	/**
	 * An instance of the StoreBuilderUser class.
	 *
	 * @var StoreBuilderUser
	 */
	protected $storebuilder_user;

	/**
	 * The REST route.
	 *
	 * @var string
	 */
	protected $route = '/storebuilderapp/wizard/validate/username';

	/**
	 * @param StoreBuilderUser $storebuilder_user
	 */
	public function __construct( StoreBuilderUser $storebuilder_user ) {
		$this->storebuilder_user = $storebuilder_user;
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
	 * @return bool
	 */
	public function authorizeRequest( WP_REST_Request $request ) {
		return current_user_can( 'manage_options' );
	}

	/**
	 * The primary callback to execute for the route.
	 *
	 * @param WP_REST_Request $request The request object.
	 *
	 * @return mixed
	 */
	public function handleRequest( WP_REST_Request $request ) {
		$username = sanitize_text_field( $request->get_param( 'username' ) );
		$errors   = [];

		if ( ! $this->storebuilder_user->validateUsername( $username ) ) {
			$errors[] = __( 'Username not valid.', 'nexcess-mapps' );
		}

		if ( $errors ) {
			return [
				'status'  => 'error',
				'message' => $errors,
			];
		}

		return [ 'status' => 'success' ];
	}
}
