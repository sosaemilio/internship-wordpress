<?php

/**
 * Wrapper that allows us to throw WP_Error objects.
 */

namespace Nexcess\MAPPS\Exceptions;

use WP_Error;

class WPErrorException extends \RuntimeException {

	/**
	 * @var \WP_Error The underlying WP_Error object.
	 */
	protected $wp_error;

	/**
	 * Construct a new WPErrorException based on a WP_Error object.
	 *
	 * @param WP_Error $wp_error The WP_Error object to use as the basis for the exception.
	 */
	public function __construct( WP_Error $wp_error ) {
		$this->wp_error = $wp_error;

		parent::__construct(
			$wp_error->get_error_message(),
			is_numeric( $wp_error->get_error_code() ) ? (int) $wp_error->get_error_code() : 0
		);
	}

	/**
	 * Get the underlying WP_Error object.
	 *
	 * @return \WP_Error
	 */
	public function getWPError() {
		return $this->wp_error;
	}
}
