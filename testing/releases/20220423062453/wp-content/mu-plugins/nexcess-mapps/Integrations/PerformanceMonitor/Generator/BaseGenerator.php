<?php

namespace Nexcess\MAPPS\Integrations\PerformanceMonitor\Generator;

/**
 * The `BaseGenerator` is a base class all other generators inherit from.
 */
abstract class BaseGenerator {

	/**
	 * The main method all generators must implement.
	 *
	 * Its purpose is to generate the sets of post meta data used to
	 * create new custom posts.
	 */
	abstract public function generate();

	/**
	 * Parses a version string into an array of integers.
	 *
	 * @param string $version Any version string.
	 *
	 * @return string[] Up to three numeric components if the version string
	 *                  is in semver or a date format.
	 */
	protected function parseVersionString( $version ) {
		preg_match( '~^([0-9]+)[\.-]([0-9]+)[\.-]?([0-9]+)?~', $version, $matches );

		/**
		 * Require at least a major and minor versions to be present in the version string.
		 */
		if ( count( $matches ) > 2 ) {

			/**
			 * Fill in a missing patch version.
			 */
			if ( ! isset( $matches[3] ) ) {
				$matches[3] = '0';
			}

			return array_slice( $matches, 1, 3 );
		}
		return [];
	}
}
