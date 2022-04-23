<?php

/**
 * Add an options page to our admin.
 *
 * If you want to add a new option, check out addOption(), it's pretty simple.
 *
 * Here's the most basic example, which will add a checkbox with the label "Enable turbo mode".
 *    $this->addOption( 'turbo_mode', 'checkbox', __( 'Enable turbo mode' ) );
 *
 * Those parameters are: $name, $type, $label. There is also a fourth $args parameter,
 * to allow you to pass additional arguments to the option. A full-er example would be:
 *     $this->addOption(
 *         [ 'my_grouped_option_key', 'this_specific_option_key' ],
 *         'checkbox',
 *         __( 'Enable turbo mode' ),
 *         [
 *             'default'     => true,
 *             'description' => __( 'This is an extended description of the option.' ),
 *             'order'       => 300,
 *             'render_cb'   => [ $this, 'renderMyOption' ], ( this takes a callback )
 *             'sanitize_cb' => [ $this, 'sanitizeMyOption' ], ( this takes a callback )
 *         ]
 *     );
 *
 * This example would show a checkbox with the label "Enable turbo mode" and the description,
 * would default to true, and would call the renderMyOption() and sanitizeMyOption() methods
 * to display in the form and to sanitize the value submitted before saving.
 */

namespace Nexcess\MAPPS\Services;

use Nexcess\MAPPS\Concerns\HasAdminPages;
use Nexcess\MAPPS\Concerns\HasHooks;
use Nexcess\MAPPS\Concerns\ManagesGroupedOptions;
use Nexcess\MAPPS\Support\AdminNotice;

class Options {
	use HasAdminPages;
	use HasHooks;
	use ManagesGroupedOptions;

	/**
	 * @var \Nexcess\MAPPS\Services\AdminBar
	 */
	protected $adminBar;

	/**
	 * @var array
	 */
	private $fields = [];

	/**
	 * This option isn't used, as we pass specific option names to the
	 * grouped options manager, but it's here to make sure it's defined.
	 */
	const OPTION_NAME = 'nexcess_mapps_options_page';

	/**
	 * @param \Nexcess\MAPPS\Services\AdminBar $admin_bar
	 */
	public function __construct( AdminBar $admin_bar ) {
		$this->adminBar = $admin_bar;
	}

	/**
	 * Register actions.
	 */
	public function register() {
		add_action( 'admin_init', [ $this, 'addOptionsPage' ], 500 );  // Set to 500 so that it's the last of the functionality tabs.
		add_action( 'admin_init', [ $this, 'processSubmission' ], 1 );
	}

	/**
	 * Register the "Settings" settings section.
	 */
	public function addOptionsPage() {
		$markup = $this->getFieldsMarkup();

		// If there aren't any valid options, don't add the options page.
		if ( ! $markup ) {
			return;
		}

		// The title of the tab is "Settings", but we refer to it as "Options" to
		// avoid confusion with the MAPPS Settings object.
		add_settings_section(
			'settings',
			esc_attr_x( 'Settings', 'settings section', 'nexcess-mapps' ),
			function () use ( $markup ) {
				$this->renderTemplate( 'options-page', [ 'fields' => $markup ] );
			},
			'nexcess-mapps'
		);
	}

