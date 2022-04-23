<?php

namespace Nexcess\MAPPS\Commands;

use Nexcess\MAPPS\Exceptions\InstallationException;
use Nexcess\MAPPS\Exceptions\LicensingException;
use Nexcess\MAPPS\Services\Installer as InstallerService;

/**
 * WP-CLI commands for the Nexcess Installer.
 */
class Installer extends Command {

	/**
	 * @var InstallerService
	 */
	protected $installer;

	/**
	 * Construct an instance of the command.
	 *
	 * @param InstallerService $installer
	 */
	public function __construct( InstallerService $installer ) {
		$this->installer = $installer;
	}

	/**
	 * Install and activate a plugin by ID.
	 *
	 * ## OPTIONS
	 *
	 * [<id>...]
	 * : One or more plugins to install, license (where applicable) and activate.
	 *
	 * ## EXAMPLES
	 *
	 * # Install plugin IDs 4, 8, 15, 16, 23, and 42.
	 * $ wp nxmapps installer install 4 8 15 16 23 42
	 *
	 * @param mixed[] $args       Positional arguments.
	 * @param mixed[] $assoc_args Associative arguments/options passed to the command.
	 */
	public function install( array $args, array $assoc_args ) {
		$licensing_failed = false;
		$counts           = [
			'installed' => 0,
			'total'     => count( $args ),
		];

		foreach ( $args as $id ) {
			try {
				$plugin = $this->installer->getPluginDetails( $id );
			} catch ( \Exception $e ) {
				$this->error( sprintf( 'Unable to install plugin #%d', $id ), false );
				continue;
			}

			$identity = isset( $plugin->identity ) ? $plugin->identity : '(unknown plugin id ' . $id . ')';
			$this->step( sprintf( 'Installing %s', $identity ) );
			try {
				$this->installer->install( $id );
				$counts['installed']++;
			} catch ( InstallationException $e ) {
				$this->error( sprintf( 'Unable to install "%1$s" (#%2$d): %3$s', $identity, $id, $e->getMessage() ), false );
				continue;
			}

			if ( isset( $plugin->license_type ) && 'none' !== $plugin->license_type ) {
				try {
					$this->installer->license( $id );
				} catch ( LicensingException $e ) {
					$this->error( sprintf( 'Unable to license "%1$s" (#2$d): %3$s', $identity, $id, $e->getMessage() ), false );
					$licensing_failed = true;
				}
			}
		}

		if ( 0 === $counts['installed'] ) {
			return $this->error( 'No plugins were installed!', 1 );
		}

		if ( $counts['total'] !== $counts['installed'] ) {
			return $this->error( sprintf( 'Only %1$d/%2$d plugins were installed', $counts['installed'], $counts['total'] ), 2 );
		}

		if ( $licensing_failed ) {
			return $this->error( 'Licensing failed for one or more plugins', 3 );
		}

		$this->success( 'Plugins installed successfully!' );
	}
}
