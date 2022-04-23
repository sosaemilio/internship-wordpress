<?php

namespace Nexcess\MAPPS\Support\AdminMenus;

class WordPressAdminMenus {
	/**
	 * @var Array<int,Array<string>> The WordPress top level $menu global structure.
	 */
	public $menu;

	/**
	 * @var Array<string,Array<int,Array<string>>> The WordPress $submenu global structure.
	 */
	public $submenu;

	/**
	 * @var Array<string,int>|null A lookup dictionary for finding top level menu items by ID.
	 */
	protected $menuIdLookup;

	/**
	 * @var Array<string,int>|null A lookup dictionary for finding top level menu items by url.
	 */
	protected $menuUrlLookup;

	/**
	 * @param Array<int,Array<string>>               $menu    The WordPress top level $menu global structure.
	 * @param Array<string,Array<int,Array<string>>> $submenu The WordPress $submenu global structure.
	 */
	public function __construct( array $menu = [], array $submenu = [] ) {
		$this->menu    = $menu;
		$this->submenu = $submenu;
	}

	/**
	 * Create a new WordPressAdminMenus instance with the menus defined in this one combined with the menus in another.
	 *
	 * @param WordPressAdminMenus $to_combine The WordPressAdminMenus instance to combine with this one.
	 *
	 * @return WordPressAdminMenus The new combined WordPressAdminMenus instance.
	 */
	public function combine( WordPressAdminMenus $to_combine ) {
		return new WordPressAdminMenus(
			array_merge( $this->menu, $to_combine->menu ),
			array_merge( $this->submenu, $to_combine->submenu )
		);
	}

	/**
	 * Look up a top level menu item by html ID.
	 *
	 * @param string $id The html ID of the menu item to look up.
	 *
	 * @return Array<string>|null Either the menu item from `$menu`, or null if it is not found.
	 */
	public function getMenuItemByID( $id ) {
		// Lazily populate the lookup table the first time a lookup is requested.
		// Cache this in the class property so only one traversal through menus is needed.
		if ( is_null( $this->menuIdLookup ) ) {
			foreach ( $this->menu as $index => $item ) {
				// Index 5 is the id for this menu item, but this is not defined on separators
				// meaning they do not show up in this lookup table.
				if ( isset( $item[5] ) ) {
					$this->menuIdLookup[ $item[5] ] = $index;
				}
			}
		}

		return isset( $this->menuIdLookup[ $id ] ) ? $this->menu[ $this->menuIdLookup[ $id ] ] : null;
	}

	/**
	 * Look up a top level menu item by HTML ID.
	 *
	 * @param string $url The URL defined for the menu item to look up.
	 *
	 * @return Array<string>|null Either the menu item from `$menu`, or null if it is not found.
	 */
	public function getMenuItemByUrl( $url ) {
		// Lazily populate the lookup table the first time a lookup is requested.
		// Cache this in the class property so only one traversal through menus is needed.
		if ( is_null( $this->menuUrlLookup ) ) {
			foreach ( $this->menu as $index => $item ) {
				// Index 2 is the URL defined for this menu item.
				if ( isset( $item[2] ) ) {
					$this->menuUrlLookup[ $item[2] ] = $index;
				}
			}
		}

		return isset( $this->menuUrlLookup[ $url ] ) ? $this->menu[ $this->menuUrlLookup[ $url ] ] : null;
	}

	/**
	 * Look up a submenu by top-level menu URL.
	 *
	 * @param string $url The URL defined for the top level menu item to look up the sub menu for.
	 *
	 * @return Array<int,Array<string>>|null Either the submenu item from `$submenu`, or null if it is not found.
	 */
	public function getSubmenuItemByUrl( $url ) {
		return isset( $this->submenu[ $url ] ) ? $this->submenu[ $url ] : null;
	}

	/**
	 * Check if this WordPressAdminMenus instance has any menus currently associated with it.
	 *
	 * @return bool Whether or not the sub menu has any menus in the menu array.
	 */
	public function hasMenus() {
		return count( $this->menu ) > 0;
	}
}
