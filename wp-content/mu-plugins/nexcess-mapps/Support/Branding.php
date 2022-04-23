<?php
/**
 * Allow for agencies to override the Nexcess branding.
 */

namespace Nexcess\MAPPS\Support;

use Nexcess\MAPPS\Integrations\Support;

use const Nexcess\MAPPS\PLUGIN_URL;

class Branding {

	/**
	 * Retrieve the company name used throughout the platform's branding.
	 *
	 * @return string The branded company name.
	 */
	public static function getCompanyName() {
		$name = _x( 'Nexcess', 'company name', 'nexcess-mapps' );

		/**
		 * Override the company name used throughout the Nexcess MAPPS platform.
		 *
		 * @param string $name The branded company name.
		 */
		$filtered = apply_filters( 'nexcess_mapps_branding_company_name', $name );

		return self::isNonEmptyString( $filtered ) ? trim( $filtered ) : $name;
	}

	/**
	 * Retrieve the company platform name used throughout the platform's branding.
	 *
	 * @return string The branded company platform name.
	 */
	public static function getPlatformName() {
		$name = _x( 'Nexcess Managed Applications Platform', 'company platform name', 'nexcess-mapps' );

		/**
		 * Override the company platform name used throughout the Nexcess MAPPS platform.
		 *
		 * @param string $name The branded company platform name.
		 */
		$filtered = apply_filters( 'nexcess_mapps_branding_company_platform_name', $name );

		return self::isNonEmptyString( $filtered ) ? trim( $filtered ) : $name;
	}

	/**
	 * Retrieve the company name as it should appear in the WP Admin Bar.
	 *
	 * @return string The title for the WP Admin Bar.
	 */
	public static function getAdminBarTitle() {
		$name = self::getCompanyName();

		/**
		 * Override the company name used in the WP Admin Bar.
		 *
		 * @param string $name The branded company name.
		 */
		$filtered = apply_filters( 'nexcess_mapps_branding_admin_bar_name', $name );

		return self::isNonEmptyString( $filtered ) ? trim( $filtered ) : $name;
	}

	/**
	 * Retrieve the label for the top-level admin menu item.
	 *
	 * @return string The label of the admin menu.
	 */
	public static function getDashboardMenuItemLabel() {
		$name = self::getCompanyName();

		/**
		 * Override the label for the top-level admin menu item.
		 *
		 * @param string $name The branded company name.
		 */
		$filtered = apply_filters( 'nexcess_mapps_branding_dashboard_menu_item_title', $name );

		return self::isNonEmptyString( $filtered ) ? trim( $filtered ) : $name;
	}

	/**
	 * Retrieve the title of the dashboard page.
	 *
	 * @return string The title for the dashboard page.
	 */
	public static function getDashboardPageTitle() {
		$name = self::getCompanyName();

		/**
		 * Override the title of the dashboard page.
		 *
		 * @param string $name The branded company name.
		 */
		$filtered = apply_filters( 'nexcess_mapps_branding_dashboard_page_title', $name );

		return self::isNonEmptyString( $filtered ) ? trim( $filtered ) : $name;
	}

	/**
	 * Retrieve the <svg> markup for the a single-color logo.
	 *
	 * @param string $color Optional. The default fill color for the SVG icon.
	 *                      Default is "currentColor".
	 *
	 * @return string An inline SVG icon.
	 */
	public static function getCompanyIcon( $color = 'currentColor' ) {
		$icon = self::getNexcessIcon( $color );

		/**
		 * Override the icon branding for the Nexcess MAPPS dashboard.
		 *
		 * @param string $icon  An inline SVG of the branded icon.
		 * @param string $color The SVG color. By default, this will be "currentColor".
		 */
		$filtered = apply_filters( 'nexcess_mapps_branding_company_icon_svg', $icon, $color );

		return self::isNonEmptyString( $filtered ) ? $filtered : $icon;
	}

