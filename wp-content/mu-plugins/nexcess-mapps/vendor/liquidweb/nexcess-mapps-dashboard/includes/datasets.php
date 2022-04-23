<?php
/**
 * The various items to handle getting and parsing data.
 *
 * @package PlatformSelfInstall
 */

// Call our namepsace.
namespace Nexcess\MAPPS\Dashboard\Datasets;

// Set our alias items.
use Nexcess\MAPPS\Dashboard as Core;
use Nexcess\MAPPS\Dashboard\Helpers as Helpers;
use Nexcess\MAPPS\Dashboard\Utilities as Utilities;
use Nexcess\MAPPS\Dashboard\External as External;

// And pull in any other namespaces.
use WP_Error;

/**
 * This is a hardcoded dataset (for now) for the
 * introduction content displayed in the installer.
 *
 * @return array
 */
function get_intro_content_data() {

	// Do our check for MWCH.
	$maybe_mwch = Utilities\maybe_is_mwch();

	// If this is MWCH, return that.
	if ( false !== $maybe_mwch ) {

		// This is the content for MWCH.
		return array(
			'headline'  => 'Say Hello To Your Super Powered WooCommerce Store!',
			'subtitles' => array(
				'The <a href="https://www.nexcess.net/">Nexcess</a> Managed WooCommerce hosting platform is unique in that it\'s been tailored <strong><em>specifically</em></strong> to running online stores, while also providing you much more than you\'d normally get from any other hosting company when setting up your WooCommerce store. Check out the options that are available to you. And add as many or as few of them as you like to your store, with a single click.',
				'If you believe your site is missing a plugin from this list, <strong>please contact our support team and we will assist you.</strong> Please note that this includes WPMerge, iThemes Sync, Abandoned Cart By Jilt, Dokan Pro, Shopmaster, and Glew Reporting which are currently installed upon request.',
			),
		);

	} else {

		// This is our content for a WordPress (non MWCH) site.
		return array(
			'headline'  => 'Say Hello To Your Super Powered WordPress Website!',
			'subtitles' => array(
				'The <a href="https://www.nexcess.net/">Nexcess</a> Managed WordPress hosting platform is unique in that it\'s been tailored <strong><em>specifically</em></strong> to running WordPress, while also providing you much more than you\'d normally get from any other hosting company when setting up your WordPress website. Check out the options that are available to you. And add as many or as few of them as you like to your website with a single click.',
				'If you believe your site is missing a plugin from this list, <strong>please contact our support team and we will assist you.</strong> Please note that this includes WPMerge and iThemes Sync, which are currently installed upon request.',
			),
		);

	}

	// Eventually this may come from the API.
}

/**
 * Get our plugin dataset in an array we can use.
 *
 * @param  boolean $grouped  Whether to group them together or return the raw.
 *
 * @return array
 */
function get_available_plugins_dataset( $grouped = true ) {

	// First get my raw plugin dataset.
	$raw_plugin_dataset = External\fetch_available_plugins_list();

	// Make sure we have some kind of dataset to use.
	if ( empty( $raw_plugin_dataset ) ) {
		return new WP_Error( 'missing_raw_dataset', __( 'The raw plugin dataset could not be generated.', 'nexcess-mapps-dashboard' ) );
	}

	// Throw the error if we have it.
	if ( is_wp_error( $raw_plugin_dataset ) ) {
		return new WP_Error( $raw_plugin_dataset->get_error_code(), $raw_plugin_dataset->get_error_message() );
	}

	// Store the data before we do anything to it.
	// update_option( Core\OPTION_PREFIX . 'raw_dataset', $raw_plugin_dataset, 'no' );

	// If we don't want the grouping, return it.
	if ( false === $grouped ) {
		return $raw_plugin_dataset;
	}

	// Get a list of our groups.
	$pull_single_groups = Helpers\get_plugin_groups( $raw_plugin_dataset, 'group' );

	// If we have no groups, just return it.
	if ( empty( $pull_single_groups ) ) {
		return array( 'unknown' => $raw_plugin_dataset );
	}

	// Now set our sort order.
	$set_group_columns  = array( 'performance', 'features', 'integrations', 'premium-tools' );

	// Set an empty.
	$set_plugins_parsed = array();

	// Now we make a big array.
	foreach ( $raw_plugin_dataset as $plugin_slug => $plugin_args ) {

		// Set the group this plugin belongs to.
		$plugin_group   = ! empty( $plugin_args['group'] ) ? $plugin_args['group'] : 'unknown';

		// Now loop each group name.
		foreach ( $pull_single_groups as $group_name ) {

			// Not the same, we don't care.
			if ( sanitize_text_field( $plugin_args['group'] ) !== sanitize_text_field( $group_name ) ) {
				continue;
			}

			// Add this to the array holding the group name.
			$set_plugins_parsed[ $group_name ][ $plugin_slug ] = $plugin_args;
		}

		// That's it for each plugin.
	}

	// Return our big parsed list.
	return $set_plugins_parsed;
}

