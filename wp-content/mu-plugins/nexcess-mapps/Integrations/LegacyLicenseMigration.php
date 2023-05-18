<?php

/**
 * Integration to migrate legacy product licenses.
 */

namespace Nexcess\MAPPS\Integrations;

use Nexcess\MAPPS\Concerns\HasCronEvents;
use Nexcess\MAPPS\Concerns\HasWordPressDependencies;
use Nexcess\MAPPS\Concerns\ManagesGroupedOptions;
use Nexcess\MAPPS\Services\Installer;

class LegacyLicenseMigration extends Integration {
	use HasCronEvents;
	use HasWordPressDependencies;
	use ManagesGroupedOptions;

	/**
	 * The daily cron action name.
	 */
	const DAILY_LICENSE_SWAP_CRON_ACTION = 'nexcess_mapps_daily_license_swap';

	/**
	 * License check action to prevent the check from running multiple times in one request.
	 */
	const LICENSE_CHECK_ACTION = 'Nexcess\\MAPPS\\Integrations\\LegacyLicenseMigration\\LicenseUpgradeCheck';

	/**
	 * The grouped setting option name.
	 */
	const OPTION_NAME = '_nexcess_licenses';

	/**
	 * @var \Nexcess\MAPPS\Services\Installer
	 */
	protected $installer;

	/**
	 * @var \Nexcess\MAPPS\Integrations\BrainstormForce
	 */
	protected $brainstorm;

	/**
	 * Rather than hardcoding license keys here to compare, we're using the sha256 hash of the license key.
	 * The easiest way to do this is to run this in your terminal: `echo -n "license key" | sha256sum`, and then copy the output.
	 *
	 * @var array
	 */
	protected $licenses_to_replace = [
		'astra-addon'       => '2A0FAB202FDB88660AAD7D73916AED7BC251F1AE08D20BB507EAA4A1614B9CA1',
		'convertpro'        => '63EB82DC24AD4211B2F11DA43048DB388A34315586AA53816B48EBFD9082ABC3',
		'bb-ultimate-addon' => '16E3F691FEBE534CFAF454DDB43231E699A9173074EAA7A6059E178512B5D5B1',
	];

	/**
	 * Hardcode the IDs used by the installer API, seperated by install type.
	 *
	 * @var array
	 */
	protected $product_types = [
		// todo: grab these dynamically - prior art in plugin framework from stellarwp.
		'brainstorm' => [
			'astra-addon'       => 20,
			'convertpro'        => 21,
			'bb-ultimate-addon' => 22,
		],
	];

	/**
	 * @param \Nexcess\MAPPS\Services\Installer           $installer
	 * @param \Nexcess\MAPPS\Integrations\BrainstormForce $brainstorm
	 */
	public function __construct( Installer $installer, BrainstormForce $brainstorm ) {
		$this->installer  = $installer;
		$this->brainstorm = $brainstorm;
	}

	/**
	 * Determine whether or not this integration should be loaded.
	 *
	 * @return bool Whether or not this integration be loaded in this environment.
	 */
	public function shouldLoadIntegration() {
		$should_load = false;
		// Make sure we're running at least one of the applicable plugins.
		foreach ( $this->licenses_to_replace as $slug => $license ) {
			if ( $this->isPluginActive( "{$slug}/{$slug}.php" ) ) {
				$should_load = true;
				break;
			}
		}
		return $should_load;
	}

	/**
	 * Perform any necessary setup for the integration.
	 *
	 * This method is automatically called as part of Plugin::loadIntegration(), and is the
	 * entry-point for all integrations.
	 */
	public function setup() {

		$this->addHooks();

		// Register cron event if we haven't already updated.
		if ( ! is_array( $this->getOption()->did_update_license ) || count( $this->getOption()->did_update_license ) < 1 ) {
			$this->registerCronEvent( self::DAILY_LICENSE_SWAP_CRON_ACTION, 'daily' );
		}
	}

	/**
	 * Retrieve all actions for the integration.
	 *
	 * @return array[]
	 */
	public function getActions() {
		return [

			[ 'upgrader_process_complete', [ $this, 'checkLicenses' ] ],
			[ 'load-update-core.php', [ $this, 'checkLicenses' ] ],
			[ 'load-plugins.php', [ $this, 'checkLicenses' ] ],

			/*
			 * Daily operations:
			 *
			 * - Check for static license keys, and replace them with unique licenses.
			 */
			[ self::DAILY_LICENSE_SWAP_CRON_ACTION, [ $this, 'checkLicenses' ] ],
		];
	}

