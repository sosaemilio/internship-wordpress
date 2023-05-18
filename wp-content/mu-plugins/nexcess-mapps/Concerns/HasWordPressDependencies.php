<?php

namespace Nexcess\MAPPS\Concerns;

use StellarWP\PluginFramework\Support\Branding;

use const Nexcess\MAPPS\VENDOR_DIR;

trait HasWordPressDependencies {

	/**
	 * Determine if the current version of WordPress is at least $version.
	 *
	 * @param string $version The version to check against.
	 *
	 * @return bool
	 */
	public function siteIsAtLeastWordPressVersion( $version ) {
		return $this->currentWordPressVersionIs( '>=', $version );
	}

	/**
	 * Check the WordPress version.
	 *
	 * @param string $operator The operator to use.
	 * @param string $version  The version to check against.
	 *
	 * @return bool
	 */
	public function currentWordPressVersionIs( $operator, $version ) {
		return version_compare( $this->getNormalizedWordPressVersion(), $version, $operator );
	}

	/**
	 * Normalize the WordPress core release number.
	 *
	 * Stable WordPress releases are in x.y.z format, but can have pre-release versions,
	 * e.g. "5.4-RC4-47505-src".
	 *
	 * We want, for example. 5.4-RC4-47505-src to be considered equal to 5.4, so strip out
	 * the pre-release portion.
	 *
	 * @return string WordPress version.
	 */
	public function getNormalizedWordPressVersion() {
		global $wp_version;

		return preg_replace( '/-.+$/', '', $wp_version );
	}

