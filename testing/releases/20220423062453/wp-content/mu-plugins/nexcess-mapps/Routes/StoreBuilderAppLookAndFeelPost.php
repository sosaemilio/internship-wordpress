<?php

/**
 * A brief description of your REST API route.
 */

namespace Nexcess\MAPPS\Routes;

use Nexcess\MAPPS\Integrations\StoreBuilder\LookAndFeel;
use WP_REST_Request;

class StoreBuilderAppLookAndFeelPost extends RestRoute {

	const ENDPOINT = '/storebuilderapp/wizard/lookandfeel';
	const COLOR    = 'color';
	const FONT     = 'font';
	const TEMPLATE = 'template';

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
	protected $route = self::ENDPOINT;

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

		if ( $request->get_param( self::COLOR ) ) {
			$this->look_and_feel->setColor( $request->get_param( self::COLOR ) );
		}
		if ( $request->get_param( self::FONT ) ) {
			$this->look_and_feel->setFont( $request->get_param( self::FONT ) );
		}
		if ( $request->get_param( self::TEMPLATE ) ) {
			$this->look_and_feel->setTemplate( $request->get_param( self::TEMPLATE ) );
		}
		if ( $this->look_and_feel->save() ) {
			return [ 'success' => true ];
		}

		return [
			'success' => false,
			'message' => [
				'lookandfeel' => __( 'Invalid look and feel values.', 'nexcess-mapps' ),
			],
		];
	}
}
