<?php

/**
 * Generic cache integration for Nexcess MAPPS.
 *
 * More specific implementations are available:
 *
 * @see Nexcess\MAPPS\Integrations\ObjectCache
 * @see Nexcess\MAPPS\Integrations\PageCache
 * @see Nexcess\MAPPS\Integrations\Varnish
 */

namespace Nexcess\MAPPS\Integrations;

use Nexcess\MAPPS\Concerns\HasHooks;

class Cache extends Integration {
	use HasHooks;

	/**
	 * Retrieve all actions for the integration.
	 *
	 * @return array[]
	 */
	protected function getActions() {
		return [
			[ 'muplugins_loaded', [ $this, 'maybeFlushAllCaches' ] ],
		];
	}

	/**
	 * Check for the presence of a .flush-cache file in the web root.
	 *
	 * If present, flush the object cache, then remove the file.
	 *
	 * This handles a case when a migration is executed which directly manipulates the database and
	 * filesystem. This can sometimes leave the cache in a state where it's still populated with
	 * the original theme, plugins, and site options, causing a broken site experience.
	 */
	public function maybeFlushAllCaches() {
		$filepath = ABSPATH . '.flush-cache';

		// No file means there's nothing to do.
		if ( ! file_exists( $filepath ) ) {
			return;
		}

		// Only remove the file if all relevant caches were flushed successfully.
		if ( wp_cache_flush() ) {
			unlink( $filepath );
		}
	}
}