	/**
	 * Determine whether or not the block editor is enabled for the given post type(s).
	 *
	 * If multiple post types are provided, the function will ensure that the editor is enabled
	 * for *all* of the given types.
	 *
	 * @param string[] ...$post_types The post type(s) to check.
	 *
	 * @return bool Whether or not the Block Editor is enabled for all of the given post types.
	 */
	public function blockEditorIsEnabledFor( ...$post_types ) {
		/*
		 * Ensure the use_block_editor_for_post_type() function, which was introduced in WordPress
		 * version 5.0, exists.
		 *
		 * If not (and assuming it hasn't been polyfilled), assume the store is using the Gutenberg
		 * feature plugin, which may or may not work the way we expect.
		 */
		if ( ! function_exists( 'use_block_editor_for_post_type' ) ) {
			return false;
		}

		foreach ( $post_types as $post_type ) {
			if ( ! use_block_editor_for_post_type( $post_type ) ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Verify that a plugin is installed.
	 *
	 * This is a wrapper around WordPress' get_plugins() function, ensuring the necessary
	 * file is installed before checking.
	 *
	 * @see get_plugins()
	 *
	 * @param string $plugin The plugin directory and file.
	 *
	 * @return bool
	 */
	public function isPluginInstalled( $plugin ) {
		if ( ! function_exists( 'get_plugins' ) ) {
			include_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		// Fetch all the plugins we have installed.
		$all_plugins = get_plugins();

		return array_key_exists( $plugin, $all_plugins );
	}

	/**
	 * Determine whether or not a particular mu-plugin is present.
	 *
	 * Since mu-plugins are loaded in alphabetical order, this is necessary to see if mu-plugins
	 * *after* "nexcess-mapps" will be loaded.
	 *
	 * @param string $text_domain The plugin text-domain to check for.
	 *
	 * @return bool Whether or not the given plugin text-domain is present as an mu-plugin.
	 */
	public function isMuPluginInstalled( $text_domain ) {
		if ( ! function_exists( 'get_mu_plugins' ) ) {
			include_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		foreach ( get_mu_plugins() as $plugin ) {
			if ( ! empty( $plugin['TextDomain'] ) && $text_domain === $plugin['TextDomain'] ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Verify that a plugin is both installed and active.
	 *
	 * This is a wrapper around WordPress' is_plugin_active() function, ensuring the necessary
	 * file is loaded before checking.
	 *
	 * @see is_plugin_active()
	 *
	 * @param string $plugin The directory/file path.
	 *
	 * @return bool
	 */
	public function isPluginActive( $plugin ) {
		if ( ! function_exists( 'is_plugin_active' ) ) {
			include_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		return is_plugin_active( $plugin );
	}

	/**
	 * Determine whether or not at least one of the given plugins is active.
	 *
	 * This is essentially a way of running isPluginActive() against an array of possibilities.
	 *
	 * @param string[] $plugins An array of plugin paths to check.
	 *
	 * @return bool True if at least one of the $plugins is active, false otherwise.
	 */
	public function isAtLeastOnePluginActive( $plugins ) {
		return ! empty( $this->findActivePlugins( $plugins ) );
	}

	/**
	 * Return an array of which plugins are active from an array of plugins.
	 *
	 * @param string[] $plugins An array of plugin paths to check.
	 *
	 * @return string[] An array of the plugins sent which are also active on the site.
	 */
	public function findActivePlugins( $plugins ) {
		$active_plugins = get_option( 'active_plugins', [] );

		// Fix for when the active_plugins option is an empty string.
		if ( ! is_array( $active_plugins ) ) {
			return [];
		}

		return array_intersect( $plugins, $active_plugins );
	}

	/**
	 * Determine if the current request is related to activating the given plugin.
	 *
	 * If we're forcibly activating a plugin in an integration, we want to make sure that
	 * integration is not being loaded on the activation request, or risk failures due to plugin
	 * classes/functions already being defined.
	 *
	 * @param string $plugin The plugin to check for.
	 *
	 * @return bool
	 */
	public function isPluginBeingActivated( $plugin ) {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		$wp_admin = isset( $_SERVER['PHP_SELF'], $_GET['action'], $_GET['plugin'] )
			&& '/wp-admin/plugins.php' === $_SERVER['PHP_SELF']
			&& 'activate' === $_GET['action']
			&& $plugin === $_GET['plugin'];
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		$dirname = dirname( $plugin );
		$wp_cli  = did_action( "Nexcess\\MAPPS\\WP-CLI\\activating_plugin_{$dirname}" );

		return $wp_admin || $wp_cli;
	}

	/**
	 * Load another plugin's bootstrap file.
	 *
	 * @param string $plugin The plugin file, relative to VENDOR_DIR.
	 */
	public function loadPlugin( $plugin ) {
		$file = VENDOR_DIR . $plugin;

		if ( is_readable( $file ) ) {
			require_once $file;

			/**
			 * Fires after the Nexcess MAPPS plugin bootstraps another plugin.
			 *
			 * @param string $file The full filepath to the bootstrap file.
			 */
			do_action( 'Nexcess\\MAPPS\\load_plugin:' . $plugin, $file );
		} else {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_trigger_error
			trigger_error(
				/* Translators: %1$s is the integration filepath. */
				esc_html( sprintf( __( '%1$s - Unable to load integration from: %2$s', 'nexcess-mapps' ), Branding::getPlatformName(), $file ) ),
				E_USER_WARNING
			);
		}
	}

	/**
	 * Get the name of a plugin based the plugin file.
	 *
	 * @param string $plugin The plugin directory/file name.
	 *
	 * @return string The plugin name (if it can be determined) or the plugin file if a name cannot
	 *                be determined.
	 */
	public function getPluginName( $plugin ) {
		if ( ! function_exists( 'get_plugins' ) ) {
			include_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		$plugins = get_plugins();

		return isset( $plugins[ $plugin ]['Name'] ) ? $plugins[ $plugin ]['Name'] : $plugin;
	}

	/**
	 * Activate a plugin.
	 *
	 * This is a wrapper around WordPress' activate_plugin() function, ensuring the necessary file
	 * is loaded before checking.
	 *
	 * @see activate_plugin()
	 *
	 * @param string $plugin The plugin file to activate.
	 * @param bool   $silent Whether or not to bypass activation hooks. Default is false.
	 */
	public function activatePlugin( $plugin, $silent = false ) {
		if ( ! function_exists( 'activate_plugin' ) ) {
			include_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		activate_plugin( $plugin, '', false, $silent );
	}

	/**
	 * Deactivate one or more plugins.
	 *
	 * This is a wrapper around WordPress' deactivate_plugins() function, ensuring the necessary
	 * file is loaded before checking.
	 *
	 * @see deactivate_plugins()
	 *
	 * @param string|string[] $plugins The plugin file(s) to deactivate.
	 * @param bool            $silent  Whether or not to bypass deactivation hooks. Default is false.
	 */
	public function deactivatePlugin( $plugins, $silent = false ) {
		if ( ! function_exists( 'deactivate_plugins' ) ) {
			include_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		deactivate_plugins( $plugins, $silent );
	}

	/**
	 * Get specified plugin's version.
	 *
	 * @param string $plugin_file "plugin-directory/plugin-file.php".
	 *
	 * @return string
	 */
	public function getPluginVersion( $plugin_file ) {
		if ( ! function_exists( 'get_plugin_data' ) ) {
			include_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		$plugins = get_plugins();

		if ( ! array_key_exists( $plugin_file, $plugins ) ) {
			return '';
		}

		$data = $plugins[ $plugin_file ];

		if ( empty( $data['Version'] ) ) {
			return '';
		}

		return $data['Version'];
	}

	/**
	 * Get plugin's version, and compare.
	 *
	 * @param string $plugin_file
	 * @param string $expected_version
	 *
	 * @return bool
	 */
	public function isPluginVersion( $plugin_file, $expected_version ) {
		$actual_version = $this->getPluginVersion( $plugin_file );

		if ( empty( $actual_version ) ) {
			return false;
		}

		return $actual_version === $expected_version;
	}
}
