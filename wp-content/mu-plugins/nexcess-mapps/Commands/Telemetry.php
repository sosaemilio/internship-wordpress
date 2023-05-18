<?php

namespace Nexcess\MAPPS\Commands;

use Nexcess\MAPPS\Integrations\Telemetry as Integration;

/**
 * WP-CLI methods for Nexcess support.
 */
class Telemetry extends Command {

	/**
	 * @var \Nexcess\MAPPS\Integrations\Telemetry
	 */
	protected $integration;

	/**
	 * Create a new instance of the command.
	 *
	 * @param \Nexcess\MAPPS\Integrations\Telemetry $integration The Telemetry integration.
	 */
	public function __construct( Integration $integration ) {
		$this->integration = $integration;
	}

	/**
	 * Output the collected telemetry data.
	 *
	 * ## OPTIONS
	 *
	 * [--ignore-plugins]
	 * : Remove the plugins from the output to allow reading the other collected metrics easier.
	 *
	 * @synopsis [--ignore-plugins]
	 *
	 * @param mixed[] $args       Positional arguments.
	 * @param mixed[] $assoc_args Associative arguments.
	 */
	public function data( $args, $assoc_args ) {
		$ignore_plugins = ! empty( $assoc_args['ignore-plugins'] );
		$telemetry_data = $this->integration->collectTelemetryData();

		if ( $ignore_plugins ) {
			unset( $telemetry_data['plugins'] );
		}
		print_r( $telemetry_data ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_print_r
	}

}
