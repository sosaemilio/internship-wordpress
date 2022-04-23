<?php
/**
 * The menu customizer class provides a means of organizing menu items based on the WordPress admin menus and
 * a definition array containing the customizations and structure for the new admin menus.
 */

namespace Nexcess\MAPPS\Support\AdminMenus;

use WP_User;

class MenuCustomizer {
	/**
	 * Processes the menu definition and WordPress admin menus to create new global menu arrays.
	 *
	 * The menu definition is an array of arrays. Each inner array has a menus key with an array of customization
	 * options. Optionally it can include a header definition to create a full collapsable section.
	 *
	 * ```
	 * $definition = [
	 *     [
	 *         'menus' => [
	 *              'menu-dashboard' => [
	 *                  'title' => 'Override',
	 *                  'icon' => 'dashicons-icon-class',
	 *                  'hide' => [
	 *                      'update-plugins',
	 *                  ]
	 *              ],
	 *         ],
	 *     ],
	 *     [
	 *         'header' => [
	 *             'title' => 'Section Title',
	 *             'icon' => 'dashicons-icon-class',
	 *         ],
	 *         'menus' => [
	 *              'menu-posts' => [],
	 *              'menu-media' => [],
	 *              'menu-pages' => [],
	 *         ],
	 *     ],
	 * ];
	 *
	 * If included, the header title is required. The icon will fall back to generic gear. If excluded the menus in the
	 * section are rendered without the additional hierarchy.
	 *
	 * The menus can each be customized with a new title, a different icon from the dashicons library, and any submenus
	 * can be specified to hide them from display. Menu keys are identified by the `id_default` falling back to the
	 * `url_default`, which ensures things like built-in separators are included in the map. All submenus are identified
	 * by the `url_default` value, which is the only identifying key available for submenu items.
	 *
	 * Any menu that is not defined in the definition will get hidden from display. This means any future plugin defined
	 * menus _will_ get hidden. At that point the feature can be disabled to gain access to the full menu set.
	 *
	 * Any menu that is defined in the definition but not available in the admin menus is ignored.
	 *
	 * @param array                                  $definition  The defined menu order and customizations as an array of array .
	 * @param Array<int,Array<string>>               $menu        The WordPress top level `$menu` global structure.
	 * @param Array<string,Array<int,Array<string>>> $submenu     The WordPress `$submenu` global structure.
	 * @param WP_User                                $user        The current user, which allows filtering out menus which the user does not have the
	 *                                                            capabilities to see.
	 * @param string                                 $pagenow     The current WordPress page, if set.
	 * @param string                                 $plugin_page The current WordPress plugin_page, if eet.
	 *
	 * @return WordPressAdminMenus A new set of WordPress admin menus matching the definition, or the original if an
	 *                             error occurred. Errors will get logged in the event an error prevents menu
	 *                             customization.
	 */
	public function customizeMenu( array $definition, array $menu, array $submenu, WP_User $user, $pagenow, $plugin_page ) {
		$global_menus = new WordPressAdminMenus( $menu, $submenu );
		return $this->decodeMenu( $definition )->process( $global_menus, $user, new CurrentPage( $pagenow, $plugin_page ) );
	}

	/**
	 * Decodes an array of generic objects into a true Menu object, ready for processing.
	 *
	 * @param Array<array> $definition An array of generic objects in the menu section structure.
	 *
	 * @return Menu A constructed menu object, ready to customize WordPress admin menus.
	 */
	public function decodeMenu( array $definition ) {
		return new Menu( array_map( [ $this, 'decodeMenuSection' ], $definition ) );
	}

	/**
	 * Decode an array in the format of a MenuHeader.
	 *
	 * Empty arrays are valid values as neither title, nor icon ar required attributes.
	 *
	 * @param Array<string,string> $menu_header The array with keys matching the MenuHeader format.
	 *
	 * @return MenuHeader The constructed MenuHeader object with the provided title and icon if set.
	 */
	public function decodeMenuHeader( array $menu_header ) {
		$title = isset( $menu_header['title'] ) && $menu_header['title'] ? $menu_header['title'] : '';
		$icon  = isset( $menu_header['icon'] ) && $menu_header['icon'] ? $menu_header['icon'] : null;

		return new MenuHeader( $title, $icon );
	}

	/**
	 * Decodes menu item serialized as an array into a constructed MenuItem object.
	 *
	 * All items are optional. An empty array can be passed to create a menu item representing a non-customized menu.
	 *
	 * @param Array<string,mixed> $menu_item A serialized menu item definition.
	 */
	public static function decodeMenuItem( array $menu_item ) {
		$title = isset( $menu_item['title'] ) && $menu_item['title'] ? $menu_item['title'] : null;
		$icon  = isset( $menu_item['icon'] ) && $menu_item['icon'] ? $menu_item['icon'] : null;
		$hide  = isset( $menu_item['hide'] ) && is_array( $menu_item['hide'] ) ? $menu_item['hide'] : null;

		return new MenuItem( $title, $icon, $hide );
	}

	/**
	 * Decodes a generic array into a MenuSection object ready for processing.
	 *
	 * @param Array<array> $menu_section A generic array in the menu section structure.
	 *
	 * @return MenuSection A constructed MenuSection object based on the generic array.
	 */
	public function decodeMenuSection( array $menu_section ) {
		// Instead of throwing on invalid data, just default to an empty array, which will yield an empty section.
		// This will get filtered out during processing without breaking the site.
		if ( ! isset( $menu_section['menus'] ) ) {
			$menu_section['menus'] = [];
		}
		$menus  = array_map( [ $this, 'decodeMenuItem' ], $menu_section['menus'] );
		$header = isset( $menu_section['header'] ) ? $this->decodeMenuHeader( $menu_section['header'] ) : null;

		return new MenuSection( $menus, $header );
	}
}