	/**
	 * Add a field to the list of fields.
	 *
	 * @param string|array $key   The key for the field.
	 * @param string       $type  The type of field.
	 * @param string       $label The label for the field.
	 * @param mixed[]      $args  {
	 *
	 *     'default'     => (mixed)    The default value for the field.
	 *     'order'       => (int)      The order of the field. Defaults to 1000.
	 *                                 A good pattern is to define fields with an order of
	 *                                 100, 200, 300, etc. to make it easy to add a field
	 *                                 inbetween existing fields without having to reorder
	 *                                 all the fields. If there are more than 10 fields
	 *                                 using the hundreds method, then it's a good idea
	 *                                 to increase the default order to something higher.
	 *     'description' => (string)   An optional extra description for the field.
	 *     'render_cb'   => (callable) A callback to render the field. The callback will be
	 *                                 passed an array of key/value pairs that matches all
	 *                                 the arguments passed to this method. Expected to
	 *                                 return either a string of markup or false.
	 *                                 If false is returned, the field will not be rendered.
	 *     'sanitize_cb' => (callable) A callback to save the field. The callback will be passed
	 *                                 the value and key of the field. Expected to return
	 *                                 the value to save. Null can be returned to not save it.
	 * }
	 *
	 * The additional args are all optional.
	 */
	public function addOption( $key, $type, $label, $args = [] ) {
		$args = wp_parse_args( $args, [
			'default'     => false,
			'description' => '',
			'order'       => 1000,
			'render_cb'   => $this->getDefaultRenderCallback( $type ),
			'sanitize_cb' => $this->getDefaultSanitizeCallback( $type ),
		] );

		$this->fields[ $this->getSanitizedKey( $key ) ] = [
			'key'         => $key,
			'label'       => $label,
			'type'        => $type,
			'default'     => $args['default'],
			'description' => $args['description'],
			'order'       => $args['order'],
			'render_cb'   => $args['render_cb'],
			'sanitize_cb' => $args['sanitize_cb'],
		];

		// Sort the fields by order whenever this gets called, so that
		// you can always grab $this->fields in the sorted order.
		$sort_col = array_column( $this->fields, 'order' );
		array_multisort( $sort_col, SORT_ASC, $this->fields );
	}

	/**
	 * Loop through the valid options fields and render them.
	 *
	 * @todo Update this to support more than checkboxes.
	 *
	 * @return string The markup for the fields.
	 */
	public function getFieldsMarkup() {
		$output = '';

		foreach ( (array) $this->fields as $field ) {
			if ( empty( $field['render_cb'] ) ) {
				continue;
			}

			// Call the render callback to get the field markup.
			$field_output = call_user_func( $field['render_cb'], $field );

			// If the markup is empty, skip this field.
			if ( empty( $field_output ) ) {
				continue;
			}

			// Add the field markup to the output.
			$output .= $field_output;
		}

		return $output;
	}

	/**
	 * Get the default render callback for a field type.
	 *
	 * @param string $type The type of field.
	 *
	 * @return false|callable The callback for the field type.
	 */
	public function getDefaultRenderCallback( $type ) {
		switch ( $type ) {
			case 'checkbox':
				return [ $this, 'renderCheckbox' ];
			default:
				return false;
		}
	}

	/**
	 * Get the default save callback for a field type.
	 *
	 * @param string $type The type of field.
	 *
	 * @return false|callable The callback for the field type.
	 */
	public function getDefaultSanitizeCallback( $type ) {
		switch ( $type ) {
			case 'checkbox':
				return [ $this, 'sanitizeCheckbox' ];
			default:
				return false;
		}
	}

	/**
	 * Get the markup for a checkbox field.
	 *
	 * @param array $field The field to get the markup for. @see addOption() for the field structure.
	 *
	 * @return string The markup for the field.
	 */
	public function renderCheckbox( $field ) {
		return sprintf(
			'<div class="mapps-toggle-switch">'
			. '<input type="checkbox" id="%1$s" name="%1$s" %2$s>'
			. '<label for="%1$s">%3$s</label>'
			. '<span class="toggle-label">%4$s</span>'
			. '</div>',
			esc_attr( $this->getSanitizedKey( $field['key'] ) ),
			checked( $this->getSavedOption( $field['key'], $field['default'] ), true, false ),
			wp_kses_post( $field['label'] ),
			! empty( $field['description'] ) ? wp_kses_post( $field['description'] ) : ''
		);
	}

	/**
	 * Sanitize callback for a checkbox field.
	 *
	 * @param string $key   The key for the field.
	 * @param mixed  $value The value to save.
	 *
	 * @return bool|null The value to save.
	 */
	public function sanitizeCheckbox( $key, $value ) {
		return ( 'on' === $value );
	}

