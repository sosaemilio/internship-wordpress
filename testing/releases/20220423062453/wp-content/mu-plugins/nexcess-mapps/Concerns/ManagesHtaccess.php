<?php

namespace Nexcess\MAPPS\Concerns;

use LiquidWeb\HtaccessValidator\Exceptions\ValidationException;
use LiquidWeb\HtaccessValidator\Validator;
use Nexcess\MAPPS\Exceptions\InvalidApacheConfigException;

use const Nexcess\MAPPS\VENDOR_DIR;

trait ManagesHtaccess {

	/**
	 * Retrieve the contents of the Htaccess file.
	 *
	 * @param string $file Optional. The Htaccess file to read. Default is empty, which will cause
	 *                     the Htaccess file in the WordPress root directory to be loaded.
	 *
	 * @return string The contents of the Htaccess file or an empty string if the file does not
	 *                exist or is otherwise unreadable.
	 */
	protected function getHtaccessFileContents( $file = '' ) {
		$htaccess = $file ?: ABSPATH . '.htaccess';

		// Nothing to do or unable to act.
		if ( ! file_exists( $htaccess ) || ! is_readable( $htaccess ) ) {
			return '';
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		return (string) file_get_contents( $htaccess );
	}

	/**
	 * Retrieve a single section from within the Htaccess file.
	 *
	 * @param string $marker The block identifier.
	 * @param string $file   Optional. The Htaccess file to read. Default is empty (use the
	 *                       Htaccess file in the WordPress root directory).
	 *
	 * @return string The contents of the block (minus the markers) or an empty string if the
	 *                given $marker cannot be found.
	 */
	protected function getHtaccessFileSection( $marker, $file = '' ) {
		$contents = $this->getHtaccessFileContents( $file );
		$marker   = preg_quote( $marker, '/' );
		$regex    = "/# BEGIN {$marker}\s+(.*)\s+# END {$marker}/ms";

		preg_match( $regex, $contents, $matched );

		return ! empty( $matched[1] ) ? $matched[1] : '';
	}

	/**
	 * Insert or update a section within the Htaccess file.
	 *
	 * This method works a lot like insert_with_markers(), but lets us specify whether the rules
	 * should come before or after stock WordPress rules. Additionally, writes are passed through
	 * setHtaccessFileContents(), which performs validation.
	 *
	 * @param string $marker  The identifier to use for the beginning or end of a block.
	 * @param string $content The contents of the block. Passing an empty string will remove the
	 *                        section if it currently exists.
	 * @param bool   $before  Optional. Whether or not the block should be placed before/after
	 *                        default WordPress rewrite rules. This option has no effect if a block
	 *                        for $marker is already present. Default is true.
	 * @param string $file    Optional. The Htaccess file to write to. Default is empty (use the
	 *                        Htaccess file in the WordPress root directory).
	 *
	 * @return bool True if the section was written, false otherwise.
	 */
	protected function writeHtaccessFileSection( $marker, $content, $before = true, $file = '' ) {
		$contents = $this->getHtaccessFileContents( $file );
		$update   = $content
			? '# BEGIN ' . $marker . PHP_EOL . $content . PHP_EOL . '# END ' . $marker . PHP_EOL
			: '';

		// Currently empty with nothing to add.
		if ( ! $contents && ! $update ) {
			return true;
		}

		// There isn't an existing marker with this name.
		if ( false === strpos( $contents, '# BEGIN ' . $marker . PHP_EOL ) ) {

			// Insert our rules just before WordPress' default rules.
			if ( false !== strpos( $contents, '# BEGIN WordPress' . PHP_EOL ) && $before ) {
				$contents = str_replace(
					'# BEGIN WordPress' . PHP_EOL,
					trim( $update ) . PHP_EOL . PHP_EOL . '# BEGIN WordPress' . PHP_EOL,
					$contents
				);
			} else {
				$contents .= PHP_EOL . PHP_EOL . $update;
			}

			$contents = (string) preg_replace( '/\n(\s*\n)+/', "\n\n", $contents );

		} else {
			// Update the existing block.
			$marker = preg_quote( $marker, '/' );
			$regex  = "/\s*(# BEGIN {$marker}\s+.*# END {$marker}\s*)/ms";

			/*
			 * Use regular expressions to match the block, but str_replace() to actually update
			 * the contents.
			 *
			 * This prevents preg_replace() from seeing "$1" (common in rewrite rules) as a regex
			 * placeholder, resulting in recursive nightmares if the same blocks are written
			 * multiple times.
			 */
			if ( preg_match( $regex, $contents, $matched ) ) {
				$contents = (string) str_replace( $matched[1], $update . PHP_EOL, $contents );
			}
		}

		return $this->setHtaccessFileContents( trim( $contents ), $file );
	}

	/**
	 * Overwrite the contents of the Htaccess file.
	 *
	 * @param string $contents The contents of the Htaccess file.
	 * @param string $file     Optional. The Htaccess file to write to. Default is empty.
	 *
	 * @throws \Nexcess\MAPPS\Exceptions\InvalidApacheConfigException If the contents don't pass validation.
	 *
	 * @return bool True if the file was updated, false otherwise.
	 */
	protected function setHtaccessFileContents( $contents, $file = '' ) {
		$htaccess = $file ?: ABSPATH . '.htaccess';

		/**
		 * Determine whether or not to validate Htaccess files before writing.
		 *
		 * @param bool   $validate True if validation should occur, false otherwise.
		 * @param string $file     The particular Htaccess file being written to.
		 * @param string $contents The Htaccess file contents.
		 */
		$should_validate = apply_filters( 'nexcess_mapps_validate_htaccess_contents', false, $file, $contents );

		if ( $should_validate ) {
			$this->validateHtaccessContents( $contents );
		}

		/*
		 * The WordPress Filesystem API doesn't cover the kind of writing with locks we're doing
		 * here, so we'll drop down to native PHP functions.
		 *
		 * phpcs:disable WordPress.WP.AlternativeFunctions
		 */
		if ( file_exists( $htaccess ) && ! is_writable( $htaccess ) ) {
			return false;
		}

		$current = (string) file_get_contents( $htaccess );

		// Nothing will change, so there's nothing to do.
		if ( $current === $contents ) {
			return true;
		}

		return false !== file_put_contents( $htaccess, $contents, LOCK_EX );
	}

	/**
	 * Validate Htaccess file contents before writing.
	 *
	 * @param string $contents The contents to be written.
	 *
	 * @throws \Nexcess\MAPPS\Exceptions\InvalidApacheConfigException If the contents don't pass validation.
	 *
	 * @return bool Will return true if the contents pass validation.
	 */
	protected function validateHtaccessContents( $contents ) {
		try {
			// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.runtime_configuration_putenv
			putenv( 'HTACCESS_VALIDATOR_SCRIPT=' . VENDOR_DIR . 'bin/validate-htaccess' );
			Validator::createFromString( $contents )->validate();
		} catch ( ValidationException $e ) {
			/*
			 * An exit code of 2 means we couldn't find the Apache binary, so notify the user but
			 * do not fail validation.
			 *
			 * @todo Send this through the dedicated logger.
			 */
			if ( 2 === $e->getCode() ) {
				// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_trigger_error
				trigger_error( wp_kses_post( $e->getMessage() ), E_USER_NOTICE );
				return true;
			}

			throw new InvalidApacheConfigException( $e->getMessage(), $e->getCode(), $e );
		}

		return true;
	}
}
