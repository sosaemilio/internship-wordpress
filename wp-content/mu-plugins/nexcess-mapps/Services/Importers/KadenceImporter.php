<?php

/**
 * A wrapper around the Kadence Starter Templates plugin.
 *
 * @link https://wordpress.org/plugins/kadence-starter-templates/
 */

namespace Nexcess\MAPPS\Services\Importers;

use Kadence_Starter_Templates\Starter_Templates;

class KadenceImporter {

	/**
	 * Import a template using Kadence Starter Templates.
	 *
	 * The plugin uses a series of ajax calls in order to import a site, but that's not helpful
	 * when we're running this programmatically.
	 *
	 * This method will emulate an ajax request and capture calls to `wp_send_json()`, using them
	 * to advance the process.
	 *
	 * @param string $template The template ID.
	 * @param string $builder  The builder being used. One of "blocks", "elementor", or "custom".
	 */
	public function import( $template, $builder ) {
		add_filter( 'wp_doing_ajax', '__return_true' );
		add_filter( 'wp_die_ajax_handler', function () {
			return [ $this, 'proceedToNextStep' ];
		} );

		// Extend timeouts since this won't be run via Ajax requests.
		add_filter( 'kadence-starter-templates/timeout_for_downloading_import_file', [ $this, 'getRequestTimeout' ] );
		add_filter( 'kadence-starter-templates/time_for_one_ajax_call', [ $this, 'getRequestTimeout' ] );

		// Required to pass nonce verification.
		$_REQUEST = [
			'security' => wp_create_nonce( 'kadence-ajax-verification' ),
		];

		// Add selected options into superglobals.
		$_POST = [
			'selected' => $template,
			'builder'  => $builder,
		];

		ob_start();
		Starter_Templates::get_instance()->install_plugins_ajax_callback();
		ob_end_flush();
	}

	/**
	 * Get the timeout for fake Ajax requests.
	 *
	 * @return int The number of seconds a fake Ajax call can last.
	 */
	public function getRequestTimeout() {
		return HOUR_IN_SECONDS;
	}

	/**
	 * Handle calls to wp_send_json().
	 *
	 * Kadence Starter Templates makes use of wp_send_json() often, which implicitly exits the
	 * script, which can be problematic when trying to run multiple jobs in sequence.
	 *
	 * This method replaces the standard "wp_die_ajax_handler" callback with one that will run the
	 * next step in the process.
	 */
	public function proceedToNextStep() {
		$message = json_decode( (string) ob_get_contents() );

		// If there's anything in the output buffer, clear it out.
		if ( ob_get_length() ) {
			ob_clean();
		}

		if ( empty( $message->status ) ) {
			return;
		}

		// If we've received a known "status", call the corresponding method.
		switch ( $message->status ) {
			case 'pluginSuccess':
				return Starter_Templates::get_instance()->import_demo_data_ajax_callback();

			case 'customizerAJAX':
				$_POST = [
					'wp_customize' => 'on',
				];

				return Starter_Templates::get_instance()->import_customizer_data_ajax_callback();

			case 'afterAllImportAJAX':
				return Starter_Templates::get_instance()->after_all_import_data_ajax_callback();
		}
	}
}