	/**
	 * From either a string or an array, generate a sanitized key to use for a field.
	 *
	 * When registering a field, rather than having to pass in a field ID that is going
	 * to be the same as the key, we just use the option key as the field ID,
	 * with a special check for it being an array. We also make sure to sanitize the key.
	 *
	 * @param string|array $key Option key.
	 *
	 * @return string Sanitized key.
	 */
	public function getSanitizedKey( $key ) {
		if ( is_array( $key ) ) {
			$key = implode( '_', $key ) . '_' . md5( maybe_serialize( $key ) );
		}

		return sanitize_key( $key );
	}

	/**
	 * Get an option value from either a grouped option or a normal DB option.
	 *
	 * @param string|array $key     Option key. Array for grouped option, string for normal key.
	 * @param mixed        $default Default value to return if the option is not set.
	 *
	 * @return mixed Option value.
	 */
	public function getSavedOption( $key, $default = null ) {
		// If it's not a grouped option, just do it normally.
		if ( ! is_array( $key ) ) {
			return get_option( $key, $default );
		}

		// We have a grouped option, so let's use our array values to grab it.
		$saved = self::getOptionByName( $key[0] )->{$key[1]};

		// If the value is not set, return the default.
		if ( is_null( $saved ) ) {
			return $default;
		}

		return $saved;

	}

	/**
	 * Process the POST submission and save the data.
	 */
	public function processSubmission() {
		// Safety check before saving.
		if (
			! isset( $_POST['mapps-options-submit'] ) // Submit button.
			|| ! isset( $_POST['_mapps-options-save-nonce'] ) // Nonce.
			|| ! is_admin()
			|| ! current_user_can( 'manage_options' )
			|| ! wp_verify_nonce( $_POST['_mapps-options-save-nonce'], 'mapps-options-save' ) // Verify nonce.
		) {
			return;
		}

		// Loop through the fields and save them.
		foreach ( (array) $this->fields as $field ) {
			// Grab our sanitized key to compare against the POST data.
			$key = $this->getSanitizedKey( $field['key'] );

			// Call the sanitizer callback and pass in the value.
			$value = call_user_func( $field['sanitize_cb'], $key, isset( $_POST[ $key ] ) ? $_POST[ $key ] : null );

			// Save the option, either as a grouped option or a normal option.
			if ( is_array( $field['key'] ) ) {
				$prev = self::getOptionByName( $field['key'][0] )->{$field['key'][1]};

				self::getOptionByName( $field['key'][0] )->set( $field['key'][1], $value )->save();
			} else {
				$prev = get_option( $field['key'] );

				update_option( $field['key'], $value );
			}

			do_action( 'Nexcess\MAPPS\Options\Update', $field['key'], $value, $prev );
		}

		$notice = new AdminNotice( __( 'Settings saved.', 'nexcess-mapps' ), 'success', true );
		$notice->setSaveDismissal( false );

		$this->adminBar->addNotice( $notice );
	}

	/**
	 * Helper to verify if the current option key is the expected option.
	 * Helpful for hooking into the update action.
	 *
	 * @param string|array $actual   The actual option key.
	 * @param string|array $expected The expected option key.
	 *
	 * @return bool True if the option key is the expected option.
	 */
	public function verifyOptionKey( $actual, $expected ) {
		// If it's an array, we want to match the option group and option name.
		if ( is_array( $expected ) ) {
			// The array will look similar to this: [ 'my_integration_options', 'enable_the_feature' ].
			if (
				isset( $actual[0], $actual[1], $expected[0], $expected[1] )
				&& $actual[0] === $expected[0]
				&& $actual[1] === $expected[1]
			) {
				return true;
			}
		} else {
			if ( $actual === $expected ) {
				return true;
			}
		}

		return false;
	}
}
