<?php

namespace Nexcess\MAPPS\Commands;

use cli\Tree;
use cli\tree\Markdown;
use WP_CLI;

use function WP_CLI\Utils\parse_str_to_argv;

/**
 * This class serves as the basis for all other commands.
 */
abstract class Command {

	/**
	 * Colorize a string for output.
	 *
	 * @see WP_CLI::colorize()
	 *
	 * @param string $string A string to colorizing, using one or more color tokens.
	 *
	 * @return string The colorized string.
	 */
	protected function colorize( $string ) {
		return WP_CLI::colorize( $string );
	}

	/**
	 * Check to see whether or not a command exists.
	 *
	 * @param string $command The command to check.
	 *
	 * @return bool
	 */
	protected function commandExists( $command ) {
		$command  = parse_str_to_argv( $command );
		$response = WP_CLI::get_runner()->find_command_to_run( $command );

		if ( is_string( $response ) ) {
			$this->debug( 'Missing command: ' . $response );
			return false;
		}

		return true;
	}

	/**
	 * Display debug message prefixed with "Debug: " when `--debug` is used.
	 *
	 * @see WP_CLI::debug()
	 *
	 * @param string      $message The message to write.
	 * @param string|bool $group   Optional. The debug group for this message, or FALSE for no group.
	 *                             Default is "MAPPS".
	 *
	 * @return self
	 */
	protected function debug( $message, $group = 'MAPPS' ) {
		WP_CLI::debug( $message, $group );

		return $this;
	}

	/**
	 * Display error message prefixed with "Error: " and (optionally) exit.
	 *
	 * @see WP_CLI::error()
	 * @see WP_CLI::error_multi_line()
	 *
	 * @param string[]|string $message The message to write. If an array is passed, the lines will
	 *                                 be concatenated with PHP_EOL characters.
	 * @param bool|int        $exit    Optional. Exit the script automatically, optionally specifying
	 *                                 the exit code. Default is true (exit code 1).
	 *
	 * @return self
	 */
	protected function error( $message, $exit = true ) {
		if ( is_array( $message ) ) {
			WP_CLI::error_multi_line( $message );

			if ( $exit ) {
				$this->halt( (int) $exit );
			}
		} else {
			WP_CLI::error( $message, $exit );
		}

		return $this;
	}

	/**
	 * Halt execution of the script.
	 *
	 * @see WP_CLI::halt()
	 *
	 * @param int $exit_code The exit code to use.
	 */
	protected function halt( $exit_code ) {
		WP_CLI::halt( $exit_code );
	}

	/**
	 * Display informational message without prefix, and ignore `–quiet`.
	 *
	 * @see WP_CLI::line()
	 *
	 * @param string $message The message to write.
	 *
	 * @return self
	 */
	protected function line( $message = '' ) {
		WP_CLI::line( $message );

		return $this;
	}

	/**
	 * Display a list of items.
	 *
	 * @see \cli\Tree
	 *
	 * @param mixed[] $items An array of items to display in a list.
	 *
	 * @return self
	 */
	protected function listing( $items ) {
		$tree = new Tree();
		$tree->setData( (array) $items );
		$tree->setRenderer( new Markdown() );

		// Sent through log() so it can be silenced if --quiet is passed.
		return $this->log( trim( (string) $tree->render() ) );
	}

	/**
	 * Display informational message without prefix.
	 *
	 * @see WP_CLI::log()
	 *
	 * @param string $message The message to write.
	 *
	 * @return self
	 */
	protected function log( $message ) {
		WP_CLI::log( $message );

		return $this;
	}

	/**
	 * Log a newline character.
	 *
	 * @return self
	 */
	protected function newline() {
		return $this->log( '' );
	}

	/**
	 * Demarcate a step/section in a process.
	 *
	 * @param string $message The step/section heading.
	 */
	protected function step( $message ) {
		$this->log( PHP_EOL . $this->colorize( sprintf( '%%c%1$s%%n', $message ) ) );

		return $this;
	}

	/**
	 * Display success message prefixed with "Success: ".
	 *
	 * @see WP_CLI::success()
	 *
	 * @param string $message The message to write.
	 *
	 * @return self
	 */
	protected function success( $message ) {
		WP_CLI::success( $message );

		return $this;
	}

	/**
	 * Display warning message prefixed with "Warning: ".
	 *
	 * @see WP_CLI::warning()
	 *
	 * @param string $message The message to write.
	 *
	 * @return self
	 */
	protected function warning( $message ) {
		WP_CLI::warning( $message );

		return $this;
	}

	/**
	 * Execute another WP-CLI command in the same process.
	 *
	 * @see WP_CLI::runcommand()
	 *
	 * @param string  $command The WP-CLI command, including arguments.
	 * @param mixed[] $args    Optional. Overrides to the configuration. Default is empty.
	 *
	 * @return mixed
	 */
	protected function wp( $command, array $args = [] ) {
		$args = wp_parse_args( $args, [
			'exit_error' => false,
			'launch'     => false,
			'return'     => false,
		] );

		$this->debug( sprintf(
			'Calling `%1$s` with args %2$s',
			$command,
			wp_json_encode( $args )
		) );

		return WP_CLI::runcommand( $command, $args );
	}
}
