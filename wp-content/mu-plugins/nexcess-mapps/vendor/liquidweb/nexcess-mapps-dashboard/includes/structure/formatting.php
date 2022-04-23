<?php
/**
 * Handle formatting any markup we are using.
 *
 * @package PlatformSelfInstall
 */

// Call our namepsace.
namespace Nexcess\MAPPS\Dashboard\Structure\Formatting;

// Set our alias items.
use Nexcess\MAPPS\Dashboard as Core;
use Nexcess\MAPPS\Dashboard\Helpers as Helpers;
use Nexcess\MAPPS\Dashboard\Utilities as Utilities;

/**
 * Take our plugin list and make a big 'ol list of lists.
 *
 * @param  array $plugin_dataset  The dataset.
 *
 * @return HTML
 */
function format_available_plugin_list( $plugin_dataset = array() ) {

	// Bail without any plugins to compare.
	if ( empty( $plugin_dataset ) ) {
		return;
	}

	// Set our currently installed plugins.
	$current_plugins    = Helpers\get_current_installed_plugins();

	// Set an empty.
	$build  = '';

	// Wrap our columns so we can style them.
	$build .= '<div class="nexcess-mapps-dashboard-plugin-list-grid">';

	// Now a big loop.
	foreach ( $plugin_dataset as $plugin_group => $plugins_list ) {

		// Set a class for the group div.
		$dclass = 'nexcess-mapps-dashboard-plugin-group nexcess-mapps-dashboard-plugin-group-' . esc_attr( $plugin_group );

		// Wrap this one in a div too.
		$build .= '<div data-group="' . esc_attr( $plugin_group ) . '" class="' . esc_attr( $dclass ) . '">';

			// Output our title.
			$build .= '<h3 class="nexcess-mapps-dashboard-plugin-group-title">' . texturize_group_name( $plugin_group ) . '</h3>';

			// And my list.
			$build .= format_group_list_display( $plugins_list, $plugin_group, $current_plugins );

		// Close the div for the group.
		$build .= '</div>';
	}

	// Close the div for the grid.
	$build .= '</div>';

	// Return the formatting.
	return $build;
}

/**
 * Take the name of a group and make it for display.
 *
 * @param  array   $plugin_list      The plugin list inside a group.
 * @param  string  $plugin_group     Which group this is a part of.
 * @param  array   $current_plugins  What plugins we currentl have.
 *
 * @return HTML
 */
function format_group_list_display( $plugin_list = array(), $plugin_group = '', $current_plugins = array() ) {

	// Bail without a list to format.
	if ( empty( $plugin_list ) ) {
		return;
	}

	// Set a class for the list.
	$class  = 'nexcess-mapps-dashboard-plugin-group-list nexcess-mapps-dashboard-plugin-group-list-' . esc_attr( $plugin_group );

	// Set an empty.
	$build  = '';

	// Start the list output.
	$build .= '<ul class="' . esc_attr( $class ) . '">';

	// Now loop and markup.
	foreach ( $plugin_list as $plugin_args ) {

		// Set our plugin slug (from the name field)
		$set_plugin_slug    = esc_attr( $plugin_args['name'] );

		// Run the enabled checks.
		$list_format_args   = create_plugin_list_formatting( $set_plugin_slug, $plugin_args, $current_plugins );

		// Fetch the classes.
		$list_item_classes  = create_plugin_list_classes( $set_plugin_slug, $plugin_args, $current_plugins );

		// Set up the item flags.
		$list_item_flags    = '';

		// Set up the flags for the input.
		if ( ! empty( $list_format_args['flag'] ) ) {

			// Implode the flags into an array.
			$flag_array = explode( ' ', $list_format_args['flag'] );

			// Loop the flags and set.
			foreach ( $flag_array as $single_flag ) {

				// Add them twice.
				$list_item_flags   .= esc_attr( $single_flag ) . '="' . esc_attr( $single_flag ) . '" ';
			}
		}

		// Wrap it in a list item.
		$build .= '<li data-plugin="' . esc_attr( $set_plugin_slug ) . '" class="' . esc_attr( $list_item_classes['list-item'] ) . '" title="' . esc_attr( $list_format_args['title'] ) . '">';

			// Handle the checkbox.
			$build .= '<input value="' . absint( $plugin_args['id'] ) . '" type="checkbox" id="' . esc_attr( $list_item_classes['field-id'] ) . '" name="nexcess-mapps-plugin[]" class="' . esc_attr( $list_item_classes['field-class'] ) . '" ' . $list_item_flags . '>';

			// Handle the label.
			$build .= '<label for="' . esc_attr( $list_item_classes['field-id'] ) . '" class="' . esc_attr( $list_item_classes['label-class'] ) . '">' . esc_attr( $plugin_args['identity'] ) . '</label>';

		// Close the list item.
		$build .= '</li>';
	}

	// Close the list markup.
	$build .= '</ul>';

	// Return the formatting.
	return $build;
}

