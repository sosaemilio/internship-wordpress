<?php

namespace Nexcess\MAPPS\Support\AdminMenus;

use WP_User;

class Menu {
	/**
	 * @var MenuSection[] The various sections in the order that make up the desired administration menu.
	 */
	protected $menuSections;

	/**
	 * @param MenuSection[] $menu_sections The various sections in the order that make up the desired administration menu.
	 */
	public function __construct( array $menu_sections ) {
		$this->menuSections = $menu_sections;
	}

	/**
	 * Given a set of WordPress admin menus, will generate a new set of WordPress admin menus based on the menu sections.
	 *
	 * @param WordPressAdminMenus $menus        The WordPress admin menus to use as the source when processing the
	 *                                          defined menu sections.
	 * @param WP_User             $user         The user to check for access to the menu.
	 * @param CurrentPage         $current_page The current menu and submenu page the user is on.
	 *
	 * @return WordPressAdminMenus A new WordPressAdminMenus object matching the defined sections, or the original if an
	 *                             error occurred.
	 */
	public function process( WordPressAdminMenus $menus, WP_User $user, CurrentPage $current_page ) {
		$processed = array_reduce(
			$this->menuSections,
			function( WordPressAdminMenus $processed_menu, MenuSection $menu_section ) use ( $menus, $user ) {
				return $processed_menu->combine( $menu_section->process( $menus, $user ) );
			},
			new WordPressAdminMenus( [], [] )
		);

		// Mix in the current page if the menus are not fully empty.
		if ( $processed->hasMenus() && $current_page->pagenow ) {
			$processed = $this->ensureMenuHasCurrentPage(
				$processed,
				$current_page->findCurrentMenus( $menus, $user )
			);
		}

		// Remove the first item if it is not a hierarchical separator (so menus do not start with a separator when
		// it is not needed to make a collapsible section).
		// offset [0][4] is the first menu item (0) and the associated html classes (4) which we can check for
		// the hierarchical section class. If it _is_ a section, we should keep the separator.
		if ( count( $processed->menu ) > 0 && false === strpos( $processed->menu[0][4], 'nx-hierarchical-section' ) ) {
			array_shift( $processed->menu );
		}

		return $processed;
	}

	/**
	 * If the current page top level or submenu items are missing, add them to the menu.
	 *
	 * @param WordPressAdminMenus $processed    The customized set of admin menus.
	 * @param WordPressAdminMenus $current_page A set of menus containing the current page menu if set.
	 *
	 * @return WordPressAdminMenus The processed menus, with the current page added if it was missing.
	 */
	protected function ensureMenuHasCurrentPage( WordPressAdminMenus $processed, WordPressAdminMenus $current_page ) {
		// Nothing to add if the current page menus is empty.
		if ( ! $current_page->hasMenus() ) {
			return $processed;
		}

		$current_top_url = $current_page->menu[0][2];
		if ( $processed->getMenuItemByUrl( $current_top_url ) ) {
			$current_submenu = $current_page->getSubmenuItemByUrl( $current_top_url );
			if ( empty( $current_submenu ) ) {
				// Current page doesn't have a submenu and the top level item already exists.
				return $processed;
			}
			$processed_submenus = $processed->getSubmenuItemByUrl( $current_top_url );
			if ( $processed_submenus ) {
				// Make sure submenu is present.
				foreach ( $processed_submenus as $entry ) {
					if ( $entry[2] === $current_submenu[0][2] ) {
						// Current page top level and sub menu already exists.
						return $processed;
					}
				}
				// Current page submenu is missing from the submenus, add it to the bottom.
				$processed->submenu[ $current_top_url ] = array_merge( $processed_submenus, $current_submenu );
			} else {
				// No submenus exist for the current top level menu, add the current submenu page as a submenu.
				$processed->submenu[ $current_top_url ] = $current_page->submenu[ $current_top_url ];
			}
			return $processed;
		}

		return $processed->combine( ( new MenuHeader() )->process( $current_page ) );
	}
}
