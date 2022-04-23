<?php

namespace Nexcess\MAPPS\Support;

use WP_Error;
use WP_Fatal_Error_Handler;

class FatalErrorHandler extends WP_Fatal_Error_Handler {

	/**
	 * Drop-in files we can safely move aside if they have caused the issue.
	 *
	 * @var string[]
	 */
	private $dropIns = [
		'advanced-cache.php',
		'maintenance.php',
		'object-cache.php',
	];

	/**
	 * Our customized error message, if applicable.
	 *
	 * @var string
	 */
	private $errorMessage;

	/**
	 * {@inheritDoc}
	 *
	 * This method is an exact copy of the parent except for the call to our custom mappsHandler().
	 *
	 * @codeCoverageIgnore
	 */
	public function handle() {
		if ( defined( 'WP_SANDBOX_SCRAPING' ) && WP_SANDBOX_SCRAPING ) {
			return;
		}

		// Do not trigger the fatal error handler while updates are being installed.
		if ( function_exists( 'wp_is_maintenance_mode' ) && wp_is_maintenance_mode() ) {
			return;
		}

		try {
			// Bail if no error found.
			$error = $this->detect_error();
			if ( ! $error ) {
				return;
			}

			if ( ! isset( $GLOBALS['wp_locale'] ) && function_exists( 'load_default_textdomain' ) ) {
				load_default_textdomain();
			}

			$handled = false;

			if ( ! is_multisite() && wp_recovery_mode()->is_initialized() ) {
				$handled = wp_recovery_mode()->handle_error( $error );
			}

			// If WordPress' default recovery didn't do the trick, try our custom handler.
			if ( ! $handled || is_wp_error( $handled ) ) {
				$handled = $this->mappsHandler( $error, $handled );
			}

			// Display the PHP error template if headers not sent.
			if ( is_admin() || ! headers_sent() ) {
				$this->display_error_template( $error, $handled );
			}
		} catch ( \Exception $e ) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch
			// Catch exceptions and remain silent.
		}
	}

	/**
	 * Apply our custom error message, if one is set.
	 *
	 * This method is applied as a filter callback registered on wp_php_error_message, but *only*
	 * if WP_DEBUG is true.
	 *
	 * @param string $message The default error message.
	 *
	 * @return string The possibly-filtered $message.
	 */
	public function filterErrorMessage( $message ) {
		return $this->errorMessage ?: $message;
	}

	/**
	 * MAPPS-specific error handling.
	 *
	 * If WordPress can't solve the issue on its own, we fall back to this to see if we can fix it.
	 *
	 * @param mixed[]       $error   Error details from {@see error_get_last()}.
	 * @param WP_Error|bool $handled Either false or a WP_Error object explaining why WordPress
	 *                               was unable to resolve the error.
	 *
	 * @return true|WP_Error True if the error was handled, or a WP_Error object explaining why it wasn't.
	 */
	protected function mappsHandler( array $error, $handled ) {
		$include_regex = '/(?:require|include)(?:_once)?\(\)\:.+(' . preg_quote( ABSPATH, '/' ) . '\S+)[\'"*]/';

		if ( E_COMPILE_ERROR === $error['type'] && preg_match( $include_regex, $error['message'], $matches ) ) {
			return $this->handleFileIncludeError( $error, $matches[1] );
		}

		return is_wp_error( $handled )
			? $handled
			: new WP_Error( 'unhandled_error', 'Unhandled error', $error );
	}

	/**
	 * Handle an error that results from trying to include/require a file that doesn't exist.
	 *
	 * @param mixed[] $error The error array from error_get_last().
	 * @param string  $file  The file that was attempting to be included.
	 *
	 * @return true|WP_Error True if the error was resolved, a WP_Error object otherwise.
	 */
	protected function handleFileIncludeError( array $error, $file ) {
		try {
			// The broken file is a drop-in.
			if ( in_array( basename( $error['file'] ), $this->dropIns, true ) ) {
				$this->moveBrokenFile( $error['file'] )
					->setErrorMessage( sprintf(
						// Intentionally not translated so as to not load more of WordPress.
						'The <code>%1$s</code> drop-in was attempting to include <code>%2$s</code>, which does not exist. The drop-in has been moved to <code>%1$s.broken</code> for your inspection, and refreshing this page should see your site functioning normally.',
						basename( $error['file'] ),
						$file
					) );

				return true;
			}

			// The broken include is coming from a MU plugin.
			if ( preg_match( '/^' . preg_quote( WP_CONTENT_DIR . '/mu-plugins/', '/' ) . '(?!nexcess\-mapps\/)/', $error['file'] ) ) {
				$this->moveBrokenFile( $error['file'] )
					->setErrorMessage( sprintf(
						// Intentionally not translated so as to not load more of WordPress.
						'The <code>%1$s</code> must-use plugin was attempting to include <code>%2$s</code>, which does not exist. The MU plugin file has been moved to <code>%1$s.broken</code> for your inspection, and refreshing this page should see your site functioning normally.',
						basename( $error['file'] ),
						$file
					) );

				return true;
			}
		} catch ( \Exception $e ) {
			$this->error( $e->getMessage() );
		}

		return new WP_Error( 'missing_file_include', sprintf(
			'%1$s includes %2$s, which does not exist.',
			$error['file'],
			$file
		), $error );
	}

	/**
	 * Move a broken file out of the way by adding a ".broken" suffix.
	 *
	 * @param string $file The absolute system path to the file.
	 *
	 * @throws \RuntimeException If the file cannot be moved.
	 *
	 * @return self
	 */
	protected function moveBrokenFile( $file ) {
		if ( ! rename( $file, $file . '.broken' ) ) {
			throw new \RuntimeException( sprintf( 'Unable to move %1$s to %1$s.broken', $file ) );
		}

		return $this;
	}

	/**
	 * Set the error message to display on the error screen when WP_DEBUG is enabled.
	 *
	 * @param string $message The error message, which will be passed through wpautop().
	 */
	protected function setErrorMessage( $message ) {
		/*
		 * Only apply our custom messages if WP_DEBUG is true, as we don't want to accidentally
		 * leak information about a production site.
		 */
		if ( ! defined( 'WP_DEBUG' ) || ! WP_DEBUG ) {
			return;
		}

		$this->errorMessage = wpautop( $message );

		if ( false === has_filter( 'wp_php_error_message', [ $this, 'filterErrorMessage' ] ) ) {
			add_filter( 'wp_php_error_message', [ $this, 'filterErrorMessage' ] );
		}
	}

	/**
	 * Log an error related to the fatal error handler.
	 *
	 * @param string $message The error message.
	 *
	 * @codeCoverageIgnore
	 */
	protected function error( $message ) {
		// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
		error_log( $message );
	}
}