/**
 * Make our buttons for selecting all of one group.
 *
 * @param  array $plugin_groups  Each group name.
 *
 * @return HTML
 */
function format_select_group_buttons( $plugin_groups = array() ) {

	// Bail without groups.
	if ( empty( $plugin_groups ) ) {
		return;
	}

	// Set an empty.
	$build  = '';

	// Loop our groups.
	foreach ( $plugin_groups as $plugin_group ) {

		// Set a classes for the button.
		$sclass = create_button_span_class( $plugin_group, true, 'nexcess-mapps-dashboard-group-action' );
		$bclass = create_button_input_class( $plugin_group, false );

		// Set the things.
		$gname  = texturize_group_name( $plugin_group );
		$btext  = sprintf( __( 'Select All %s', 'nexcess-mapps-dashboard' ), $gname );

		// Set a span around the button.
		$build .= '<li class="' . esc_attr( $sclass ) . '">';

			// Make a button.
			$build .= '<button class="' . esc_attr( $bclass ) . '" type="button" value="' . esc_attr( $plugin_group ) . '">' . $btext . '</button>';

		// Close up the span.
		$build .= '</li>';
	}

	// And return it.
	return $build;
}

/**
 * Make our "select all" and clear (reset) buttons.
 *
 * @return HTML
 */
function format_secondary_select_buttons() {

	// Set up the items our secondary buttons have.
	$secondary_buttons  = array(

		'select-all'    => array(
			'type'    => 'button',
			'label'   => __( 'Select All Plugins', 'nexcess-mapps-dashboard' ),
			'name'    => 'nexcess-mapps-dashboard-select-all',
			'js-only' => true,
			'primary' => false,
		),

		'run-install' => array(
			'type'    => 'submit',
			'label'   => __( 'Install Selected Plugins', 'nexcess-mapps-dashboard' ),
			'name'    => 'nexcess-mapps-dashboard-submit',
			'js-only' => false,
			'primary' => true,
			'spinner' => true,
		),

		'reset-choices' => array(
			'type'    => 'reset',
			'label'   => __( 'Reset Choices', 'nexcess-mapps-dashboard' ),
			'name'    => 'nexcess-mapps-dashboard-reset',
			'js-only' => false,
			'primary' => false,
		),
	);

	// Set an empty.
	$build  = '';

	// Loop our groups.
	foreach ( $secondary_buttons as $button_slug => $setup_args ) {

		// Make sure we have a button type.
		$button_type    = ! empty( $setup_args['type'] ) ? $setup_args['type'] : 'button';

		// Check to add a button arg or name.
		$button_label   = ! empty( $setup_args['label'] ) ? $setup_args['label'] : 'Submit';
		$button_args    = ! empty( $setup_args['type'] ) && 'reset' === esc_attr( $button_type ) ? 'button-small' : '';
		$button_name    = ! empty( $setup_args['name'] ) ? esc_attr( $setup_args['name'] ) : $button_slug;

		// Check for the js-only argument.
		$button_js_only = ! empty( $setup_args['js-only'] ) ? true : false;

		// Figure if the button is primary or not.
		$button_primary = ! empty( $setup_args['primary'] ) ? true : false;

		// Set a class for the button span.
		$span_class     = create_button_span_class( $button_slug, $button_js_only );
		$button_class   = create_button_input_class( $button_slug, $button_primary, $button_args );

		// Set a span around the button.
		$build .= '<li class="' . esc_attr( $span_class ) . '">';

			// Maybe make a spinner?
			if ( ! empty( $setup_args['spinner'] ) ) {
				$build .= '<span class="nexcess-spinner spinner"></span>';
			}

			// Output the button.
			$build .= '<button id="nexcess-button-' . esc_attr( $button_slug ) . '" name="' . esc_attr( $button_name ) . '" class="' . esc_attr( $button_class ) . '" type="' . esc_attr( $button_type ) . '" value="' . esc_attr( $button_slug ) . '">' . esc_html( $button_label ) . '</button>';

		// Close up the span.
		$build .= '</li>';
	}

	// And return it.
	return $build;
}

