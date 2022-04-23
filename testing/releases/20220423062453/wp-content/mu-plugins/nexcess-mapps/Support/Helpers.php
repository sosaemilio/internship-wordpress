<?php

/**
 * Helper methods for Nexcess MAPPS.
 */

namespace Nexcess\MAPPS\Support;

use WP_User;

class Helpers {

	/**
	 * Perform a URL-safe base64_encode().
	 *
	 * @link https://www.php.net/manual/en/function.base64-encode.php#123098
	 *
	 * @param string $string The string to encode.
	 *
	 * @return string The encoded string.
	 */
	public static function base64_urlencode( $string ) {
		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
		return str_replace( [ '+', '/', '=' ], [ '-', '_', '' ], base64_encode( $string ) );
	}

	/**
	 * Perform a URL-safe base64_deencode().
	 *
	 * @link https://www.php.net/manual/en/function.base64-encode.php#123098
	 *
	 * @param string $string The string to decode.
	 *
	 * @return string The decoded string.
	 */
	public static function base64_urldecode( $string ) {
		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode
		return base64_decode( str_replace( [ '-', '_' ], [ '+', '/' ], $string ) );
	}

	/**
	 * Print either "Enabled" or "Disabled" based on the value of $is_enabled.
	 *
	 * @param bool $is_enabled Whether or not a particular flag is enabled.
	 */
	public static function enabled( $is_enabled ) {
		echo esc_html( self::getEnabled( $is_enabled ) );
	}

	/**
	 * Return either "Enabled" or "Disabled" based on the value of $is_enabled.
	 *
	 * @param bool $is_enabled Whether or not a particular flag is enabled.
	 *
	 * @return string One of "Enabled" or "Disabled".
	 */
	public static function getEnabled( $is_enabled ) {
		return $is_enabled
			? _x( 'Enabled', 'setting state', 'nexcess-mapps' )
			: _x( 'Disabled', 'setting state', 'nexcess-mapps' );
	}

	/**
	 * Create a URL to activate the given $plugin through WP Admin.
	 *
	 * This creates a URL with the following pattern:
	 *
	 *     {url}/wp-admin/plugin.php?action=activate&plugin={plugin}&_wpnonce={nonce}
	 *
	 * @param string $plugin The plugin path (e.g. "some-plugin/some-plugin.php").
	 *
	 * @return string
	 */
	public static function getPluginActivationUrl( $plugin ) {
		return add_query_arg( [
			'action'   => 'activate',
			'plugin'   => $plugin,
			'_wpnonce' => wp_create_nonce( 'activate-plugin_' . $plugin ),
		], admin_url( 'plugins.php' ) );
	}

	/**
	 * Create a URL to install the given $plugin through WP Admin.
	 *
	 * This creates a URL with the following pattern:
	 *
	 *     {url}/wp-admin/update.php?action=install-plugin&plugin={plugin}&_wpnonce={nonce}
	 *
	 * @param string $plugin The plugin slug.
	 *
	 * @return string
	 */
	public static function getPluginInstallationUrl( $plugin ) {
		return add_query_arg( [
			'action'   => 'install-plugin',
			'plugin'   => $plugin,
			'_wpnonce' => wp_create_nonce( 'install-plugin_' . $plugin ),
		], admin_url( 'update.php' ) );
	}

	/**
	 * Get the MAPPS portal URL for the given account ID.
	 *
	 * @param ?int    $plan_id Optional. The plan ID. Default is null.
	 * @param ?int    $site_id Optional. The site ID. Default is null.
	 * @param ?string $path    Optional. A path to append. Default is empty.
	 *
	 * @return string The absolute URL to the Nexcess portal URL.
	 */
	public static function getPortalUrl( $plan_id = null, $site_id = null, $path = '' ) {
		$url = '';

		if ( $plan_id ) {
			$url .= sprintf( '/plans/%d', $plan_id );

			if ( $site_id ) {
				$url .= sprintf( '/sites/%d', $site_id );
			}
		}

		// Strip the leading slash on $path, if it exists.
		if ( $path && 0 === mb_strpos( $path, '/' ) ) {
			$path = mb_substr( $path, 1 );
		}

		// If we still have anything in $path, append it.
		if ( $path ) {
			$url .= sprintf( '/%s', $path );
		}

		return sprintf( 'https://my.nexcess.net%s', $url );
	}