/**
 * Run the command to get our currently installed plugins.
 *
 * @param  boolean $purge  Optional to purge the cache'd version before looking up.
 *
 * @return mixed
 */
function build_current_installed_plugins( $purge = false ) {

	// Set the key to use in our transient.
	$ky = Core\HOOK_PREFIX . 'installed_plugins';

	// Attempt to get the reviews from the cache.
	$cached_dataset = get_transient( $ky );

	// If we don't have or want the cache'd version, run the actual data query.
	if ( false === $cached_dataset || ( defined( 'WP_DEBUG' ) && WP_DEBUG ) || ! empty( $purge ) ) {

		// First get the list of all the plugins.
		$all_installed_plugins  = get_plugins();

		// Bail if we don't have anything.
		if ( empty( $all_installed_plugins ) ) {
			return false;
		}

		// Set up an empty array for our return.
		$set_plugin_list    = array();

		// Now loop each plugin from the list and set accordingly.
		foreach ( $all_installed_plugins as $single_file_path => $single_plugin_args ) {

			// Add the slug to the plugin list.
			$set_plugin_list[] = Utilities\format_plugin_file_path( $single_file_path );
		}

		// Create my theme name.
		$setup_theme_name   = get_option( 'template', '' ) . '-theme';

		// Now include the current theme.
		$set_plugin_list[]  = sanitize_text_field( $setup_theme_name );

		// Bail if we don't have anything.
		if ( empty( $set_plugin_list ) ) {
			return false;
		}

		// Set our transient with our data.
		set_transient( $ky, $set_plugin_list, Core\CACHE_TIME );

		// And change the variable to do the things.
		$cached_dataset = $set_plugin_list;
	}

	// Return the entire dataset.
	return $cached_dataset;
}

/**
 * Run the command to get our currently installed and activated plugins.
 *
 * @param  boolean $purge  Optional to purge the cache'd version before looking up.
 *
 * @return mixed
 */
function build_current_activated_plugins( $purge = false ) {

	// Set the key to use in our transient.
	$ky = Core\HOOK_PREFIX . 'active_plugins';

	// Attempt to get the reviews from the cache.
	$cached_dataset = get_transient( $ky );

	// If we don't have or want the cache'd version, run the actual data query.
	if ( false === $cached_dataset || ( defined( 'WP_DEBUG' ) && WP_DEBUG ) || ! empty( $purge ) ) {

		// Get our list of installed plugins.
		$all_installed_plugins  = get_plugins();

		// Bail if we don't have anything installed.
		if ( empty( $all_installed_plugins ) ) {
			return false;
		}

		// Set and empty array.
		$set_active_list    = array();

		// Now loop each plugin from the list and set accordingly.
		foreach ( $all_installed_plugins as $installed_file_path => $installed_plugin_args ) {

			// Check to see if this plugin is active.
			if ( ! is_plugin_active( $installed_file_path ) ) {
				continue;
			}

			// Add the slug to the plugin list.
			$set_active_list[] = Utilities\format_plugin_file_path( $installed_file_path );
		}

		// Create my theme name.
		$setup_theme_name  = get_option( 'template', '' ) . '-theme';

		// Now include the current theme.
		$set_active_list[] = sanitize_text_field( $setup_theme_name );

		// Set our transient with our data.
		set_transient( $ky, $set_active_list, Core\CACHE_TIME );

		// And change the variable to do the things.
		$cached_dataset = $set_active_list;
	}

	// Return the entire dataset.
	return $cached_dataset;
}

/**
 * Make the required API calls to get the install instructions for each plugin.
 *
 * @param  array   $plugin_ids  The individual IDs we requested.
 * @param  boolean $args_only   Whether to only return the install path args.
 *
 * @return array
 */
