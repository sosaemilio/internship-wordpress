<?php

/**
 * This service is responsible for cleaning up leftover MU plugins, configurations, etc. that were
 * carried over during migrations.
 *
 * For each [previous] host, we establish a set of "check" files, meant to distinguish one host
 * from another (for example, another host is unlikely to have "mu-plugins/liquid-web.php"). If
 * one of the check files is found, we'll clean up known files/directories from those hosts.
 */

namespace Nexcess\MAPPS\Services;

use StellarWP\PluginFramework\Exceptions\FilesystemException;
use WP_Filesystem_Base;

class MigrationCleaner {

	/**
	 * @var \Nexcess\MAPPS\Services\WPConfig
	 */
	protected $config;

	/**
	 * The WordPress filesystem object.
	 *
	 * @var \WP_Filesystem_Base
	 */
	protected $fs;

	/**
	 * Create a new instance of the MigrationCleaner service.
	 *
	 * @param \WP_Filesystem_Base              $filesystem A WP_Filesystem instance.
	 * @param \Nexcess\MAPPS\Services\WPConfig $wp_config  A WPConfig instance.
	 */
	public function __construct( WP_Filesystem_Base $filesystem, WPConfig $wp_config ) {
		$this->fs     = $filesystem;
		$this->config = $wp_config;
	}

	/**
	 * Find and remove migration leftovers on the site.
	 *
	 * @throws \StellarWP\PluginFramework\Exceptions\FilesystemException If an error occurs.
	 *
	 * @return array[] {
	 *
	 *   An array containing two keys:
	 *
	 *    @type string[] $constants Any constants that were removed.
	 *    @type array[]  $files     Removed files, grouped by host.
	 * }
	 */
	public function clean() {
		$leftovers = $this->scan();
		$removed   = [
			'constants' => [],
			'files'     => [],
		];

		foreach ( $leftovers['constants'] as $constant => $reason ) {
			$this->config->removeConstant( $constant );
			$removed['constants'][ $constant ] = $reason;
		}

		foreach ( $leftovers['files'] as $host => $files ) {
			$removed['files'][ $host ] = $this->remove( $files );
		}

		return $removed;
	}

	/**
	 * Retrieve an array of hosts that we have definitions for.
	 *
	 * @return string[] Host names.
	 */
	public function getSupportedHosts() {
		return array_keys( $this->getDefinitions() );
	}

	/**
	 * Remove the given $paths from the filesystem.
	 *
	 * @param string[] $paths The files and/or directories to remove, relative to WP_CONTENT_DIR.
	 *
	 * @throws \StellarWP\PluginFramework\Exceptions\FilesystemException If any files are unable to be removed.
	 *
	 * @return string[] All system paths that have been removed.
	 */
	public function remove( array $paths ) {
		$removed = [];
		$missed  = [];
		rsort( $paths );

		foreach ( $paths as $path ) {
			$path = $this->fs->wp_content_dir() . $path;

			try {
				$this->deletePath( $path );
				$removed[] = $path;
			} catch ( FilesystemException $e ) {
				$missed[] = $path;
			}
		}

		if ( ! empty( $missed ) ) {
			throw new FilesystemException( sprintf(
				"One or more files could not be deleted:\n- %s",
				implode( "\n- ", $missed )
			) );
		}

		return $removed;
	}

	/**
	 * Scan the local filesystem for leftovers. No files will be removed.
	 *
	 * @throws \StellarWP\PluginFramework\Exceptions\FilesystemException If an error occurs.
	 *
	 * @return array[] An array of detected files, grouped by host.
	 */
	public function scan() {
		$definitions = $this->getDefinitions();
		$found       = [];

		foreach ( $definitions as $platform => $definition ) {
			// If no checkfile exists, continue onto the next platform.
			if ( ! $this->checkFilesFound( $definition['check'] ) ) {
				continue;
			}

			// Filter the list to those files that actually exist.
			$found[ $platform ] = array_filter( $definition['paths'], function ( $path ) {
				return $this->fs->exists( $this->fs->wp_content_dir() . $path );
			} );

			rsort( $found[ $platform ] );
		}

		return [
			'constants' => $this->scanConstants(),
			'files'     => $found,
		];
	}

	/**
	 * Scan for any mis-defined constants defined within wp-config.php.
	 *
	 * @return string[] An array of constants that should be removed.
	 */
	public function scanConstants() {
		$detected = [];

		// Used by get_temp_dir().
		if ( defined( 'WP_TEMP_DIR' ) ) {
			if ( empty( WP_TEMP_DIR ) ) {
				$detected['WP_TEMP_DIR'] = 'WP_TEMP_DIR is defined, but empty';
			} elseif ( ! is_dir( WP_TEMP_DIR ) ) {
				$detected['WP_TEMP_DIR'] = sprintf( 'Path %s does not exist', WP_TEMP_DIR );
			} elseif ( ! is_writable( WP_TEMP_DIR ) ) {
				$detected['WP_TEMP_DIR'] = sprintf( 'Directory %s is not writable', WP_TEMP_DIR );
			}
		}

		return $detected;
	}

