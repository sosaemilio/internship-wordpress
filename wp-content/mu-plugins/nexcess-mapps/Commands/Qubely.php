<?php

namespace Nexcess\MAPPS\Commands;

use WP_CLI;

/**
 * Integration with Qubely Pro by Themium.
 */
class Qubely {

	/**
	 * Activate a Qubely Pro license for the site.
	 *
	 * ## OPTIONS
	 *
	 * <license>
	 * : The Qubely Pro license key.
	 *
	 * [--expires_at=<date>]
	 * : Date the license is set to expire, in YYYY-MM-DD format.
	 * ---
	 * default: 2099-12-31
	 * ---
	 *
	 * [--license_to=<name>]
	 * : The name to show on the license.
	 * ---
	 * default: Nexcess Customer
	 * ---
	 *
	 * ## EXAMPLES
	 *
	 *   $ wp nxmapps qubely activate <license-key>
	 *
	 * @param mixed[] $args       Positional arguments.
	 * @param mixed[] $assoc_args Associative arguments/options passed to the command.
	 */
	public function activate( $args, $assoc_args ) {
		$license_key = $args[0];
		$assoc_args  = wp_parse_args( $assoc_args, [
			'expires_at' => '2099-12-31',
			'license_to' => 'Nexcess Customer',
		] );

		// Ensure the necessary constant is available.
		if ( ! defined( 'QUBELY_PRO_BASENAME' ) ) {
			return WP_CLI::error( __(
				'The QUBELY_PRO_BASENAME constant is undefined, unable to proceed. Is Qubely Pro installed and activated?',
				'nexcess-mapps'
			) );
		}

		// Validate expiration dates.
		if ( gmdate( 'Y-m-d', strtotime( $assoc_args['expires_at'] ) ) !== $assoc_args['expires_at'] ) {
			return WP_CLI::error( sprintf(
				/* Translators: %1$s is the provided value for --expires_at */
				__( '--expires_at should be in YYYY-MM-DD format, "%1$s" provided.', 'nexcess-mapps' ),
				$assoc_args['expires_at']
			) );
		}

		update_option( QUBELY_PRO_BASENAME . '_license_info', [
			'activated'    => true,
			'license_key'  => sanitize_text_field( $license_key ),
			'license_to'   => sanitize_text_field( $assoc_args['license_to'] ),
			'expires_at'   => sanitize_text_field( $assoc_args['expires_at'] ),
			'activated_at' => current_datetime()->format( 'Y-m-d' ),
			'msg'          => __( 'License key successfully verified and activated', 'nexcess-mapps' ),
		] );

		WP_CLI::success( sprintf(
			/* Translators: %1$s is the licensee, %2$s is the license key. */
			__( 'Qubely Pro has been licensed to "%1$s" with key %2$s.', 'nexcess-mapps' ),
			$assoc_args['license_to'],
			$license_key
		) );
	}

	/**
	 * Deactivate a Qubely Pro license for the site.
	 *
	 * ## EXAMPLES
	 *
	 *   $ wp nxmapps qubely deactivate
	 */
	public function deactivate() {
		if ( ! defined( 'QUBELY_PRO_BASENAME' ) ) {
			return WP_CLI::error( 'The QUBELY_PRO_BASENAME constant is undefined, unable to proceed. Is Qubely Pro installed and activated?' );
		}

		delete_option( QUBELY_PRO_BASENAME . '_license_info' );

		WP_CLI::success( __( 'The Qubely Pro license has been removed.', 'nexcess-mapps' ) );
	}
}