function get_plugin_install_instructions( $plugin_ids = array(), $args_only = false ) {

	// Bail without the IDs.
	if ( empty( $plugin_ids ) ) {
		return new WP_Error( 'missing_required_ids', __( 'The required plugin IDs were not provided.', 'nexcess-mapps-dashboard' ) );
	}

	// Now set an empty return.
	$instructions_set   = array();

	// Include a potential set of license IDs.
	$pending_licensing  = array();

	// Now loop each one and make the API call.
	foreach ( $plugin_ids as $plugin_id ) {

		// Attempt to get the instructions.
		$fetch_instructions = External\fetch_single_plugin_install_instructions( $plugin_id );

		// Bail if the return is bad.
		if ( is_wp_error( $fetch_instructions ) ) {
			return $fetch_instructions;
		}

		// Bail with no return at all.
		if ( empty( $fetch_instructions ) || empty( $fetch_instructions['install_script']['plugin'] ) ) {
			continue; // @todo figure out how to handle this.
		}

		// If we have a license type, set a note for now.
		if ( ! empty( $fetch_instructions['license_type'] ) && 'none' !== esc_attr( $fetch_instructions['license_type'] ) ) {

			// Set my array key and values.
			//
			// I am not sure why we are keeping the license type
			// yet but it may be needed for more complicated ones.
			$set_array_key  = absint( $plugin_id );
			$set_array_val  = sanitize_text_field( $fetch_instructions['license_type'] );

			// Set an array of the ID and type.
			$pending_licensing[ $set_array_key ] = $set_array_val;
		}

		// Now add the install script to the array array.
		$instructions_set[ $plugin_id ] = $fetch_instructions['install_script']['plugin']; // $fetch_instructions;
	}

	// Return an error if no instructions exist.
	if ( empty( $instructions_set ) ) {
		return new WP_Error( 'empty_install_instructions', __( 'The installation setup for these plugins could not be determined.', 'nexcess-mapps-dashboard' ) );
	}

	// If we have some license instructions, set the option.
	if ( ! empty( $pending_licensing ) ) {
		Helpers\update_pending_licensing( $pending_licensing );
	}

	// Return the resulting array.
	return false !== $args_only ? wp_list_pluck( $instructions_set, 'install', null ) : $instructions_set;
}

/**
 * Make the required API calls to get the licensing instructions for each plugin.
 *
 * @param  array   $plugin_ids  The individual IDs we requested.
 * @param  boolean $args_only   Whether to only return the install path args.
 *
 * @return array
 */
function get_plugin_licensing_instructions( $plugin_ids = array(), $args_only = false ) {

	// Bail without the IDs.
	if ( empty( $plugin_ids ) ) {
		return new WP_Error( 'missing_required_ids', __( 'The required plugin IDs were not provided.', 'nexcess-mapps-dashboard' ) );
	}

	// Now set an empty return.
	$instructions_set   = array();

	// Now loop each one and make the API call.
	foreach ( $plugin_ids as $plugin_id ) {

		// Attempt to get the instructions.
		$fetch_instructions = External\fetch_single_plugin_license_instructions( $plugin_id );

		// Bail if the return is bad.
		if ( is_wp_error( $fetch_instructions ) ) {
			return $fetch_instructions;
		}

		// Bail with no return at all.
		if ( empty( $fetch_instructions ) || empty( $fetch_instructions['licensing_script']['plugin'] ) ) {
			continue; // @todo figure out how to handle this.
		}

		// Now add the install script to the array array.
		$instructions_set[ $plugin_id ] = $fetch_instructions['licensing_script']['plugin']; // $fetch_instructions;
	}

	// Return an error if no instructions exist.
	if ( empty( $instructions_set ) ) {
		return new WP_Error( 'empty_licensing_instructions', __( 'The licensing setup for these plugins could not be determined.', 'nexcess-mapps-dashboard' ) );
	}

	// Return the resulting array.
	return false !== $args_only ? wp_list_pluck( $instructions_set, 'licensing_script', null ) : $instructions_set;
}


/**
 * Get the data array for the Iconic plugins.
 *
 * @param  string $single  Return a single plugin's data.
 * @param  string $keyset  Return a single key from each plugin's data.
 *
 * @return array
 */
