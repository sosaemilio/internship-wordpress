<?php

namespace Nexcess\MAPPS\Support\AdminMenus;

use WP_User;

class CurrentPage {
	/**
	 * @var string
	 */
	public $pagenow;
	/**
	 * @var string
	 */
	public $plugin_page;

	/**
	 * @param string|null $pagenow     The current page, typically in the $pagenow WP global.
	 * @param string|null $plugin_page The current plugin page slug, typically in the $plugin_page WP global.
	 */
	public function __construct( $pagenow, $plugin_page ) {
		$this->pagenow     = $pagenow ? $pagenow : '';
		$this->plugin_page = $plugin_page ? $plugin_page : '';
	}

	/**
	 * Find the menu and submenu items based on the current page and plugin slug, given a set of menu arrays.
	 *
	 * @param WordPressAdminMenus $menus A set of menu arrays wrapped in a WordPressAdminMenus object.
	 * @param WP_User             $user  The current user, allowing filtering of pages without permissions.
	 *
	 * @return WordPressAdminMenus The current menu and submenu if found, or an empty set of menus if not found.
	 */
	public function findCurrentMenus( WordPressAdminMenus $menus, WP_User $user ) {
		$menu_item    = $menus->getMenuItemByUrl( $this->pagenow );
		$submenu      = [];
		$submenu_slug = $this->plugin_page ? $this->plugin_page : $this->pagenow;

		// If we can go top-down, it will be more efficient.
		if ( $menu_item ) {
			$submenus = $menus->getSubmenuItemByUrl( $this->pagenow );
			$submenus = $submenus ? $submenus : [];
			foreach ( $submenus as $entry ) {
				if ( $entry[2] === $submenu_slug ) {
					if ( $user->has_cap( $entry[1] ) ) { // capability is stored at index 1.
						$submenu = [ $this->pagenow => [ $entry ] ];
					}
					break;
				}
			}
		} else {
			// Can't go top down, need to find it on the bottom and work our way up.
			foreach ( $menus->submenu as $menu_slug => $submenus ) {
				foreach ( $submenus as $entry ) {
					if ( $entry[2] === $submenu_slug ) {
						if ( $user->has_cap( $entry[1] ) ) { // capability is stored at index 1.
							$menu_item = $menus->getMenuItemByUrl( $menu_slug );
							$submenu   = [ $menu_slug => [ $entry ] ];
						}
						break 2;
					}
				}
			}
		}
		// If no top level menu was found, or the user doesn't have permissions return an empty menu.
		if ( ! $menu_item || ! $user->has_cap( $menu_item[1] ) ) { // capability is stored at index 1.
			return new WordPressAdminMenus( [], [] );
		}

		return new WordPressAdminMenus( [ $menu_item ], $submenu );
	}
}