	/**
	 * Retrieve the company logo used throughout the platform.
	 *
	 * @return string The URL of the logo.
	 */
	public static function getCompanyImage() {
		$logo = PLUGIN_URL . '/nexcess-mapps/assets/img/nexcess-logo.svg?v2021';

		/**
		 * Override the company logo image file for the Nexcess MAPPS dashboard.
		 *
		 * @param string $logo A URL for the branded company logo.
		 */
		$filtered = apply_filters( 'nexcess_mapps_branding_company_image', $logo );

		return self::isNonEmptyString( $filtered ) ? $filtered : $logo;
	}

	/**
	 * Retrieve the URL for support requests.
	 *
	 * @return string The support URL.
	 */
	public static function getSupportUrl() {
		$url = Support::SUPPORT_URL;

		/**
		 * Filter the URL for support.
		 *
		 * @param string $url The support URL.
		 */
		$filtered = apply_filters( 'nexcess_mapps_branding_support_url', $url );

		return self::isNonEmptyString( $filtered ) ? trim( $filtered ) : $url;
	}

	/**
	 * Retrieve the URL for DNS help.
	 *
	 * @return string The DNS help URL.
	 */
	public static function getDNSHelpUrl() {
		$url = 'https://help.nexcess.net/74095-wordpress/how-to-edit-or-add-an-a-host-dns-record-to-go-live-with-your-site';

		/**
		 * Filter the URL for DNS help.
		 *
		 * @param string $url The DNS help URL.
		 */
		$filtered = apply_filters( 'nexcess_mapps_branding_dns_help_url', $url );

		return self::isNonEmptyString( $filtered ) ? trim( $filtered ) : $url;
	}

	/**
	 * Retrieve the <svg> markup for the single-color Nexcess logo.
	 *
	 * @param string $color Optional. The default fill color for the SVG icon.
	 *                      Default is "currentColor".
	 *
	 * @return string An inline SVG version of the single-color Nexcess "N" icon.
	 */
	public static function getNexcessIcon( $color = 'currentColor' ) {
		return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 579.55 467.17"><g fill="' . $color . '"><path d="M108.1 18.2c-52.73 0-95.46 41.55-95.46 92.8v166.5c0-108.45 94.85-118.94 142-75.1L281.1 323.84 281 147.9 175.6 45.38a96.89 96.89 0 0 0-67.5-27.18z"/><path d="m190.03 236.38 91.07 87.46-.13-175.85zm80.65 97.52-90.65 87.87.14-175.73zM147.69 214.85c-25.85-25.48-53.51-22.78-53.51-22.78s-70.3-1.29-81.51 85.24v130.84c0 24.7 20.6 44.72 46 44.72h78.46a10.56 10.56 0 0 0 10.56-10.56zm321.05 238.02c52.73 0 95.46-41.55 95.46-92.81v-166.5c0 108.45-94.85 118.94-142 75.1L295.74 147.23l.06 176 105.44 102.46a96.89 96.89 0 0 0 67.5 27.18z"/><path d="m386.81 234.7-91.07-87.47.13 175.86zm-80.65-97.52 90.65-87.88-.14 175.73zm122.99 119.04C455 281.7 482.66 279 482.66 279s70.3 1.3 81.51-85.24V62.92c0-24.7-20.6-44.72-46-44.72h-78.46a10.56 10.56 0 0 0-10.56 10.56zm113.99 186.92h-3.35v-2h9.12v2h-3.35v8.48h-2.42zm17.78 8.48v-6.27l-3.08 5.17h-1.09l-3.06-5v6.14h-2.29v-10.49h2l3.92 6.49 3.85-6.49h2v10.45z"/></g></svg>';
	}

	/**
	 * Quickly validate that the given value is a non-empty string.
	 *
	 * @param mixed $value The value to check.
	 *
	 * @return bool True if $value is a non-empty string, false otherwise.
	 */
	protected static function isNonEmptyString( $value ) {
		return is_string( $value ) && ! empty( $value );
	}
}
