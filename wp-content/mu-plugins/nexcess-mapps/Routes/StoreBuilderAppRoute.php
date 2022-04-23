<?php

/**
 * A brief description of your REST API route.
 */

namespace Nexcess\MAPPS\Routes;

use Nexcess\MAPPS\Concerns\ManagesGroupedOptions;
use Nexcess\MAPPS\Integrations\StoreBuilder\Setup as StoreBuilderSetup;
use WP_REST_Request;

class StoreBuilderAppRoute extends RestRoute {
	use ManagesGroupedOptions;

	/**
	 * @var StoreBuilderSetup $storeBuilderSetup The Store Builder Setup Class
	 */
	protected $storeBuilderSetup;

	/**
	 * The grouped setting option name.
	 */
	const OPTION_NAME = '_storebuilderapp_config';

	/**
	 * Supported HTTP methods for this route.
	 *
	 * @var string[]
	 */
	protected $methods = [
		'GET',
	];

	/**
	 * @var array $setup_props The Setup Props.
	 */
	protected $setup_props;

	/**
	 * The REST route.
	 *
	 * @var string
	 */
	protected $route = '/storebuilderapp/(?P<setup>\w+)';

	/**
	 * Initialize the dependencies.
	 *
	 * @param StoreBuilderSetup $setup The StoreBuilder Class.
	 */
	public function __construct( StoreBuilderSetup $setup ) {
		$this->storeBuilderSetup = $setup;
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
	 * @return bool True if authorized, false otherwise.
	 */
	public function authorizeRequest( WP_REST_Request $request ) {
		// Perform any necessary authorization checks.
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
		$setup = sanitize_text_field( $request['setup'] );
		// Handle the request.
		switch ( $setup ) {
			case 'ftc':
				return $this->storeBuilderSetup->getSetupProps();
			case 'look_and_feel':
				return $this->storeBuilderSetup->getLookAndFeelProps();
			default:
				return $this->storeBuilderSetup->getUIData();
		}
	}

}
