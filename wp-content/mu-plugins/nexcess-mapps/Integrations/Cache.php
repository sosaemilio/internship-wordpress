<?php

/**
 * Generic cache integration for Nexcess MAPPS.
 *
 * More specific implementations are available:
 *
 * @see Nexcess\MAPPS\Integrations\ObjectCache
 * @see Nexcess\MAPPS\Integrations\PageCache
 */

namespace Nexcess\MAPPS\Integrations;

use Nexcess\MAPPS\Concerns\HasHooks;
use Nexcess\MAPPS\Services\AdminBar;
use Nexcess\MAPPS\Services\MappsApiClient;
use Nexcess\MAPPS\Support\AdminNotice;
use StellarWP\PluginFramework\Exceptions\RequestException;

class Cache extends Integration {
	use HasHooks;

	/**
	 * @var \Nexcess\MAPPS\Services\AdminBar
	 */
	protected $adminBar;

	/**
	 * @var \Nexcess\MAPPS\Services\MappsApiClient
	 */
	protected $client;

	/**
	 * @param \Nexcess\MAPPS\Services\AdminBar       $admin_bar
	 * @param \Nexcess\MAPPS\Services\MappsApiClient $client
	 */
	public function __construct( AdminBar $admin_bar, MappsApiClient $client ) {
		$this->adminBar = $admin_bar;
		$this->client   = $client;
	}

	/**
	 * Retrieve all actions for the integration.
	 *
	 * @return array[]
	 */
	protected function getActions() {
		return [
			[ 'muplugins_loaded', [ $this, 'maybeFlushAllCaches' ] ],
			[ 'init', [ $this, 'registerAdminBarMenu' ] ],
			[ 'admin_action_nexcess-mapps-flush-cache', [ $this, 'adminBarFlushCache' ] ],
			[ 'admin_post_nexcess-mapps-flush-cache', [ $this, 'adminBarFlushCache' ] ],
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

	/**
	 * Register the admin bar menu item.
	 */
	public function registerAdminBarMenu() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$this->adminBar->addMenu(
			'flush-cache',
			AdminBar::getActionPostForm(
				'nexcess-mapps-flush-cache',
				_x( 'Flush Nexcess Cache & CDN', 'admin bar menu title', 'nexcess-mapps' )
			)
		);
	}

	/**
	 * Callback for requests to flush the object cache via the Admin Bar.
	 */
	public function adminBarFlushCache() {
		if ( ! AdminBar::validateActionNonce( 'nexcess-mapps-flush-cache' ) ) {
			return $this->adminBar->addNotice( new AdminNotice(
				__( 'We were unable to flush the cache, please try again.', 'nexcess-mapps' ),
				'error',
				true
			) );
		}

		try {
			$this->client->purgeCaches();
		} catch ( RequestException $e ) {
			return $this->adminBar->addNotice( new AdminNotice(
				sprintf( 'Unexpected error attempting to flush the cache: %s', $e->getMessage() ),
				'error',
				true
			) );
		}

		$this->adminBar->addNotice( new AdminNotice(
			__( 'The cache has been flushed successfully!', 'nexcess-mapps' ),
			'success',
			true
		) );

		// If we have a referrer, we likely came from the front-end of the site.
		$referrer = wp_get_referer();

		if ( $referrer ) {
			return wp_safe_redirect( $referrer );
		}
	}
}
