<?php

namespace Nexcess\MAPPS\Support;

/**
 * @method static void constant(string $name, string $version, string $alternative = '')
 * @method static void filter(string $name, string $version, string $alternative = '')
 */
class Deprecation {

	/**
	 * @var string An alternative to $this->name to suggest.
	 */
	public $alternative;

	/**
	 * @var string The item that has been deprecated.
	 */
	public $name;

	/**
	 * @var string The type of deprecation.
	 */
	public $type;

	/**
	 * @var string The version in which this was deprecated.
	 */
	public $version;

	/**
	 * The action fired when a deprecation is found.
	 */
	const ACTION_HOOK = 'Nexcess\\MAPPS\\deprecation';

	/**
	 * The types of deprecations to process.
	 */
	const TYPE_CONSTANT = 'constant';
	const TYPE_FILTER   = 'filter';

	/**
	 * Create a new deprecation.
	 *
	 * @param string $type        The type of deprecation (one of the TYPE_* constants).
	 * @param string $name        The item that has been deprecated.
	 * @param string $version     The version in which it was deprecated.
	 * @param string $alternative Optional. An alternative to suggest. Default is empty.
	 */
	public function __construct( $type, $name, $version, $alternative = '' ) {
		$this->type        = (string) $type;
		$this->name        = (string) $name;
		$this->version     = (string) $version;
		$this->alternative = (string) $alternative;
	}

	/**
	 * Handle the processing of the deprecation.
	 */
	public function handle() {
		/**
		 * Fires when a deprecation has been detected.
		 *
		 * @param \Nexcess\MAPPS\Support\Deprecation $deprecation The deprecation object.
		 */
		do_action( self::ACTION_HOOK, $this );
	}

	/**
	 * Shortcut for constructing *and executing* methods by type.
	 *
	 * @param string  $name The method name.
	 * @param mixed[] $args The arguments passed to the method.
	 *
	 * @throws \BadMethodCallException If the method name isn't a valid TYPE_* constant.
	 */
	public static function __callStatic( $name, array $args ) {
		$constant = 'TYPE_' . strtoupper( $name );

		if ( ! defined( 'self::' . $constant ) ) {
			throw new \BadMethodCallException( sprintf(
				'"%1$s" is not a valid deprecation type.',
				$name
			) );
		}

		array_unshift( $args, constant( 'self::' . $constant ) );

		( new self( ...$args ) )->handle();
	}
}
