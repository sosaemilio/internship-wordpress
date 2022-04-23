<?php

/**
 * Integration with the WP REST API.
 *
 * Most of the work here is handled by the underlying RouteManager service,
 * {@see Nexcess\MAPPS\Services\Managers\RouteManager}.
 */

namespace Nexcess\MAPPS\Integrations;

use Nexcess\MAPPS\Concerns\HasHooks;
use Nexcess\MAPPS\Services\Managers\RouteManager;

class RestApi extends Integration {
	use HasHooks;

	/**
	 * The underlying RouteManager instance.
	 *
	 * @var \Nexcess\MAPPS\Services\Managers\RouteManager
	 */
	protected $routeManager;

	/**
	 * Create a new instance of the REST API integration.
	 *
	 * @param \Nexcess\MAPPS\Services\Managers\RouteManager $route_manager
	 */
	public function __construct( RouteManager $route_manager ) {
		$this->routeManager = $route_manager;
	}

	/**
	 * Retrieve all actions for the integration.
	 *
	 * @return array[]
	 */
	protected function getActions() {
		return [
			[ 'rest_api_init', [ $this->routeManager, 'registerRoutes' ] ],
		];
	}
}
