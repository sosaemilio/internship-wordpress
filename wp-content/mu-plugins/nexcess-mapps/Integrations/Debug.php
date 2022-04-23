<?php

/**
 * Debug integration for Nexcess MAPPS.
 */

namespace Nexcess\MAPPS\Integrations;

use Nexcess\MAPPS\Concerns\HasHooks;

class Debug extends Integration {
	use HasHooks;

	/**
	 * @var int
	 */
	protected $requestStartTime;

	/**
	 * Determine whether or not this integration should be loaded.
	 *
	 * @return bool Whether or not this integration be loaded in this environment.
	 */
	public function shouldLoadIntegration() {
		return ! empty( $_GET['mapps-debug'] ); // phpcs:ignore WordPress.Security.NonceVerification
	}

	/**
	 * Perform any necessary setup for the integration.
	 *
	 * This method is automatically called as part of Plugin::loadIntegration(), and is the
	 * entry-point for all integrations.
	 */
	public function setup() {
		$this->requestStartTime = isset( $_SERVER['REQUEST_TIME_FLOAT'] )
			? $_SERVER['REQUEST_TIME_FLOAT']
			: microtime( true );

		$this->addHooks();
	}

	/**
	 * Retrieve all actions for the integration.
	 *
	 * @return array[]
	 */
	protected function getActions() {
		return [
			[ 'wp_footer', [ $this, 'printDebugging' ], PHP_INT_MAX ],
		];
	}

	/**
	 * Print debugging information.
	 */
	public function printDebugging() {
		?>

<!--

Nexcess MAPPS debugging:

* Page load time: <?php echo esc_html( number_format( microtime( true ) - $this->requestStartTime, 2 ) ); ?> seconds

-->

		<?php
	}
}
