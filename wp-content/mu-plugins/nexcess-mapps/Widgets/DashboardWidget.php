<?php

/**
 * An object-oriented representation of a WP dashboard widget.
 */

namespace Nexcess\MAPPS\Widgets;

use Nexcess\MAPPS\Concerns\HasAdminPages;
use StellarWP\PluginFramework\Exceptions\ImmutableValueException;

/**
 * @property-read string $context
 * @property-read callable $controlCallback
 * @property-read mixed  $data
 * @property-read string $id
 * @property-read string $name
 * @property-read string $priority
 * @property-read string $template
 */
class DashboardWidget {
	use HasAdminPages;

	/**
	 * @var string The context for rendering the widget. Accepts 'normal', 'side', 'column3', or 'column4'.
	 */
	protected $context;

	/**
	 * @var callable The callable to run when the controls for the widget are rendered.
	 */
	protected $controlCallback;

	/**
	 * @var mixed[] The data passed to the render template
	 */
	protected $data;

	/**
	 * @var string The widget ID string
	 */
	protected $id;

	/**
	 * @var string The widget name rendered as the title of the widget
	 */
	protected $name;

	/**
	 * @var string The priority for rendering the widget. Accepts 'high', 'core', 'default', or 'low'.
	 */
	protected $priority;

	/**
	 * @var string The template path for rendering the widget.
	 *
	 * @see self::render()
	 */
	protected $template;

	/**
	 * Construct a Dashboard Widget.
	 *
	 * @param string  $id       The ID to register the widget under.
	 * @param string  $name     The name to give the widget, rendered as the title.
	 * @param string  $template The template to use when rendering the widget.
	 * @param mixed[] $data     The data to provide to the template when rendering the widget.
	 */
	public function __construct( $id, $name, $template, $data ) {
		$this->id       = $id;
		$this->name     = $name;
		$this->template = $template;
		$this->data     = $data;
	}

	/**
	 * Expose protected properties for reading.
	 *
	 * @param string $prop The property being accessed.
	 *
	 * @return mixed The value of $this->$prop or null.
	 */
	public function __get( $prop ) {
		return isset( $this->{ $prop } ) ? $this->{ $prop } : null;
	}

	/**
	 * Do not permit properties to be overridden on the class using object notation.
	 *
	 * @param string $property The property name.
	 * @param mixed  $value    The value being assigned.
	 *
	 * @throws \StellarWP\PluginFramework\Exceptions\ImmutableValueException If the property is immutable.
	 */
	public function __set( $property, $value ) {
		throw new ImmutableValueException(
			sprintf(
				/* Translators: %1$s is the property name. */
				__( 'Setting "%1$s" may not be modified.', 'nexcess-mapps' ),
				esc_html( $property )
			)
		);
	}

	/**
	 * Set the widget context to null, 'normal', 'side', 'column3', or 'column4'.
	 *
	 * @param string|null $context The context to set.
	 *
	 * @return self
	 */
	public function setContext( $context = null ) {
		if ( in_array( $context, [ 'normal', 'side', 'column3', 'column4' ], true ) ) {
			$this->context = $context;
		} else {
			unset( $this->context );
		}

		return $this;
	}

	/**
	 * Set the widget priority to null, 'high', 'core', 'default', or 'low'.
	 *
	 * @param string|null $priority The priority to set.
	 *
	 * @return self
	 */
	public function setPriority( $priority = null ) {
		if ( in_array( $priority, [ 'high', 'core', 'default', 'low' ], true ) ) {
			$this->priority = $priority;
		} else {
			unset( $this->priority );
		}

		return $this;
	}

	/**
	 * Render the widget markup.
	 */
	public function render() {
		$this->renderTemplate( $this->template, $this->data );
	}
}
