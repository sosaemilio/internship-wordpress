<?php

namespace Nexcess\MAPPS\Commands;

use Nexcess\MAPPS\Integrations\ObjectCache;
use Nexcess\MAPPS\Integrations\PageCache;
use StellarWP\PluginFramework\Exceptions\WPConfigException;

use function WP_CLI\Utils\get_flag_value;

/**
 * WP-CLI sub-commands related to managing cache on Nexcess MAPPS sites.
 *
 * These commands will generally map to the underlying caching plugins/tools,
 * but provide a standard interface for the Nexcess MAPPS platform.
 */
class Cache extends Command {

	/**
	 * @var ObjectCache
	 */
	protected $objectCache;

	/**
	 * @var PageCache
	 */
	protected $pageCache;

	/**
	 * Construct an instance of the command.
	 *
	 * @param ObjectCache $object_cache The ObjectCache integration.
	 * @param PageCache   $page_cache   The PageCache integration.
	 */
	public function __construct( ObjectCache $object_cache, PageCache $page_cache ) {
		$this->objectCache = $object_cache;
		$this->pageCache   = $page_cache;
	}

	/**
	 * Enable caching layers for a site.
	 *
	 * ## OPTIONS
	 *
	 * [<type>...]
	 * : The caching layer to enable.
	 * ---
	 * options:
	 *   - object
	 *   - page
	 *
	 * [--all]
	 * : Enable all available cache types.
	 *
	 * ## EXAMPLES
	 *
	 * # Enable all caching
	 * $ wp nxmapps cache enable --all
	 *
	 * # Only enable object caching
	 * $ wp nxmapps cache enable object
	 *
	 * @param mixed[] $args       Positional arguments.
	 * @param mixed[] $assoc_args Associative arguments/options passed to the command.
	 */
	public function enable( $args, $assoc_args ) {
		$all     = get_flag_value( $assoc_args, 'all', false );
		$enabled = [];

		if ( empty( $args ) && ! $all ) {
			$this->warning( 'No cache types were specified. Please specify one or more cache types, or --all.' )
				->halt( 1 );
		}

		// Enable page caching.
		if ( $all || in_array( 'page', $args, true ) ) {
			$this->step( 'Enabling page caching' );

			try {
				$this->pageCache->enablePageCache();
				$enabled[] = 'page';
			} catch ( WPConfigException $e ) {
				$this->error( 'Unable to enable page caching: ' . $e->getMessage(), false );
			}
		}

		// Finally, report status.
		if ( empty( $enabled ) ) {
			return $this->warning( 'No cache types were enabled.' );
		}

		$this->success( 'The following cache type(s) have been enabled:' )
			->listing( $enabled );
	}

	/**
	 * Disable caching layers for a site.
	 *
	 * ## OPTIONS
	 *
	 * [<type>...]
	 * : The caching layer to disable.
	 * ---
	 * options:
	 *   - object
	 *   - page
	 *
	 * [--all]
	 * : Disable all available cache types.
	 *
	 * ## EXAMPLES
	 *
	 * # Disable all caching
	 * $ wp nxmapps cache disable --all
	 *
	 * # Only disable object caching
	 * $ wp nxmapps cache disable object
	 *
	 * @param mixed[] $args       Positional arguments.
	 * @param mixed[] $assoc_args Associative arguments/options passed to the command.
	 */
	public function disable( $args, $assoc_args ) {
		$all      = get_flag_value( $assoc_args, 'all', false );
		$disabled = [];

		if ( empty( $args ) && ! $all ) {
			$this->warning( 'No cache types were specified. Please specify one or more cache types, or --all.' )
				->halt( 1 );
		}

		// Disable object caching.
		if ( $all || in_array( 'object', $args, true ) ) {
			$this->step( 'Disabling object caching' );
			$this->wp( 'plugin deactivate redis-cache wp-redis object-cache-pro' );

			if ( file_exists( WP_CONTENT_DIR . '/object-cache.php' ) ) {
				unlink( WP_CONTENT_DIR . '/object-cache.php' );
			}

			$disabled[] = 'object';
		}

		// Disable page caching.
		if ( $all || in_array( 'page', $args, true ) ) {
			$this->step( 'Disabling page caching' );

			try {
				$this->pageCache->disablePageCache();
				$disabled[] = 'page';
			} catch ( WPConfigException $e ) {
				$this->error( 'Unable to disable page caching: ' . $e->getMessage(), false );
			}
		}

		// Finally, report status.
		if ( empty( $disabled ) ) {
			return $this->warning( 'No cache types were disabled.' );
		}

		$this->success( 'The following cache type(s) have been disabled:' )
			->listing( array_unique( $disabled ) );
	}

	/**
	 * Flush various caches across the site.
	 *
	 * This command primarily acts as proxy to common plugins' cache controls, while also providing
	 * a single place to flush *all* caches.
	 *
	 * ## OPTIONS
	 *
	 * [<type>...]
	 * : The caching layer to flush.
	 * ---
	 * options:
	 *   - assets
	 *   - object
	 *   - page
	 *
	 * [--all]
	 * : Flush all available cache types.
	 *
	 * ## EXAMPLES
	 *
	 * # Flush all caches
	 * $ wp nxmapps cache flush --all
	 *
	 * # Only flush the object cache
	 * $ wp nxmapps cache flush object
	 *
	 * @param mixed[] $args       Positional arguments.
	 * @param mixed[] $assoc_args Associative arguments/options passed to the command.
	 */
	public function flush( array $args, array $assoc_args ) {
		$all     = get_flag_value( $assoc_args, 'all', false );
		$flushed = [];

		if ( empty( $args ) && ! $all ) {
			$this->warning( 'No cache types were specified. Please specify one or more cache types, or --all.' )
				->halt( 1 );
		}

		// Flush the object cache.
		if ( $all || in_array( 'object', $args, true ) ) {
			$this->step( 'Flushing the object cache' );
			$this->wp( 'cache flush' );
			$this->wp( 'transient delete --all' );
			$flushed[] = 'Object cache';
		}

		// Flush page caches.
		if ( $all || in_array( 'page', $args, true ) ) {
			$commands = [
				'Cache Enabler'     => 'cache-enabler clear',
				'Swift Performance' => 'sp_clear_all_cache',
				'W3 Total Cache'    => 'w3-total-cache flush all',
				'WP Fastest Cache'  => 'fastest-cache clear all and minified',
				'WP Rocket'         => 'rocket clean --confirm',
				'WP Super Cache'    => 'super-cache flush',
			];

			$this->step( 'Flushing page caches' );
			foreach ( $commands as $label => $command ) {
				if ( $this->commandExists( $command ) ) {
					$this->wp( $command );
					$flushed[] = $label;
				}
			}
		}

		// Flush asset caches.
		if ( $all || in_array( 'assets', $args, true ) ) {
			$commands = [
				'Autoptimize'    => 'autoptimize clear',
				'Beaver Builder' => 'beaver clearcache --all',
				'Elementor'      => 'elementor flush_css',
				'W3 Total Cache' => 'w3-total-cache flush minify',
			];

			$this->step( 'Flushing asset caches' );
			foreach ( $commands as $label => $command ) {
				if ( $this->commandExists( $command ) ) {
					$this->wp( $command );
					$flushed[] = $label;
				}
			}
		}

		// Finally, report status.
		if ( empty( $flushed ) ) {
			return $this->warning( 'No caches were flushed.' );
		}

		$this->line()
			->success( 'The following cache type(s) have been flushed:' )
			->listing( $flushed );
	}
}
