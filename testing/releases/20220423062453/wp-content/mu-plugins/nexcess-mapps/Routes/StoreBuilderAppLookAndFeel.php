<?php

/**
 * A brief description of your REST API route.
 */

namespace Nexcess\MAPPS\Routes;

use Nexcess\MAPPS\Integrations\StoreBuilder\LookAndFeel;
use WP_REST_Request;

class StoreBuilderAppLookAndFeel extends RestRoute {

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
	protected $route = '/storebuilderapp/wizard/lookandfeel';

	/**
	 * @var LookAndFeel $look_and_feel
	 */
	private $look_and_feel;

	/**
	 * @param LookAndFeel $look_and_feel
	 */
	public function __construct( LookAndFeel $look_and_feel ) {
		$this->look_and_feel = $look_and_feel;
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
	 * @return array
	 */
	public function handleRequest( WP_REST_Request $request ) {
		$return = [
			'template'   => $this->look_and_feel->getTemplate(),
			'font'       => $this->look_and_feel->getFont(),
			'color'      => $this->look_and_feel->getColor(),
			'ajax_nonce' => wp_create_nonce( 'kadence-ajax-verification' ),
			'complete'   => $this->look_and_feel->isComplete(),
		];
		return $return;
	}
}