/**
 * Create our plugin intro content in the tabs.
 *
 * @return HTML
 */
function format_info_tab_intro_content() {

	// Set an empty.
	$build  = '';

	// Set some intro content.
	$build .= '<p>' . esc_html__( 'Want to learn more about the plugins we offer?', 'nexcess-mapps-dashboard' ) . '</p>';
	$build .= '<p>' . esc_html__( 'Select a group name from the menu and see details regarding the plugins.', 'nexcess-mapps-dashboard' ) . '</p>';

	// And return my build.
	return $build;
}

/**
 * Create our plugin list in the tabs.
 *
 * @param  array $plugin_list  Each group name.
 *
 * @return HTML
 */
function format_info_tab_plugin_group_table( $plugin_list = array() ) {

	// Bail without a list to format.
	if ( empty( $plugin_list ) ) {
		return;
	}

	// Set an empty.
	$build  = '';

	// Begin some basic table markup.
	$build .= '<table class="wp-list-table nexcess-mapps-admin-tab-plugin-info-table"><tbody>';

	// Now set some list markup.
	foreach ( $plugin_list as $index => $plugin_args ) {

		// If no info text is available, bail.
		if ( empty( $plugin_args['information_tip'] ) ) {
			continue;
		}

		// Set our row class.
		$row_class  = create_plugin_tab_row_class( $plugin_args['name'], $index );

		// Set up our row item.
		$build .= '<tr class="' . esc_attr( $row_class ) . '">';

			// Wrap our title in a table header tag.
			$build .= '<th scope="row">';
				$build .= '<span class="nexcess-mapps-admin-tab-single-plugin-data nexcess-mapps-admin-tab-single-plugin-data-title">' . esc_html( $plugin_args['identity'] ) . '</span>';
			$build .= '</th>';

			// Wrap our content in a table data tag.
			$build .= '<td>';

				// Output the actual title first in a span.
				$build .= '<span class="nexcess-mapps-admin-tab-single-plugin-data nexcess-mapps-admin-tab-single-plugin-data-text">' . esc_html( $plugin_args['information_tip'] ) . '</span>';

				// Show the link if we have one.
				if ( ! empty( $plugin_args['link'] ) ) {
					$build .= ' <a href="' . esc_url( $plugin_args['link'] ) . '" class="nexcess-mapps-admin-tab-single-plugin-data nexcess-mapps-admin-tab-single-plugin-data-link" target="_blank" rel="noopener noreferrer">' . esc_html__( 'Learn More', 'nexcess-mapps-dashboard' ) . '</a>';
				}

			// Close up the table data.
			$build .= '</td>';

		// Close the individual row item.
		$build .= '</tr>';
	}

	// Close the table.
	$build .= '</tbody></table>';

	// And return my build.
	return $build;
}

/************************************************
 * These functions are more utility-type items.
 ************************************************/

/**
 * Take the name of a group create a class.
 *
 * @param  string  $plugin_group  The current name (slug-like) of the group.
 * @param  boolean $js_only       Whether to include the js-only class.
 * @param  array   $custom_args   Any possible custom classes to add.
 *
 * @return string
 */
