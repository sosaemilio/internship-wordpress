<?php

namespace Nexcess\MAPPS\Services;

use Nexcess\MAPPS\Concerns\InvokesCli;
use Nexcess\MAPPS\Concerns\MakesHttpRequests;
use Nexcess\MAPPS\Concerns\QueriesMAPPS;
use Nexcess\MAPPS\Exceptions\ConsoleException;
use Nexcess\MAPPS\Exceptions\InstallationException;
use Nexcess\MAPPS\Exceptions\LicensingException;
use Nexcess\MAPPS\Exceptions\MappsApiException;
use Nexcess\MAPPS\Settings;
use Nexcess\MAPPS\Support\ConsoleCommand;

class Installer {
	use InvokesCli;
	use MakesHttpRequests;
	use QueriesMAPPS;

	/**
	 * @var \Nexcess\MAPPS\Services\Logger
	 */
	private $logger;

	/**
	 * @var \Nexcess\MAPPS\Settings
	 */
	private $settings;

	/**
	 * The cache key used for retrieving available plugins.
	 */
	const AVAILABLE_PLUGINS_CACHE_KEY = 'nexcess-mapps-installer-plugins';

	/**
	 * Construct the Installer instance.
	 *
	 * @param \Nexcess\MAPPS\Settings        $settings The Settings object for this site.
	 * @param \Nexcess\MAPPS\Services\Logger $logger   The Logger instance.
	 */
	public function __construct( Settings $settings, Logger $logger ) {
		$this->settings = $settings;
		$this->logger   = $logger;
	}

	/**
	 * Retrieve a list of installable plugins.
	 *
	 * @throws \Nexcess\MAPPS\Exceptions\MappsApiException If an unexpected response is returned
	 *                                                     from the MAPPS API.
	 *
	 * @return object[] An array of installable plugins.
	 */
	public function getAvailablePlugins() {
		try {
			$body = remember_transient( self::AVAILABLE_PLUGINS_CACHE_KEY, function () {
				return $this->validateHttpResponse( $this->mappsApi( 'v1/app-plugin' ), 200 );
			}, 5 * MINUTE_IN_SECONDS );
		} catch ( \Exception $e ) {
			throw new MappsApiException( $e->getMessage(), $e->getCode(), $e );
		}

		return json_decode( $body, false ) ?: [];
	}

	/**
	 * Retrieve a list of plugins that should be pre-installed on the site.
	 *
	 * The API endpoint will use the MAPPS API token, and the results will change based on the API
	 * token used.
	 *
	 * @throws \Nexcess\MAPPS\Exceptions\MappsApiException If an unexpected response is returned
	 *                                                     from the MAPPS API.
	 *
	 * @return object[] An array of plugins that should be pre-installed. This may be empty if no
	 *                  plugins should be pre-configured.
	 */
	public function getPreinstallPlugins() {
		try {
			$body = $this->validateHttpResponse( $this->mappsApi( 'v1/app-plugin/setup' ), 200 );
		} catch ( \Exception $e ) {
			throw new MappsApiException( $e->getMessage(), $e->getCode(), $e );
		}

		return json_decode( $body, false ) ?: [];
	}

	/**
	 * Get details about one of the installable plugins.
	 *
	 * @param int $id The plugin ID, derived from $this->getAvailablePlugins().
	 *
	 * @throws \Nexcess\MAPPS\Exceptions\MappsApiException If an unexpected response is returned
	 *                                                     from the MAPPS API.
	 *
	 * @return object The plugin details.
	 */
	public function getPluginDetails( $id ) {
		try {
			$response = $this->mappsApi( sprintf( 'v1/app-plugin/%d/install', $id ) );
			$body     = $this->validateHttpResponse( $response, 200 );
		} catch ( \Exception $e ) {
			throw new MappsApiException( $e->getMessage(), $e->getCode(), $e );
		}

		return json_decode( $body, false ) ?: (object) [];
	}

	/**
	 * Get licensing instructions for one of the installable plugins.
	 *
	 * @param int $id The plugin ID, derived from $this->getAvailablePlugins().
	 *
	 * @throws \Nexcess\MAPPS\Exceptions\MappsApiException If an unexpected response is returned.
	 *
	 * @return object The licensing instructions.
	 */
	public function getPluginLicensing( $id ) {
		try {
			$response = $this->mappsApi( sprintf( 'v1/app-plugin/%d/license', $id ), [
				'timeout' => 60, // Licensing often depends on outside services.
			] );
			$body     = $this->validateHttpResponse( $response, 200 );
		} catch ( \Exception $e ) {
			throw new MappsApiException( $e->getMessage(), $e->getCode(), $e );
		}

		return json_decode( $body, false ) ?: (object) [];
	}

