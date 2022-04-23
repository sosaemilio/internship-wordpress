<?php

namespace Nexcess\MAPPS\Commands;

/**
 * Commands to aid in end-to-end platform testing.
 */
class PlatformTesting extends Command {

	/**
	 * Execute end-to-end platform tests.
	 */
	public function run() {
		$this->warning( 'No end-to-end tests have been defined!' )
			->line( 'On the upside, that also means everything worked just fine 🎉' );
	}
}
