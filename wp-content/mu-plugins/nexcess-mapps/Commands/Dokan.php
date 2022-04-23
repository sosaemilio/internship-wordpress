<?php

namespace Nexcess\MAPPS\Commands;

use WP_CLI;

/**
 * Manage licensing for Dokan by weDevs.
 *
 * ## REFERENCE
 *
 * https://wedevs.com/dokan/
 */
class Dokan {

	/**
	 * Add a Dokan license for the site.
	 *
	 * This will set both the "dokan_license" and "dokan_license status" options in the wp_options table.
	 *
	 * ## OPTIONS
	 *
	 * <email>
	 * : The customer email address.
	 *
	 * <license>
	 * : The Dokan license key.
	 *
	 * ## EXAMPLES
	 *
	 *   $ wp nxmapps dokan activate test@example.com abc123
	 *
	 * @param mixed[] $args Positional arguments.
	 */
	public function activate( $args ) {
		list($email, $license) = $args;

		// Dokan is expecting these values as JSON, not serialized data.
		update_option( 'dokan_license', wp_json_encode( [
			'email' => $email,
			'key'   => $license,
		] ) );

		update_option( 'dokan_license_status', (object) [
			'activated' => true,
		] );

		WP_CLI::success( sprintf(
			/* Translators: %1$s is the licensee, %2$s is the license key. */
			__( 'Dokan has been licensed to "%1$s" with license key "%2$s".', 'nexcess-mapps' ),
			$email,
			$license
		) );
	}

	/**
	 * Deactivate the Dokan license for the site.
	 */
	public function deactivate() {
		delete_option( 'dokan_license' );
		delete_option( 'dokan_license_status' );

		WP_CLI::success( __( 'The Dokan license has been removed.', 'nexcess-mapps' ) );
	}
}