	/**
	 * Install a single plugin.
	 *
	 * @param int $id The plugin/theme ID to install.
	 *
	 * @throws \Nexcess\MAPPS\Exceptions\InstallationException If the installation request fails.
	 *
	 * @return bool Will return true if nothing went wrong during installation.
	 */
	public function install( $id ) {
		$install_steps = [
			'pre_install_script',
			'install',
			'post_install_script',
		];

		try {
			$details = $this->getPluginDetails( $id );

			foreach ( $install_steps as $step ) {
				if ( empty( $details->install_script->plugin->{$step} ) ) {
					continue;
				}

				$this->handleInstallationStep( $details->install_script->plugin->{$step} );
			}
		} catch ( \Exception $e ) {
			throw new InstallationException( sprintf(
				'Unable to install asset with ID %1$d: %2$s',
				$id,
				$e->getMessage()
			), $e->getCode(), $e );
		}

		return true;
	}

	/**
	 * License a single plugin.
	 *
	 * @param int $id The plugin/theme ID to install.
	 *
	 * @throws \Nexcess\MAPPS\Exceptions\LicensingException If the licensing request fails.
	 *
	 * @return bool Will return true if nothing went wrong during licensing.
	 */
	public function license( $id ) {
		$licensing_steps = [
			'pre_licensing_script',
			'licensing_script',
			'post_licensing_script',
		];

		try {
			$details = $this->getPluginLicensing( $id );

			foreach ( $licensing_steps as $step ) {
				if ( empty( $details->licensing_script->plugin->{$step} ) ) {
					continue;
				}

				$this->handleLicensingStep( $details->licensing_script->plugin->{$step} );
			}
		} catch ( \Exception $e ) {
			throw new LicensingException( sprintf(
				'Unable to license asset with ID %1$d: %2$s',
				$id,
				$e->getMessage()
			), $e->getCode(), $e );
		}

		return true;
	}

	/**
	 * Handle a single installation step.
	 *
	 * This includes pre- and post-install commands, as well as the primary installation method.
	 *
	 * @param object $instructions The instructions to execute.
	 *
	 * @throws \Nexcess\MAPPS\Exceptions\InstallationException If the installation step fails.
	 */
	protected function handleInstallationStep( $instructions ) {
		$command = false;

		// Install from WordPress.org via WP-CLI.
		if ( ! empty( $instructions->wp_package ) ) {
			$command = $this->makeCommand( 'wp plugin install', [
				$instructions->wp_package,
				'--activate' => true,
			] );
		} elseif ( ! empty( $instructions->{'wp-package'} ) ) {
			$command = $this->makeCommand( 'wp plugin install', [
				$instructions->{'wp-package'},
				'--activate' => true,
			] );
		} elseif ( ! empty( $instructions->wp_theme ) ) {
			$command = $this->makeCommand( 'wp theme install', [
				$instructions->wp_theme,
				'--activate' => true,
			] );
		} elseif ( ! empty( $instructions->source ) ) {
			$command = $this->makeCommand( 'wp plugin install', [
				$instructions->source,
				'--activate' => true,
			] );
		}

		if ( $command ) {
			$this->installViaWpCli( $command );
		}
	}

	/**
	 * Handle a single licensing step.
	 *
	 * @param object $instructions The instructions to execute.
	 *
	 * @throws \Nexcess\MAPPS\Exceptions\LicensingException If the licensing step fails.
	 */
	protected function handleLicensingStep( $instructions ) {
		// Run a WP-CLI command.
		if ( ! empty( $instructions->wp_cli ) ) {
			try {
				// The API currently returns the wrong command for licensing Brainstorm Force plugins.
				$command = str_replace(
					'brainstormforce license',
					'nxmapps brainstormforce',
					$instructions->wp_cli
				);

				// Ensure commands are prefixed with "wp ".
				if ( 'wp ' !== substr( trim( $command ), 0, 3 ) ) {
					$command = 'wp ' . trim( $command );
				}

				$this->makeCommand( $command )
					->setPriority( 10 )
					->setTimeout( 60 )
					->execute()
					->wasSuccessful( true );
			} catch ( ConsoleException $e ) {
				throw new LicensingException( sprintf(
					/* Translators: %1$s is the error message. */
					__( 'Unable to license plugin: %1$s', 'nexcess-mapps' ),
					$e->getMessage()
				), $e->getCode(), $e );
			}
		}

		// Set option(s).
		if ( ! empty( $instructions->wp_option ) ) {
			foreach ( (array) $instructions->wp_option as $key => $value ) {
				update_option( $key, $value );
			}
		}
	}

	/**
	 * Log an exception that may have come up.
	 *
	 * @param \Exception $e An Exception object.
	 */
	protected function logException( \Exception $e ) {
		$this->logger->error( sprintf( 'Installer error: %1$s', $e->getMessage() ), [
			'exception' => $e,
		] );
	}

	/**
	 * Execute a WP-CLI command to install an add-on.
	 *
	 * @param ConsoleCommand $command The WP-CLI command to execute.
	 *
	 * @throws InstallationException If the installation step fails.
	 */
	private function installViaWpCli( ConsoleCommand $command ) {
		try {
			$command->setPriority( 10 )
				->setTimeout( 60 )
				->execute()
				->wasSuccessful( true );
		} catch ( ConsoleException $e ) {
			throw new InstallationException( sprintf(
				/* Translators: %1$s is the previous exception message. */
				__( 'Unable to install: %1$s', 'nexcess-mapps' ),
				$e->getMessage()
			), $e->getCode(), $e );
		}
	}

}