function create_button_span_class( $plugin_group = '', $js_only = true, $custom_args = array() ) {

	// First set the array of classes we are gonna use.
	$setup  = array(
		'nexcess-mapps-dashboard-button-action',
		'nexcess-mapps-dashboard-button-' . esc_attr( $plugin_group ) . '-action',
	);

	// Add the custom if they exist.
	if ( ! empty( $custom_args ) ) {
		$setup  = wp_parse_args( (array) $custom_args, $setup );
	}

	// Add the hide JS class if requested.
	if ( ! empty( $js_only ) ) {
		$setup  = wp_parse_args( array( 'hide-if-no-js' ), $setup );
	}

	// Make sure each one is clean.
	$button_classes = array_map( 'sanitize_text_field', $setup );

	// Now return it, all blowed up.
	return implode( ' ', $button_classes );
}

/**
 * Make the class for the actual button itself.
 *
 * @param  string  $plugin_group  The current name (slug-like) of the group.
 * @param  array   $custom_args   Any possible custom classes to add.
 *
 * @return string
 */
function create_button_input_class( $plugin_group = '', $primary = false, $custom_args = array() ) {

	// Set the primary flag.
	$is_primary = empty( $primary ) ? 'button-secondary' : 'button-primary';

	// First set the array of classes we are gonna use.
	$setup  = array(
		'button',
		$is_primary,
		'button-' . esc_attr( $plugin_group ),
		'nexcess-self-install-single-button',
	);

	// Add the custom if they exist.
	if ( ! empty( $custom_args ) ) {
		$setup  = wp_parse_args( $setup, (array) $custom_args );
	}

	// Make sure each one is clean.
	$button_classes = array_map( 'sanitize_text_field', $setup );

	// Now return it, all blowed up.
	return implode( ' ', $button_classes );
}

/**
 * Figure out any flags or setups.
 *
 * @param  string $plugin_slug      The slug of the plugin being listed.
 * @param  array  $plugin_args      The related arguments for the plugin.
 * @param  array  $current_plugins  The plugins currently installed.
 *
 * @return array
 */
function create_plugin_list_formatting( $plugin_slug = '', $plugin_args = array(), $current_plugins = array() ) {

	// First run the check for being enabled.
	if ( in_array( $plugin_slug, $current_plugins ) ) {
		return array(
			'status' => 'installed',
			'flag'   => 'disabled readonly checked',
			'title'  => __( 'This plugin is already installed.', 'nexcess-mapps-dashboard' ),
		);
	}

	// Do the dependency check.
	$maybe_dependencies = Helpers\check_plugin_dependencies( $plugin_slug, $plugin_args );

	// Now check the dependency stuff.
	if ( false !== $maybe_dependencies ) {

		// Set my flag title.
		$flag_title = sprintf( __( 'This plugin requires that %s also be installed and activated.', 'nexcess-mapps-dashboard' ), $maybe_dependencies );

		// Return the flag.
		return array(
			'status' => 'dependency',
			'flag'   => 'disabled readonly',
			'title'  => $flag_title,
		);
	}

	// Maybe more later, but not now.
	return array(
		'status' => 'available',
		'flag'   => '',
		'title'  => sprintf( __( 'Install the %s plugin.', 'nexcess-mapps-dashboard' ), esc_html( $plugin_args['identity'] ) ),
	);
}

/**
 * Make the class for a single label plugin.
 *
 * @param  string $plugin_slug      The slug of the plugin being listed.
 * @param  array  $plugin_args      The related arguments for the plugin.
 * @param  array  $current_plugins  The plugins currently installed.
 *
 * @return string
 */