	/**
	 * Check the licenses for installed plugins, and if needed, update the the license.
	 */
	public function checkLicenses() {
		// Make sure that we don't run this action multiple times in one request.
		if ( did_action( self::LICENSE_CHECK_ACTION ) ) {
			return;
		}

		// Fire the action to avoid a loop.
		do_action( self::LICENSE_CHECK_ACTION );

		// If we've already updated the license, no need to check it and do it again.
		$did_update = ! empty( $this->getOption()->did_update_license ) ? $this->getOption()->did_update_license : [];

		$active_plugins = get_option( 'active_plugins' );

		foreach ( $active_plugins as $plugin ) {
			// The 'active_plugins' option gives us an array of <name>/<main-file>.php,
			// but we only need the <name>.
			$plugin_name = explode( '/', $plugin );

			// If we somehow don't have a plugin name, then skip it.
			if ( ! isset( $plugin_name[0] ) ) {
				continue;
			}

			// Allow skipping the processing of a license if needed.
			if ( apply_filters( 'Nexcess\\MAPPS\\Integrations\\LegacyLicenseMigration\\skip_license_check', false, $plugin_name[0] ) ) {
				continue;
			}

			// If we've already updated the license, no need to check it and do it again.
			if ( in_array( $plugin_name[0], $did_update, true ) ) {
				continue;
			}

			// If it's not one we're looking to replace, then skip it.
			if ( ! isset( $this->licenses_to_replace[ $plugin_name[0] ] ) ) {
				continue;
			}

			$license      = false;
			$product_type = false;

			// Grab the license key from the options table for each plugin.
			if ( in_array( $plugin_name[0], array_keys( $this->product_types['brainstorm'] ), true ) ) {
				$license      = $this->getBrainStormKey( 'bb-ultimate-addon' === $plugin_name[0] ? 'uabb' : $plugin_name[0] );
				$product_type = 'brainstorm';
			}

			// If we don't have a license or a product type, then skip it.
			if ( ! $license || ! $product_type ) {
				continue;
			}

			// If the license key is the same as the one we're looking for, then update it.
			if ( hash_equals( $this->licenses_to_replace[ $plugin_name[0] ], strtoupper( hash( 'sha256', $license ) ) ) ) {
				$this->updateLicense( $plugin_name[0], $product_type );
			}
		}
	}

	/**
	 * Grab the license key from the nested brainstorm option in the options table.
	 *
	 * @param string $slug Plugin to grab the license key for.
	 *
	 * @return false|string The license key, or false if it doesn't exist.
	 */
	public function getBrainStormKey( $slug ) {
		$brainstorm_options = get_option( BrainstormForce::OPTION_NAME );

		if (
			! $brainstorm_options
			|| ! isset( $brainstorm_options['plugins'] )
			|| ! isset( $brainstorm_options['plugins'][ $slug ] )
			|| ! isset( $brainstorm_options['plugins'][ $slug ]['purchase_key'] ) ) {
			return false;
		}

		return $brainstorm_options['plugins'][ $slug ]['purchase_key'];
	}

	/**
	 * Get the ID that is expected for the installer API for a given plugin.
	 *
	 * @param string $plugin       The plugin slug.
	 * @param string $product_type The product type, such as 'brainstorm'.
	 *
	 * @return false|int The ID that is expected for the installer API for a given plugin.
	 */
	public function getPluginID( $plugin, $product_type ) {
		if ( ! isset( $this->product_types[ $product_type ][ $plugin ] ) ) {
			return false;
		}

		return $this->product_types[ $product_type ][ $plugin ];
	}

	/**
	 * Update the license key for a given plugin.
	 *
	 * @param string $plugin       The plugin slug.
	 * @param string $product_type The product type, such as 'brainstorm'.
	 */
	public function updateLicense( $plugin, $product_type ) {
		$plugin_id = $this->getPluginID( $plugin, $product_type );

		if ( ! $plugin_id ) {
			return;
		}

		// This is required by Nocworx before calling the /license API endpoint - it can be disregarded.
		$plugin_install = $this->installer->getPluginDetails( $plugin_id );

		// Grab the licensing instructions for the plugin.
		$license_instructions = $this->installer->getPluginLicensing( $plugin_id );

		// Different plugins will need to update license differently, and we don't
		// want to run the entire set up licensing instructions, so we'll just
		// grab the license key from the instructions and update it that way.
		if ( 'brainstorm' === $product_type ) {
			$this->doBrainstormLicenseUpdate( $plugin, $license_instructions );
		}

		// Grab the list of updated licenses so we can add this plugin to it.
		$updated = $this->getOption()->did_update_license;

		// Save the time we updated the license.
		$updated[ $plugin ] = time();

		$this->getOption()->set( 'did_update_license', $updated )->save();
	}

	/**
	 * Update Brainstorm licenses for a plugin.
	 *
	 * @param string $plugin               The plugin name.
	 * @param object $license_instructions The license instructions from the MAPPS Dashboard plugin API.
	 */
	public function doBrainstormLicenseUpdate( $plugin, $license_instructions ) {
		// Check to make sure we've actually gotten the license instructions.
		if ( ! is_object( $license_instructions ) ) {
			return;
		}

		// Make sure that we've gotten the expected instructions to grab the license key.
		/** @phpstan-ignore-next-line */
		if ( ! isset( $license_instructions->licensing_script->plugin->licensing_script->wp_cli[0] ) ) {
			return;
		}

		// The API we're grabbing the key form is usually used to activate & license a plugin,
		// so the response it gives us is something similar to 'brainstormforce license activate <name> <key>,
		// and we only want the key, so we need to grab it out of there.
		$activate_command = $license_instructions->licensing_script->plugin->licensing_script->wp_cli[0];
		$command_parts    = explode( ' ', $activate_command );
		$new_key          = end( $command_parts );

		if ( ! $new_key ) {
			return;
		}

		// Use the existing Brainstorm integration to license the plugin.
		if ( 'bb-ultimate-addon' === $plugin ) {
			$plugin = 'uabb';
		}
		$licensed = $this->brainstorm->activate( $plugin, $new_key );

		// Clear the cron.
		if ( ! is_wp_error( $licensed ) ) {
			$timestamp = wp_next_scheduled( self::DAILY_LICENSE_SWAP_CRON_ACTION );
			if ( $timestamp ) {
				wp_unschedule_event( $timestamp, self::DAILY_LICENSE_SWAP_CRON_ACTION );
			}
		}
	}
}
