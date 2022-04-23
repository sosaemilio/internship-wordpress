<?php

/**
 * Responsible for registering custom Site Health checks.
 */

namespace Nexcess\MAPPS\Services\Managers;

use Nexcess\MAPPS\Container;
use Nexcess\MAPPS\HealthChecks;
use Nexcess\MAPPS\HealthChecks\HealthCheck;

class SiteHealthManager {

	/**
	 * An array of registered HealthCheck objects, group by async/direct.
	 *
	 * @var array[]
	 */
	protected $checks = [
		'async'  => [],
		'direct' => [
			HealthChecks\CronConstant::class,
			HealthChecks\PageCache::class,
		],
	];

	/**
	 * The DI container, used to resolve route classes.
	 *
	 * @var \Nexcess\MAPPS\Container
	 */
	protected $container;

	/**
	 * Construct the manager instance.
	 *
	 * @param \Nexcess\MAPPS\Container $container The DI container.
	 */
	public function __construct( Container $container ) {
		$this->container = $container;
	}

	/**
	 * Register a new Health Check.
	 *
	 * @param HealthCheck|string $check The Health Check to be registered, either as a fully-
	 *                                  qualified class name or as an instance.
	 * @param bool               $async Optional. Whether the check should be async (true) or
	 *                                  direct (false). Default is false (run on page load).
	 *
	 * @return self
	 */
	public function addCheck( $check, $async = false ) {
		$type                    = $async ? 'async' : 'direct';
		$this->checks[ $type ][] = $check;

		return $this;
	}

	/**
	 * Return all registered routes.
	 *
	 * @return array[]
	 */
	public function getChecks() {
		return $this->checks;
	}

	/**
	 * Register the Health Checks within WordPress.
	 *
	 * @param array[] $health_checks Current Site Health checks.
	 *
	 * @return array[] The filtered $health_checks array.
	 */
	public function registerChecks( array $health_checks ) {
		foreach ( $this->checks as $type => $checks ) {
			if ( ! isset( $health_checks[ $type ] ) ) {
				$health_checks[ $type ] = [];
			}

			foreach ( $checks as $check ) {
				if ( ! $check instanceof HealthCheck ) {
					if ( is_string( $check ) && is_subclass_of( $check, HealthCheck::class, true ) ) {
						$check = $this->container->get( $check );
					} else {
						// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_trigger_error
						trigger_error(
							esc_html( sprintf( '%s is not a sub-class of %s, skipping.', $check, HealthCheck::class ) ),
							E_USER_WARNING
						);
						continue;
					}
				}

				$health_checks[ $type ][ $check->id ] = [
					'label' => $check->label,
					'test'  => [ $check, 'run' ],
				];
			}
		}

		return $health_checks;
	}
}