	/**
	 * Given a user, generate their unique reset password link.
	 *
	 * @param \WP_User $user       The user for whom a reset password link should be generated.
	 * @param array    $extra_args Any extra args to be added to the url.
	 *
	 * @return string The reset password URL.
	 */
	public static function getResetPasswordUrl( WP_User $user, $extra_args = [] ) {
		$args = [
			'action' => 'rp',
			'key'    => get_password_reset_key( $user ),
			'login'  => rawurlencode( $user->user_login ),
		];

		$args = array_merge( $args, $extra_args );

		return network_site_url( add_query_arg( $args, 'wp-login.php' ) );
	}

	/**
	 * Determine whether or not a file is a broken symlink.
	 *
	 * @param string $filepath The filepath to inspect.
	 *
	 * @return bool True if the file is a symlink with a missing target, false otherwise.
	 */
	public static function isBrokenSymlink( $filepath ) {
		if ( ! is_link( $filepath ) ) {
			return false;
		}

		return ! file_exists( (string) readlink( $filepath ) );
	}

	/**
	 * Extract the domain portion of a URL.
	 *
	 * @param string $url The URL to parse.
	 *
	 * @return string Only the domain of the given URL, or an empty string if one cannot be parsed.
	 */
	public static function parseDomain( $url ) {
		// Add a protocol if one isn't present.
		if ( false === mb_strpos( $url, '://' ) ) {
			$url = 'http://' . $url;
		}

		/** @var string|false $domain */
		$domain = wp_parse_url( $url, PHP_URL_HOST );

		return $domain ?: '';
	}

	/**
	 * Truncate a string, showing only the first $before and $after characters.
	 *
	 * @param string $string    The string to be truncated.
	 * @param int    $before    The number of characters from the beginning of the string to show.
	 * @param int    $after     The number of characters from the end of the string to show.
	 * @param string $separator Optional. The string to indicate truncation. Default is "…".
	 */
	public static function truncate( $string, $before, $after, $separator = '…' ) {
		$length = mb_strlen( $string );

		// We've asked for the entire string.
		if ( $before + $after >= $length ) {
			return $string;
		}

		$beginning = $before > 0 ? mb_substr( $string, 0, $before ) : '';
		$remaining = mb_substr( $string, mb_strlen( $beginning ) );
		$ending    = $after > 0 ? mb_substr( $remaining, -1 * $after ) : '';

		// Only truncate if the resulting string will be shorter than $length.
		return mb_strlen( $beginning ) + mb_strlen( $ending ) + mb_strlen( $separator ) < $length
			? $beginning . $separator . $ending
			: $string;
	}

	/**
	 * Calculate the average integer value from a numeric array.
	 *
	 * @param Array<int|string|null> $array_values
	 *
	 * @return int Average value rounded down to the nearest integer.
	 */
	public static function calculateIntegerAverage( array $array_values ) {
		$average_value = 0;
		$array_values  = array_filter( $array_values, 'is_numeric' );

		if ( $array_values ) {
			$average_value = intval( array_sum( $array_values ) / count( $array_values ) );
		}

		return $average_value;
	}

	/**
	 * Return the string 'no'.
	 *
	 * @return string The string 'no'.
	 */
	public static function returnNo() {
		return 'no';
	}

	/**
	 * Return the string 'yes'.
	 *
	 * @return string The string 'yes'.
	 */
	public static function returnYes() {
		return 'yes';
	}

	/**
	 * Helper to output an array for the menus array for use with the simpleAdminMenu integration.
	 *
	 * If the string starts with 'menu-' or 'toplevel_', then it doesn't get changed.
	 * Otherwise, if it starts with '__', then that will be replaced with 'toplevel_page_',
	 * And finally it will get 'menu-' prepended to it if it's neither of the above.
	 *
	 * @param array $items The items.
	 *
	 * @return array An array of empty arrays, for use with the simpleAdminMenu integration.
	 */
	public static function makeSimpleAdminMenuMenus( $items ) {
		$return = [];

		// Looping through the array rather than array_map, because of the
		// funkiness of the value being an empty array and all that.
		foreach ( $items as $key => $value ) {
			// If we have an array for the item, then we know the key is the
			// actual menu, and the inner array is a customization of the title/icon.
			if ( is_array( $value ) ) {
				$item  = isset( $value[0] ) ? $value[0] : '';
				$title = isset( $value[1] ) ? $value[1] : '';
				$icon  = isset( $value[2] ) ? $value[2] : '';

				if ( empty( $title ) && empty( $icon ) ) {
					$return_value = $value;
				} else {
					$return_value = self::makeSimpleAdminMenuHeader( $title, $icon );
				}
			} else {
				$item         = $value;
				$return_value = [];
			}

			// if the string starts with '__', then replace that with toplevel_page_
			// else if the string starts with 'menu-' or 'toplevel_', then leave it alone.
			// else prepend 'menu-' to it.
			if ( 0 === strpos( $item, 'menu-' ) || 0 === strpos( $item, 'toplevel_' ) ) {
				$return[ $item ] = $return_value;
			} elseif ( 0 === strpos( $item, '__' ) ) {
				$return[ 'toplevel_page_' . substr( $item, 2 ) ] = $return_value;
			} else {
				$return[ 'menu-' . $item ] = $return_value;
			}
		}

		return $return;
	}

