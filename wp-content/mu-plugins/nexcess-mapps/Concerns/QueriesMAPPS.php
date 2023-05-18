<?php

namespace Nexcess\MAPPS\Concerns;

use WP_Error;

use const Nexcess\MAPPS\PLUGIN_VERSION;

trait QueriesMAPPS {

	/**
	 * Send a request to the MAPPS API.
	 *
	 * @param string  $endpoint The API endpoint.
	 * @param mixed[] $args     Optional. WP HTTP API arguments, which will be merged with defaults.
	 *                          {@link https://developer.wordpress.org/reference/classes/WP_Http/request/#parameters}.
	 *
	 * @return mixed[]|WP_Error Either a response array or a WP_Error object, same as wp_remote_request().
	 */
	protected function mappsApi( $endpoint, $args = [] ) {
		// Strip leading slashes.
		if ( 0 === strpos( $endpoint, '/' ) ) {
			$endpoint = substr( $endpoint, 1 );
		}

		return wp_remote_request(
			esc_url_raw( sprintf( '%s/api/%2$s', $this->settings->managed_apps_endpoint, $endpoint ) ),
			array_replace_recursive( $this->getDefaultRequestArguments(), $args )
		);
	}

	/**
	 * Retrieve default request arguments.
	 *
	 * This includes common headers, User-Agent, etc.
	 *
	 * @link https://developer.wordpress.org/reference/classes/WP_Http/request/#parameters
	 *
	 * @return mixed[] An array of default request arguments.
	 */
	protected function getDefaultRequestArguments() {
		return [
			'user-agent' => sprintf( 'NexcessMAPPS/%1$s', PLUGIN_VERSION ),
			'timeout'    => 30,
			'headers'    => [
				'Accept'        => 'application/json',
				'X-MAAPI-TOKEN' => $this->settings->managed_apps_token,
			],
		];
	}

	/**
	 * Wrapper to set default request arguments and send a request to the MAPPS API.
	 *
	 * @param array $args Arguments to use.
	 *
	 * @return mixed[] An array of arguments.
	 */
	protected function getRequestArguments( $args = [] ) {
		$default = $this->getDefaultRequestArguments();

		// We're handling the nesting of headers specially, because we want to merge them,
		// not replace them. If we didn't do this, then the default headers would be
		// overwritten by the $args['headers'] argument.
		$new_headers = isset( $args['headers'] ) ? $args['headers'] : '';

		if ( $new_headers ) {
			$default_headers = isset( $default['headers'] ) ? $default['headers'] : [];

			$args['headers'] = wp_parse_args( $new_headers, $default_headers );
		}

		return wp_parse_args( $args, $default );
	}
}