function create_plugin_list_classes( $plugin_slug = '', $plugin_args = array(), $current_plugins = array() ) {

	// First set the base classes.
	$input_field_id     = 'nexcess-mapps-dashboard-' . esc_attr( $plugin_slug );
	$list_item_class    = 'nexcess-mapps-dashboard-plugin-list-single nexcess-mapps-dashboard-plugin-list-single-' . esc_attr( $plugin_slug );
	$input_field_class  = 'nexcess-mapps-dashboard-plugin-input nexcess-mapps-dashboard-plugin-' . esc_attr( $plugin_slug ) . '-input nexcess-mapps-dashboard-plugin-checkbox';
	$label_field_class  = 'nexcess-mapps-dashboard-plugin-label nexcess-mapps-dashboard-plugin-' . esc_attr( $plugin_slug ) . '-label';

	// Now the checks for it already being installed.
	if ( in_array( $plugin_slug, $current_plugins ) ) {
		$list_item_class   .= ' nexcess-mapps-dashboard-plugin-list-single-installed';
		$input_field_class .= ' nexcess-mapps-dashboard-plugin-input-installed';
		$label_field_class .= ' nexcess-mapps-dashboard-plugin-label-installed';
	}

	// Now the secondary checks for it already being installed.
	if ( ! in_array( $plugin_slug, $current_plugins ) ) {
		$list_item_class   .= ' nexcess-mapps-dashboard-plugin-list-single-available';
		$input_field_class .= ' nexcess-mapps-dashboard-plugin-input-available';
		$label_field_class .= ' nexcess-mapps-dashboard-plugin-label-available';
	}

	// Add the class for Iconic products.
	if ( ! empty( $plugin_args['vendor'] ) && 'iconic' === sanitize_text_field( $plugin_args['vendor'] ) ) {
		$list_item_class   .= ' nexcess-mapps-dashboard-plugin-list-iconic-plugin';
	}

	// Now check the restricted stuff.
	if ( ! empty( $plugin_args['status'] ) && 'restricted' === sanitize_text_field( $plugin_args['status'] ) ) {
		$list_item_class   .= ' nexcess-mapps-dashboard-plugin-list-restricted';
	}

	// Do the dependency check.
	$maybe_dependencies = Helpers\check_plugin_dependencies( $plugin_slug, $plugin_args );

	// Now check the dependency stuff.
	if ( false !== $maybe_dependencies ) {
		$list_item_class   .= ' nexcess-mapps-dashboard-plugin-list-restricted';
	}

	// Create an array of the three values to return.
	return array(
		'list-item'   => $list_item_class,
		'field-id'    => $input_field_id,
		'field-class' => $input_field_class,
		'label-class' => $label_field_class,
	);
}

/**
 * Make the class for a single plugin in a table row.
 *
 * @param  string  $plugin_slug  The slug of the plugin being listed.
 * @param  integer $increment    The increment counter so we can add a "first".
 *
 * @return string
 */
function create_plugin_tab_row_class( $plugin_slug = '', $increment = 1 ) {

	// First set the array of classes we are gonna use.
	$setup  = array(
		'nexcess-mapps-admin-tab-single-plugin',
		'nexcess-mapps-admin-tab-' . sanitize_html_class( $plugin_slug ) . '-plugin',
	);

	// If this is the first, add the 'first'.
	if ( absint( $increment ) < 1 ) {
		$setup  = wp_parse_args( array( 'nexcess-mapps-admin-tab-single-plugin-first' ), $setup );
	}

	// Make sure each one is clean.
	$setup_classes  = array_map( 'sanitize_text_field', $setup );

	// Now return it, all blowed up.
	return implode( ' ', $setup_classes );
}

/**
 * Take the name of a group and make it for display.
 *
 * @param  string $plugin_group  The current name (slug-like) of the group.
 *
 * @return string
 */
function texturize_group_name( $plugin_group = '' ) {

	// First convert underscores and dashes to spaces.
	$group_name = str_replace( array( '-', '_' ), ' ', $plugin_group );

	// "Theme" => "Themes".
	if ( 'theme' === $group_name ) {
		$group_name = 'Themes';
	}

	// Now make it all cap'd.
	return ucwords( trim( $group_name ) );
}

/**
 * Take the name of a group and make it for HTML.
 *
 * @param  string $group_name  The current name (display-like) of the group.
 *
 * @return string
 */
function classify_group_name( $group_name = '' ) {

	// First convert underscores and spaces to spaces.
	$class_name = str_replace( array( ' ', '_' ), '-', $group_name );

	// Now make it all lower'd.
	return strtolower( trim( $class_name ) );
}
