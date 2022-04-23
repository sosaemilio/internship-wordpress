<?php

/**
 * Expose a cached getOption() method for interacting with a GroupedOption.
 *
 * IMPORTANT: PHP traits can't define constants, but the getOption() method assumes that the class
 * has an "OPTION_NAME" constant defined, corresponding to the option name.
 */

namespace Nexcess\MAPPS\Concerns;

use Nexcess\MAPPS\Support\GroupedOption;

trait ManagesGroupedOptions {

	/**
	 * The GroupedOption instance.
	 *
	 * @var GroupedOption
	 */
	private $option;

	/**
	 * Get the GroupedOption instance.
	 *
	 * Note that this method assumes the class has an "OPTION_NAME" class constant defined.
	 *
	 * @return GroupedOption
	 */
	public function getOption() {
		if ( null === $this->option ) {
			$this->option = new GroupedOption( self::OPTION_NAME );
		}

		return $this->option;
	}

	/**
	 * Get a GroupedOption by directly passing in the Option name.
	 *
	 * @param string $option_name The option name.
	 *
	 * @return GroupedOption
	 */
	public static function getOptionByName( $option_name = '' ) {
		return new GroupedOption( $option_name );
	}
}
