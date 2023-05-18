<?php

/**
 * Manage drop-in files.
 */

namespace Nexcess\MAPPS\Services;

use Nexcess\MAPPS\Support\Filesystem;
use Nexcess\MAPPS\Support\Helpers;
use StellarWP\PluginFramework\Exceptions\InvalidDropInException;

class DropIn {

	/**
	 * @var \Nexcess\MAPPS\Services\Logger
	 */
	protected $logger;

	/**
	 * Create a new instance of the service.
	 *
	 * @param \Nexcess\MAPPS\Services\Logger $logger
	 */
	public function __construct( Logger $logger ) {
		$this->logger = $logger;
	}

	/**
	 * Clean up any broken drop-ins.
	 *
	 * We'll define a drop-in as "broken" if any of the following are true:
	 *
	 * 1. The drop-in file exists, but is empty.
	 * 2. The drop-in is a broken symlink.
	 */
	public function cleanBrokenDropIns() {
		$fs       = Filesystem::init();
		$drop_ins = _get_dropins();

		foreach ( $drop_ins as $drop_in => $description ) {
			$path = WP_CONTENT_DIR . '/' . $drop_in;

			// Empty drop-in files.
			if ( $fs->is_readable( $path ) && 0 === $fs->size( $path ) ) {
				$this->logger->notice( sprintf( 'Removing empty drop-in file %1$s.', $drop_in ) );
				$fs->delete( $path, false, 'f' );
				continue;
			}

			// Broken symlinks.
			if ( Helpers::isBrokenSymlink( $path ) ) {
				$this->logger->notice( sprintf(
					'Removing %1$s, as it points to a non-existent path %2$s.',
					$drop_in,
					readlink( $path )
				) );
				$fs->delete( $path, false, 'f' );
				continue;
			}
		}
	}

	/**
	 * Install a drop-in file via symlink.
	 *
	 * @param string $drop_in The drop-in filename.
	 * @param string $source  The source file.
	 * @param bool   $force   Optional. Whether or not to overwrite the drop-in if it already
	 *                        exists. Default is false.
	 *
	 * @return bool Whether or not the drop-in was installed successfully. Will return true if the
	 *              requested symlink already exists.
	 */
	public function install( $drop_in, $source, $force = false ) {
		try {
			$target = $this->validateDropIn( $drop_in );
		} catch ( InvalidDropInException $e ) {
			$this->logger->warning( $e->getMessage() );
			return false;
		}

		// Ensure the $source file exists.
		if ( ! file_exists( $source ) ) {
			return false;
		}

		// Verify the target isn't already present.
		if ( file_exists( $target ) ) {
			if ( $force ) {
				unlink( $target );
			} else {
				if ( is_link( $target ) ) {
					return readlink( $target ) === $source;
				}

				return false;
			}
		}

		// If it's a broken symlink, clean it up.
		if ( Helpers::isBrokenSymlink( $target ) ) {
			unlink( $target );
		}

		return symlink( $source, $target );
	}

	/**
	 * Remove the given drop-in file.
	 *
	 * @param string  $drop_in The drop-in file.
	 * @param ?string $source  Optional. If provided, the symlink will only be removed if it
	 *                         points to this absolute path. Default is empty.
	 * @param bool    $force   Optional. Force the removal of the file, even if it isn't a symlink.
	 *                         Default is false.
	 *
	 * @return bool Whether or not the drop-in was removed successfully. Will return true if the
	 *              specified drop-in didn't exist to begin with.
	 */
	public function remove( $drop_in, $source = null, $force = false ) {
		try {
			$target = $this->validateDropIn( $drop_in );
		} catch ( InvalidDropInException $e ) {
			$this->logger->warning( $e->getMessage() );
			return false;
		}

		$is_broken = Helpers::isBrokenSymlink( $target );

		// The target doesn't exist, so there's nothing to do.
		if ( ! file_exists( $target ) && ! $is_broken ) {
			return true;
		}

		// Don't remove normal files.
		if ( file_exists( $target ) && ! is_link( $target ) && ! $force ) {
			return false;
		}

		// If a $source is provided, validate the linked file.
		if ( ! empty( $source ) && ! $is_broken && is_link( $target ) && readlink( $target ) !== $source ) {
			return false;
		}

		return unlink( $target );
	}

	/**
	 * Validate known drop-in files.
	 *
	 * @param string $drop_in The drop-in being referenced. Can accept the file with or without the
	 *                        ".php" file extension.
	 *
	 * @throws InvalidDropInException If the given $drop_in is unrecognized.
	 *
	 * @return string The full system path to the given drop-in file.
	 */
	protected function validateDropIn( $drop_in ) {
		// Ensure we have the ".php" suffix.
		$drop_in       = basename( $drop_in, '.php' ) . '.php';
		$valid_dropins = _get_dropins();

		if ( ! isset( $valid_dropins[ $drop_in ] ) ) {
			throw new InvalidDropInException( sprintf(
				'%1$s is not a valid WordPress drop-in.',
				$drop_in
			) );
		}

		return sprintf( '%s/%s', WP_CONTENT_DIR, $drop_in );
	}
}
