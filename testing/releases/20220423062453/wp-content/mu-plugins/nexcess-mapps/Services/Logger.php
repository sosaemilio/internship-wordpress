<?php

/**
 * A centralized, PSR-3 compliant logger for Nexcess MAPPS.
 */

namespace Nexcess\MAPPS\Services;

use Psr\Log\LoggerInterface;
use Psr\Log\LoggerTrait;
use Psr\Log\LogLevel;

class Logger implements LoggerInterface {
	use LoggerTrait;

	/**
	 * A cache of log levels and whether or not they should be logged.
	 *
	 * @var bool[]
	 */
	protected $levels = [];

	/**
	 * Additional properties that may be assigned to the class.
	 *
	 * @var mixed[]
	 */
	protected $properties = [];

	/**
	 * Retrieve a non-existent property, pulling from $this->properties when available.
	 *
	 * @param string $name The inaccessible property name.
	 *
	 * @return mixed The value of $this->properties[$name] if it exists, null otherwise.
	 */
	public function __get( $name ) {
		return isset( $this->properties[ $name ] ) ? $this->properties[ $name ] : null;
	}

	/**
	 * Set a new property within the logger.
	 *
	 * @param string $name  The property name.
	 * @param mixed  $value The property value.
	 */
	public function __set( $name, $value ) {
		$this->properties[ (string) $name ] = $value;
	}

	/**
	 * Logs with an arbitrary level.
	 *
	 * @param mixed   $level   The log level.
	 * @param string  $message The log message.
	 * @param mixed[] $context The log context.
	 */
	public function log( $level, $message, array $context = [] ) {
		if ( ! $this->shouldLogMessageLevel( $level ) ) {
			return;
		}

		$this->writeLogMessage( $this->formatLogMessage( $level, $message, $context ), $level );

		/**
		 * A message has been logged with the Nexcess MAPPS logger.
		 *
		 * @param string  $level   One of the RFC 5424 log levels.
		 * @param string  $message The contents of the message.
		 * @param mixed[] $context Additional context for the message.
		 */
		do_action( 'Nexcess\\MAPPS\\Logger::log', $level, $message, $context );

		/**
		 * A message has been logged with the Nexcess MAPPS logger.
		 *
		 * @param string  $level   One of the RFC 5424 log levels.
		 * @param string  $message The contents of the message.
		 * @param mixed[] $context Additional context for the message.
		 */
		do_action( "Nexcess\\MAPPS\\Logger::log_{$level}", $level, $message, $context );
	}

	/**
	 * Format a message for writing to the system logger.
	 *
	 * @param string  $level
	 * @param string  $message
	 * @param mixed[] $context
	 *
	 * @return string The formatted log message.
	 */
	protected function formatLogMessage( $level, $message, array $context = [] ) {
		$output = sprintf( '[MAPPS][%1$s] %2$s', $level, $message );

		if ( ! empty( $context ) ) {
			foreach ( $context as $key => $value ) {
				// Clean up the presentation of exceptions.
				if ( $value instanceof \Exception ) {
					$value = (string) $value;
				}

				// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_print_r
				$output .= "\n\t[{$key}] " . print_r( $value, true );
			}
		}

		return $output;
	}

	/**
	 * Determine whether or not the current $level should be logged.
	 *
	 * @param string $level
	 *
	 * @return bool True if a message at this level should be logged, false otherwise.
	 */
	protected function shouldLogMessageLevel( $level ) {
		if ( empty( $this->levels[ $level ] ) ) {
			$should_log = true;

			// Only log notice-level and lower if E_NOTICE is included in the error_reporting bitmask.
			if ( in_array( $level, [ LogLevel::NOTICE, LogLevel::INFO, LogLevel::DEBUG ], true ) ) {
				$should_log = (bool) ( (int) ini_get( 'error_reporting' ) & E_NOTICE );

				// Furthermore, only log debug messages if WP_DEBUG is true.
				if ( $should_log && LogLevel::DEBUG === $level ) {
					$should_log = defined( 'WP_DEBUG' ) && WP_DEBUG;
				}
			}

			$this->levels[ $level ] = $should_log;
		}

		return $this->levels[ $level ];
	}

	/**
	 * Write the given log message.
	 *
	 * @param string $message The formatted message.
	 * @param string $level   The log level.
	 */
	protected function writeLogMessage( $message, $level ) {
		// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
		error_log( $message, 0 );
	}
}
