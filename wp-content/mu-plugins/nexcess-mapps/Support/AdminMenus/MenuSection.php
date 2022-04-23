<?php

namespace Nexcess\MAPPS\Support\AdminMenus;

use WP_User;

class MenuSection {
	/**
	 * @var Array<string, MenuItem>
	 */
	protected $menus;

	/**
	 * @var MenuHeader
	 */
	protected $header;

	/**
	 * @param Array<MenuItem> $menus  The menu items to render in this menu section.
	 * @param MenuHeader      $header Optional. The header to render if this section should be collapsible.
	 */
	public function __construct( array $menus, MenuHeader $header = null ) {
		$this->menus  = $menus;
		$this->header = is_null( $header ) ? new MenuHeader() : $header;
	}

	/**
	 * Given a set of WordPress admin menus and user, will create a customized menu section in the global menu format.
	 *
	 * @param WordPressAdminMenus $menus The original menus which this menu section is customizing.
	 * @param WP_User             $user  The current WordPress user for performing menu-item capability checks.
	 *
	 * @return WordPressAdminMenus A WordPressAdminMenus wrapper with all the menus and submenus for this menu section.
	 */
	public function process( WordPressAdminMenus $menus, WP_User $user ) {
		$new_menus = new WordPressAdminMenus( [], [] );
		foreach ( $this->menus as $id => $item ) {
			// Since the original array structure for menus used the id or url as the array key, we need to search the
			// original global menu array here, since the actual item definition doesn't know the menu key it is
			// supposed to belong to. Ce la vie.
			$menu_item = $menus->getMenuItemByID( $id );

			if ( is_null( $menu_item ) ) {
				$menu_item = $menus->getMenuItemByUrl( $id );
			}

			// If we did not find a menu by ID or URL, filter this menu item out.
			if ( is_null( $menu_item ) ) {
				continue;
			}

			// Customize the menu item and add it to the section's menus.
			$new_menus = $new_menus->combine(
				$item->process( $menu_item, $user, $menus->getSubmenuItemByUrl( $menu_item[2] ) )
			);
		}

		return $this->header->process( $new_menus );
	}
}