	/**
	 * Given an array of file paths relative to WP_CONTENT_DIR, determine if at least one of the
	 * files exists.
	 *
	 * @param string[] $paths Filepaths to check.
	 *
	 * @return bool True if at least one of the files was found, false otherwise.
	 */
	protected function checkFilesFound( array $paths ) {
		foreach ( $paths as $path ) {
			if ( $this->fs->exists( $this->fs->wp_content_dir() . $path ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Delete a single path.
	 *
	 * This is a wrapper around WP_Filesystem_Base::delete(), but will attempt to automatically
	 * remove empty directories.
	 *
	 * @param string $path The path to remove.
	 * @param string $type Optional. One of "d" ("Directory") or "f" ("File"). Default is empty.
	 *
	 * @throws \StellarWP\PluginFramework\Exceptions\FilesystemException If a file exists but cannot be deleted.
	 *
	 * @return bool True if the file was deleted.
	 */
	protected function deletePath( $path, $type = '' ) {
		if ( ! in_array( $type, [ 'd', 'f' ], true ) ) {
			$type = false;
		}

		// The file doesn't exist, so there's nothing to do.
		if ( $this->fs->exists( $path ) ) {
			if ( ! $this->fs->delete( $path, true, $type ) ) {
				throw new FilesystemException( sprintf( 'Unable to delete %s.', $path ) );
			}
		}

		// If the parent directory is empty, recursively try to delete it.
		$dirname = untrailingslashit( dirname( $path ) );

		if ( empty( $this->fs->dirlist( $dirname, false ) ) && $this->fs->wp_content_dir() !== $dirname ) {
			return $this->deletePath( $dirname, 'd' );
		}

		return true;
	}

	/**
	 * Retrieve an array of known leftovers, grouped by host.
	 *
	 * @return array[] {
	 *
	 *   Leftover files, grouped by host.
	 *
	 *   @type string[] $check An array of one or more files to test; at least one of these files
	 *                        must exist before the service will attempt to clean anything up.
	 *   @type string[] $paths File and directory paths, relative to WP_CONTENT_DIR, to be
	 *                        found and removed.
	 * }
	 */
	protected function getDefinitions() {
		return [
			'Bluehost'       => [
				'check' => [
					'plugins/bluehost-wordpress-plugin/',
					'mu-plugins/endurance-browser-cache.php',
					'mu-plugins/endurance-page-cache.php',
					'mu-plugins/endurance-php-edge.php',
				],
				'paths' => [
					'mu-plugins/endurance-browser-cache.php',
					'mu-plugins/endurance-page-cache.php',
					'mu-plugins/endurance-php-edge.php',
					'mu-plugins/sso.php',
					'plugins/bluehost-wordpress-plugin/',
				],
			],

			// AKA DreamPress.
			'DreamHost'      => [
				'check' => [
					'plugins/dreamobjects',
					'plugins/dreamhost-panel-login',
				],
				'paths' => [
					'plugins/dreamobjects',
					'plugins/varnish-http-purge',
					'plugins/dreamhost-panel-login',
				],
			],

			'EasyWP'         => [
				'check' => [
					'mu-plugins/wp-nc-easywp.php',
				],
				'paths' => [
					'mu-plugins/wp-nc-easywp/',
					'mu-plugins/wp-nc-easywp.php',
				],
			],

			'Flywheel'       => [
				'check' => [
					'../.fw-config.php',
				],
				'paths' => [
					'../.fw-config.php',
					'db-error.php',
					'mu-plugins/health-check-troubleshooting-mode.php',
				],
			],

			'GoDaddy'        => [
				'check' => [
					'mu-plugins/gd-system-plugin',
					'mu-plugins/gd-system-plugin.php',
					'mu-plugins/gd-config.php',
					'mu-plugins/gd-config-sample.php',
				],
				'paths' => [
					'mu-plugins/gd-system-plugin',
					'mu-plugins/gd-system-plugin.php',
					'mu-plugins/gd-config.php',
					'mu-plugins/gd-config-sample.php',
				],
			],

			'HostGator'      => [
				'check' => [
					'mu-plugins/wp-plugin-hostgator/',
					'mu-plugins/wp-plugin-hostgator.php',
				],
				'paths' => [
					'mu-plugins/wp-plugin-hostgator/',
					'mu-plugins/wp-plugin-hostgator.php',
				],
			],

			'Kinsta'         => [
				'check' => [
					'mu-plugins/kinsta-mu-plugins.php',
				],
				'paths' => [
					'mu-plugins/kinsta-mu-plugins/',
					'mu-plugins/kinsta-mu-plugins.php',
				],
			],

			'Liquid Web MWX' => [
				'check' => [
					'mu-plugins/000-liquidweb-config.php',
					'mu-plugins/liquid-web.php',
					'mu-plugins/liquidweb_mwp.php',
					'mu-plugins/lw_disable_nags.php',
					'mu-plugins/lw-varnish-cache-purger.php',
				],
				'paths' => [
					'mu-plugins/000-liquidweb-config.php',
					'mu-plugins/jetpack.php',
					'mu-plugins/liquid-web.php',
					'mu-plugins/liquid-web/',
					'mu-plugins/liquidweb_mwp.php',
					'mu-plugins/lw_disable_nags.php',
					'mu-plugins/lw-varnish-cache-purger.php',
					'mu-plugins/plugin-reports/package.php',
					'mu-plugins/plugin-reports/src/gather.php',
					'mu-plugins/plugin-reports/src/init.php',
					'mu-plugins/plugin-reports/src/models.php',
					'mu-plugins/plugin-reports/src/plugin-stats/woocommerce.php',
					'mu-plugins/plugin-reports/src/send-report.php',
					'mu-plugins/plugin-reports/src/utils.php',
					'mu-plugins/wp-cli-packages/',
					'mu-plugins/wp-fail2ban.php',
					'mu-plugins/wp-vulnerability-scanner.php',
				],
			],

			'Pagely'         => [
				'check' => [
					'mu-plugins/pagely-assets/',
					'mu-plugins/pagely-cache-control/',
					'mu-plugins/pagely-management-v2.php',
				],
				'paths' => [
					'mu-plugins/pagely-app-stats/',
					'mu-plugins/pagely-assets/',
					'mu-plugins/pagely-cache-control/',
					'mu-plugins/pagely-cache-purge/',
					'mu-plugins/pagely-cdn/',
					'mu-plugins/pagely-cli/',
					'mu-plugins/pagely-plugin-upgrade-hooks/',
					'mu-plugins/pagely-security-patches/',
					'mu-plugins/pagely-site-health/',
					'mu-plugins/pagely-status/',
					'mu-plugins/pagely-util/',
					'mu-plugins/pagely-wp-cli-mail-patch/',
					'mu-plugins/pagely-management-v2.php',
					'mu-plugins/redis-cache-pro.php',
					'mu-plugins/pagely-cron.php',
					'mu-plugins/pagely-server-vars.php',
					'mu-plugins/db-config.php',
				],
			],

			'RunCloud'       => [
				'check' => [
					'mu-plugins/runcloud-hub.php',
				],
				'paths' => [
					'mu-plugins/runcloud-hub.php',
				],
			],

			'Pantheon'       => [
				'check' => [
					'mu-plugins/pantheon',
					'mu-plugins/pantheon.php',
				],
				'paths' => [
					'mu-plugins/pantheon',
					'mu-plugins/pantheon.php',
					'plugins/pantheon-hud',
					'plugins/pantheon-advanced-page-cache',
				],
			],

			'SiteGround'     => [
				'check' => [
					'plugins/sg-cachepress',
				],
				'paths' => [
					'plugins/sg-cachepress',
				],
			],

			// AKA Synthesis.
			'StudioPress'    => [
				'check' => [
					'mu-plugins/sp-affiliates',
					'mu-plugins/sp-clear-cache',
					'mu-plugins/sp-purchase',
					'mu-plugins/sp-software-monitor',
					'mu-plugins/sp-software-monitor',
					'mu-plugins/scribesysmsg',
					'mu-plugins/synth-deploy',
					'mu-plugins/synthesis-block-login-attempts',
					'mu-plugins/synthesis-resource-monitor',
				],
				'paths' => [
					'mu-plugins/sp-affiliates',
					'mu-plugins/sp-clear-cache',
					'mu-plugins/sp-purchase',
					'mu-plugins/sp-software-monitor',
					'mu-plugins/sp-software-monitor',
					'mu-plugins/scribesysmsg',
					'mu-plugins/synth-deploy',
					'mu-plugins/synthesis-block-login-attempts',
					'mu-plugins/synthesis-resource-monitor',
					'mu-plugins/synthesis-security',
					'mu-plugins/synthesis-update-manager',
					'mu-plugins/sysmsg',
					'mu-plugins/mu-includes.php',
				],
			],

			/*
			 * @link https://wpengine.com/support/platform-settings/#WP_Engine_MU_Plugins
			 */
			'WP Engine'      => [
				'check' => [
					'mu-plugins/wpe-wp-sign-on-plugin.php',
					'mu-plugins/wpengine-common/',
					'mu-plugins/wpengine-security-auditor.php',
				],
				'paths' => [
					'mu-plugins/force-strong-passwords/',
					'mu-plugins/mu-plugin.php',
					'mu-plugins/slt-force-strong-passwords.php',
					'mu-plugins/stop-long-comments.php',
					'mu-plugins/wpe-wp-sign-on-plugin/',
					'mu-plugins/wpe-wp-sign-on-plugin.php',
					'mu-plugins/wpengine-common/',
					'mu-plugins/wpengine-security-auditor.php',
				],
			],

			'WP Redis'       => [
				'check' => [
					'mu-plugins/wp-redis',
					'mu-plugins/wp-redis.php',
				],
				'paths' => [
					'mu-plugins/wp-redis',
					'mu-plugins/wp-redis.php',
				],
			],
		];
	}
}
