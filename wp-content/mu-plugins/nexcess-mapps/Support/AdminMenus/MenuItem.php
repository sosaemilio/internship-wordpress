<?php

namespace Nexcess\MAPPS\Support\AdminMenus;

use WP_User;

class MenuItem {
	/**
	 * @var string|null
	 */
	protected $title;

	/**
	 * @var string|null
	 */
	protected $icon;

	/**
	 * @var Array<string>|null
	 */
	protected $hide;

	/**
	 * @param string|null        $title Optional. The title to override the menu's title with.
	 * @param string|null        $icon  Optional. The Dashicons icon to use for the menu instead of the original icon.
	 * @param Array<string>|null $hide  Optional. An array of submenu items which should get hidden from display. Each
	 *                                  item corresponds to the URL of the submenu item.
	 */
	public function __construct( $title = null, $icon = null, array $hide = null ) {
		$this->title = $title;
		$this->icon  = $icon;
		$this->hide  = $hide;
	}

	/**
	 * Will create a customized version of the WordPress menu item and submenu for the passed WP User.
	 *
	 * @param Array<string>                 $menu_item    A single top level menu item as defined in the WordPress global `$menus`.
	 * @param WP_User                       $user         The current WordPress user object, used for checking capabilities.
	 * @param Array<int,Array<string>>|null $submenu_item A single submenu definition as defined in the WordPress global
	 *                                                    `$submenus`. Null if this item should have no submenu.
	 *
	 * @return WordPressAdminMenus A global menu instance which contains the menu and submenu for this menu item.
	 */
	public function process( array $menu_item, WP_User $user, array $submenu_item = null ) {
		// First check for an add the menu item if defined, and the user has the needed capability. Customize as needed.
		$global_menus = new WordPressAdminMenus( [], [] );
		if ( ! $user->has_cap( $menu_item[1] ) ) { // capability is stored at index 1.
			return $global_menus;
		}

		if ( $this->title ) {
			$menu_item[0] = $this->title;
		}

		if ( $this->icon ) {
			$menu_item[6] = $this->icon; // icon ist stored at index 6.
		}

		$global_menus->menu[] = $menu_item;

		// Second add the submenu if defined, and the user has the needed capability. Hide if defined in the hide array.
		if ( is_null( $submenu_item ) ) {
			return $global_menus;
		}

		$displayed_submenus = array_reduce(
			$submenu_item,
			function( $displayed_submenus, $submenu ) use ( $user ) {
				if ( ! $user->has_cap( $submenu[1] ) ) { // capability is stored at index 1 for submenus as well.
					return $displayed_submenus;
				}
				if ( ! is_null( $this->hide ) && in_array( $submenu[2], $this->hide, true ) ) { // 2 is the url.
					return $displayed_submenus;
				}
				$displayed_submenus[] = $submenu;
				return $displayed_submenus;
			},
			[]
		);

		// Only add submenus if the list is not empty (all items hidden or not available for the user).
		if ( ! empty( $displayed_submenus ) ) {
			$global_menus->submenu[ $menu_item[2] ] = $displayed_submenus; // 2 is the url, which is the submenu key.
		}

		return $global_menus;
	}
}
