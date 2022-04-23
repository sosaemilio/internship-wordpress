<?php

namespace Nexcess\MAPPS\Commands;

use Nexcess\MAPPS\Exceptions\FilesystemException;
use Nexcess\MAPPS\Services\MigrationCleaner;

/**
 * Commands to aid post-migration.
 */
class Migration extends Command {

	/**
	 * @var \Nexcess\MAPPS\Services\MigrationCleaner
	 */
	private $migrationCleaner;

	/**
	 * Create a new instance of the command.
	 *
	 * @param \Nexcess\MAPPS\Services\MigrationCleaner $cleaner The MigrationCleaner service.
	 */
	public function __construct( MigrationCleaner $cleaner ) {
		$this->migrationCleaner = $cleaner;
	}

	/**
	 * Clean up a site after migrating from another platform.
	 *
	 * ## OPTIONS
	 *
	 * [--version=<version>]
	 * : Specify the WordPress version for checksums and, if necessary, re-installing core files.
	 * Defaults to the current value of `$wp_version`.
	 *
	 * ## EXAMPLES
	 *
	 *   # Clean up migration artifacts and core files.
	 *   wp nxmapps migration clean
	 *
	 *   # Clean up migration artifacts and core files for WordPress 5.6.
	 *   wp nxmapps migration clean --version=5.6
	 *
	 * @synopsis [--version=<version>]
	 *
	 * @global $wp_version
	 *
	 * @param mixed[] $args       Positional arguments.
	 * @param mixed[] $assoc_args Associative arguments.
	 */
	public function clean( array $args, array $assoc_args ) {
		global $wp_version;

		$version = ! empty( $assoc_args['version'] ) ? $assoc_args['version'] : $wp_version;

		$this->step( 'Cleaning up any leftover constants and files' );

		try {
			$artifacts = $this->migrationCleaner->clean();
		} catch ( FilesystemException $e ) {
			$this->error( $e->getMessage(), 1 );
		}

		if ( ! empty( $artifacts['constants'] ) ) {
			array_walk( $artifacts['constants'], function ( &$reason, $constant ) {
				$reason = sprintf( '%1$s (%2$s)', $constant, $reason );
			} );

			$this->success( 'The following constants have been removed:' )
				->listing( $artifacts['constants'] );
		}

		if ( ! empty( $artifacts['files'] ) ) {
			$this->success( 'The following migration artifacts have been removed:' )
				->listing( $artifacts['files'] );
		}

		$this->step( sprintf( 'Verifying core checksums (version %s)', $version ) );

		$verify = $this->wp( sprintf( 'core verify-checksums --version=%s', escapeshellarg( $version ) ), [
			'launch' => true,
			'return' => 'return_code',
		] );

		if ( 0 === $verify ) {
			$this->success( 'Core checksums have been validated.' );
		} else {
			$this->log( sprintf( 'Verification failed, re-downloading WordPress core (version %s)', $version ) )
				->wp( sprintf( 'core download --path=%s --version=%s --force', escapeshellarg( ABSPATH ), escapeshellarg( $version ) ), [
					'exit_error' => true,
				] );
		}
	}

	/**
	 * Scan for any known migration artifacts.
	 *
	 * ## OPTIONS
	 *
	 * [--version=<version>]
	 * : Specify the WordPress version to use for checksum verification.
	 * Defaults to the value of `$wp_version`.
	 *
	 * ## EXAMPLES
	 *
	 *   # Detect any migration artifacts or modified core files.
	 *   wp nxmapps migration scan
	 *
	 *   # Detect migration artifacts and verify core files against WordPress 5.6 checksums.
	 *   wp nxmapps migration scan --version=5.6
	 *
	 * @synopsis [--version=<version>]
	 *
	 * @global $wp_version
	 *
	 * @param mixed[] $args       Positional arguments.
	 * @param mixed[] $assoc_args Associative arguments.
	 */
	public function scan( $args, $assoc_args ) {
		global $wp_version;

		$version = ! empty( $assoc_args['version'] ) ? $assoc_args['version'] : $wp_version;

		$this->step( 'Looking for leftover files' );

		try {
			$artifacts = $this->migrationCleaner->scan();
		} catch ( FilesystemException $e ) {
			$this->error( $e->getMessage(), 1 );
		}

		if ( empty( $artifacts['constants'] ) ) {
			$this->success( 'No broken constants have been detected.' );
		} else {
			array_walk( $artifacts['constants'], function ( &$reason, $constant ) {
				$reason = sprintf( '%1$s (%2$s)', $constant, $reason );
			} );

			$this->warning( 'The following broken constants were detected:' )
				->listing( $artifacts['constants'] );
		}

		if ( empty( $artifacts['files'] ) ) {
			$this->success( 'No migration artifacts found from the following hosts:' )
				->listing( $this->migrationCleaner->getSupportedHosts() );
		} else {
			$this->warning( sprintf( 'Migration artifacts from %d host(s) have been detected:', count( $artifacts['files'] ) ) )
				->listing( $artifacts['files'] );
		}

		$this->step( sprintf( 'Verifying core checksums (version %s)', $version ) );

		$verify = $this->wp( sprintf( 'core verify-checksums --version=%s', escapeshellarg( $version ) ), [
			'launch' => true,
			'return' => 'all',
		] );

		if ( 0 === $verify->return_code ) {
			$this->success( 'No modifications to WordPress core have been detected.' );
		} else {
			preg_match_all( '/File doesn\'t verify against checksum: (.+)/i', $verify->stderr, $core );
			$this->warning( sprintf( 'The following core files have been modified (WordPress version %s):', $version ) )
				->listing( $core[1] );
		}

		// Everything checks out, so we can return safely.
		if ( empty( $artifacts['constants'] ) && empty( $artifacts['files'] ) && 0 === $verify->return_code ) {
			return;
		}

		$this->newline()
			->error( 'Migration artifacts have been detected, please run `wp nxmapps migration clean` to clean them.', 1 );
	}
}