function iconic_activation_dataset( $single = '', $keyset = '' ) {

	// Begin building the array.
	$iconic_dataset = array(

		'iconic-woothumbs' => array(
			'label'   => 'WooThumbs for WooCommerce',
			'class'   => '\Iconic_WooThumbs_Core_Licence',
			'dismiss' => 'iconic_woothumbs_notice_dismiss_getting_started',
			'menu'    => 'wpsf_register_settings_iconic_woothumbs',
			'docs'    => 'https://docs.iconicwp.com/collection/110-woothumbs',
		),

		'iconic-woo-show-single-variations' => array(
			'label'   => 'WooCommerce Show Single Variations',
			'class'   => '\Iconic_WSSV_Core_Licence',
			'dismiss' => 'iconic_wssv_notice_dismiss_getting_started',
			'menu'    => 'wpsf_register_settings_iconic_wssv',
			'docs'    => 'https://docs.iconicwp.com/collection/84-woocommerce-show-single-variations',
		),

		'iconic-woo-delivery-slots' => array(
			'label'   => 'WooCommerce Delivery Slots',
			'class'   => '\Iconic_WDS_Core_Licence',
			'dismiss' => 'iconic_wds_notice_dismiss_getting_started',
			'menu'    => 'wpsf_register_settings_iconic_wds',
			'docs'    => 'https://docs.iconicwp.com/collection/120-woocommerce-delivery-slots',
		),

		'iconic-woo-linked-variations' => array(
			'label'   => 'WooCommerce Linked Variations',
			'class'   => '\Iconic_WLV_Core_Licence',
			'dismiss' => 'iconic_wlv_notice_dismiss_getting_started',
			'menu'    => 'wpsf_register_settings_iconic_wlv',
			'docs'    => 'https://docs.iconicwp.com/collection/172-woocommerce-linked-variations',
		),

		'iconic-woo-product-configurator' => array(
			'label'   => 'WooCommerce Product Configurator',
			'class'   => '\Iconic_PC_Core_Licence',
			'dismiss' => 'iconic_woo_product_configurator_notice_dismiss_getting_started',
			'menu'    => 'wpsf_register_settings_iconic_woo_product_configurator',
			'docs'    => 'https://docs.iconicwp.com/collection/126-woocommerce-product-configurator',
		),

		'iconic-woo-quicktray' => array(
			'label'   => 'WooCommerce QuickTray',
			'class'   => '\Iconic_WQT_Core_Licence',
			'dismiss' => 'iconic_wqt_notice_dismiss_getting_started',
			'menu'    => 'wpsf_register_settings_iconic_wqt',
			'docs'    => 'https://docs.iconicwp.com/collection/204-woocommerce-quicktray',
		),

		'iconic-woo-account-pages' => array(
			'label'   => 'WooCommerce Account Pages',
			'class'   => '\Iconic_WAP_Core_Licence',
			'dismiss' => 'iconic_wap_notice_dismiss_getting_started',
			'menu'    => 'wpsf_register_settings_iconic_wap',
			'docs'    => 'https://docs.iconicwp.com/collection/101-woocommerce-account-pages',
		),

		'iconic-woo-quickview' => array(
			'label'   => 'WooCommerce Quickview',
			'class'   => '\Iconic_WQV_Core_Licence',
			'dismiss' => 'jckqv_notice_dismiss_getting_started',
			'menu'    => 'wpsf_register_settings_jckqv',
			'docs'    => 'https://docs.iconicwp.com/collection/158-woocommerce-quickview',
		),

		'iconic-woo-attribute-swatches' => array(
			'label'   => 'WooCommerce Attribute Swatches',
			'class'   => '\Iconic_WAS_Core_Licence',
			'dismiss' => 'iconic_was_notice_dismiss_getting_started',
			'menu'    => 'wpsf_register_settings_iconic_was',
			'docs'    => 'https://docs.iconicwp.com/collection/134-woocommerce-attribute-swatches',
		),

		'iconic-woo-custom-fields-for-variations' => array(
			'label'   => 'WooCommerce Custom Fields for Variations',
			'class'   => '\Iconic_CFFV_Core_Licence',
			'dismiss' => 'iconic_cffv_notice_dismiss_getting_started',
			'menu'    => 'wpsf_register_settings_iconic_cffv',
			'docs'    => 'https://docs.iconicwp.com/collection/146-woocommerce-custom-fields-for-variations',
		),

		'iconic-woo-bundled-products' => array(
			'label'   => 'WooCommerce Bundled Products',
			'class'   => '\Iconic_WBP_Core_Licence',
			'dismiss' => 'iconic_wbp_notice_dismiss_getting_started',
			'menu'    => 'wpsf_register_settings_iconic_wbp',
			'docs'    => 'https://docs.iconicwp.com/collection/140-woocommerce-bundled-products',
		),

		// Add more plugins as needed.
	);

	// Return the entire dataset if we didn't request a single.
	if ( empty( $single ) && empty( $keyset ) ) {
		return $iconic_dataset;
	}

	// Return the requested keyset or false.
	if ( ! empty( $keyset ) ) {
		return wp_list_pluck( $iconic_dataset, $keyset );
	}

	return empty( $iconic_dataset[ $single ] ) ? false : $iconic_dataset[ $single ];
}
