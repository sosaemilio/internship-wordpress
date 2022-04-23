<?php

/**
 * Responsible for coordinating custom WP REST API routes.
 */

namespace Nexcess\MAPPS\Services\Managers;

use Nexcess\MAPPS\Container;
use Nexcess\MAPPS\Exceptions\RouteException;
use Nexcess\MAPPS\Routes;
use Nexcess\MAPPS\Routes\RestRoute;

class RouteManager {

	/**
	 * The DI container, used to resolve route classes.
	 *
	 * @var \Nexcess\MAPPS\Container
	 */
	protected $container;

	/**
	 * An array of registered RestRoute objects.
	 *
	 * Global routes may be registered here directly by referencing their class names.
	 *
	 * @var array<RestRoute|string>
	 */
	protected $routes = [];

	/**
	 * Construct the manager instance.
	 *
	 * @param \Nexcess\MAPPS\Container $container The DI container.
	 */
	public function __construct( Container $container ) {
		$this->container = $container;

		$this->routes = [
			$this->container->get( Routes\MappsStatus::class ),
		];
	}

	/**
	 * Register a new route.
	 *
	 * @param RestRoute|string $route The route to be registered, either as an instance or as a
	 *                                class name.
	 *
	 * @return self
	 */
	public function addRoute( $route ) {
		$this->routes[] = $route;

		return $this;
	}

	/**
	 * Return all registered routes.
	 *
	 * @return mixed[] An array consisting of RestRoute instances and/or class names.
	 */
	public function getRoutes() {
		return $this->routes;
	}

	/**
	 * Register the routes within the WP REST API.
	 *
	 * @throws RouteException If called before rest_api_init.
	 *
	 * @return self
	 */
	public function registerRoutes() {
		if ( ! did_action( 'rest_api_init' ) ) {
			throw new RouteException( 'Routes cannot be registered within WordPress before rest_api_init.' );
		}

		foreach ( $this->routes as $route ) {
			if ( ! $route instanceof RestRoute ) {
				if ( is_subclass_of( $route, RestRoute::class, true ) ) {
					$route = $this->container->get( $route );
				} else {
					// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_trigger_error
					trigger_error(
						esc_html( sprintf( '%s is not a sub-class of %s, skipping.', $route, RestRoute::class ) ),
						E_USER_WARNING
					);
					continue;
				}
			}
			register_rest_route( $route->getNamespace(), $route->getRoute(), $route->getArgs() );
		}

		return $this;
	}
}