	/**
	 * Make an array for the header for use with the simpleAdminMenu integration.
	 *
	 * @param string $title Title for the section.
	 * @param string $icon  Dashicon icon for the section. Optional.
	 *
	 * @return array An array of [ 'title' => $title, 'icon' => $icon ]. Icon is optional.
	 */
	public static function makeSimpleAdminMenuHeader( $title, $icon = '' ) {
		// Always want to use the title param as our title value.
		$return = [
			'title' => $title,
		];

		if ( ! empty( $icon ) ) {
			$return['icon'] = 'dashicons-' . $icon;
		}

		return $return;
	}

	/**
	 * Helper to create a section for the simpleAdminMenu integration.
	 *
	 * @param string|array $title_or_menus The title of the section to pass to makeSimpleAdminMenuHeader,
	 *                                     or the 3rd paramater $menus, to use as a shorter call.
	 * @param string       $icon           Dashicon icon for the section. Optional.
	 * @param array        $menus          Array of menus to pass to makeSimpleAdminMenuMenus.
	 *
	 * @return array An array of [ 'title' => $title, 'icon' => $icon, 'menus' => $menus ].
	 */
	public static function makeSimpleAdminMenuSection( $title_or_menus, $icon = '', array $menus = [] ) {
		$return = [];

		// Allow passing the menus array as the first paramater if we want.
		if ( is_array( $title_or_menus ) ) {
			$menus = $title_or_menus;
		} else {
			$title = (string) $title_or_menus;
		}

		if ( ! empty( $title ) ) {
			$return['header'] = self::makeSimpleAdminMenuHeader( $title, $icon );
		}

		if ( ! empty( $menus ) ) {
			$return['menus'] = self::makeSimpleAdminMenuMenus( $menus );
		}

		return $return;
	}

	/**
	 * Main function to create a simpleAdminMenu section.
	 *
	 * This will take a readable and easily understood array of menus and convert
	 * it into the format we need for the simple admin menu integration. If you
	 * want it to look real nice and readable, then do a // phpcs:disable WordPress.Arrays
	 * beforehand and a // phpcs:enable WordPress.Arrays afterwards.
	 *
	 * This is an example of the array you pass in, that would make the menu show as:
	 * Dashboard
	 * Nexcess
	 * Content
	 *   Posts
	 *   Media
	 *   Pages
	 * Store
	 *    Products
	 *    Reviews
	 *    Settings
	 * Site
	 *    Appearance
	 *    Plugins
	 *    Users
	 * WP101
	 *
	 * // phpcs:disable WordPress.Arrays
	 * makeSimpleAdminMenu( [
	 *     'dashboard',
	 *     '__nexcess-mapps',
	 *     [ __( 'Content', 'nexcess-mapps' ), 'admin-page', [
	 *         'posts',
	 *         'media',
	 *         'pages'
	 *     ] ],
	 *     [ __( 'Store', 'nexcess-mapps' ), 'cart', [
	 *         'posts-product',
	 *         [ '__woo-better-reviews', _x( 'Reviews', 'Dashboard sidebar menu', 'nexcess-mapps' ),  'admin-comments' ],
	 *         [ '__woocommerce',        _x( 'Settings', 'Dashboard sidebar menu', 'nexcess-mapps' ), 'admin-generic' ],
	 *     ] ],
	 *     [ __( 'Site', 'nexcess-mapps' ), 'cover-image', [
	 *         'appearance',
	 *         'plugins',
	 *         'users',
	 *     ] ],
	 *     '__wp101',
	 * ] );
	 * // phpcs:enable WordPress.Arrays
	 *
	 * @param array $menu The menu array in the readable format.
	 *
	 * @return array The formatted array.
	 */
	public static function makeSimpleAdminMenu( $menu ) {
		$return = [];

		foreach ( $menu as $item ) {
			$item = (array) $item;

			// If we have an array as the third param, we know it's a section.
			// Otherwise, we assume it's a menu. To get 3 params, we need to pass
			// title, icon, and menus, otherwise you can just pass menus.
			if ( isset( $item[2] ) ) {
				$return[] = self::makeSimpleAdminMenuSection( ...$item );
			} else {
				$return[] = self::makeSimpleAdminMenuSection( $item );
			}
		}

		return $return;
	}
}
