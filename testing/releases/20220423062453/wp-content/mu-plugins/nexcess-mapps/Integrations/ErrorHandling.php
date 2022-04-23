<?php

/**
 * Display the current environment type in the Admin Bar.
 */

namespace Nexcess\MAPPS\Integrations;

use Nexcess\MAPPS\Concerns\HasHooks;
use Nexcess\MAPPS\Concerns\HasWordPressDependencies;
use Nexcess\MAPPS\Services\DropIn;
use Nexcess\MAPPS\Settings;

class ErrorHandling extends Integration {
	use HasHooks;
	use HasWordPressDependencies;

	/**
	 * @var \Nexcess\MAPPS\Services\DropIn
	 */
	protected $dropIn;

	/**
	 * @var \Nexcess\MAPPS\Settings
	 */
	protected $settings;

	/**
	 * The flag set once the error handler has been installed once.
	 */
	const FLAG_NAME = 'fatal-error-handler-installed';

	/**
	 * @param \Nexcess\MAPPS\Settings        $settings
	 * @param \Nexcess\MAPPS\Services\DropIn $drop_in
	 */
	public function __construct( Settings $settings, DropIn $drop_in ) {
		$this->settings = $settings;
		$this->dropIn   = $drop_in;
	}

	/**
	 * Determine whether or not this integration should be loaded.
	 *
	 * @return bool Whether or not this integration be loaded in this environment.
	 */
	public function shouldLoadIntegration() {
		return $this->siteIsAtLeastWordPressVersion( '5.2' );
	}

	/**
	 * Retrieve all actions for the integration.
	 *
	 * @return array[]
	 */
	protected function getActions() {
		return [
			[ 'admin_init', [ $this, 'installDropIn' ] ],
		];
	}

	/**
	 * Install the fatal-error-handler.php drop-in.
	 *
	 * Since customers may not always want our custom handler, we'll keep track of whether or not
	 * we've installed it once and, if so, never try to install it again.
	 */
	public function installDropIn() {
		if ( $this->settings->getFlag( self::FLAG_NAME, false ) ) {
			return;
		}

		$this->dropIn->install(
			'fatal-error-handler.php',
			dirname( __DIR__ ) . '/DropIns/fatal-error-handler.php'
		);

		// Set the flag so we don't try to re-install this once removed.
		$this->settings->setFlag( self::FLAG_NAME, true );
	}
}
