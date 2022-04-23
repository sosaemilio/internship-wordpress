<?php

namespace Nexcess\MAPPS\Support\AdminMenus;

class MenuHeader {
	/**
	 * @var string The title to use as the text in the menu item used as the collapsible section header.
	 */
	protected $title;

	/**
	 * @var string The dashicons icon class to render for the menu header item.
	 */
	protected $icon;

	/**
	 * @param string      $title Optional. The title to use as the text in the menu item used as the collapsible section
	 *                           header. If left null, this header will only denote the section separation.
	 * @param string|null $icon  Optional. The dashicons class to use as the icon for this menu header.
	 *                           default: null which becomes dashicons-admin-generic.
	 */
	public function __construct( $title = '', $icon = null ) {
		$this->title = $title;
		$this->icon  = is_null( $icon ) ? 'dashicons-admin-generic' : $icon;
	}

	/**
	 * Add a new menu separator to denote this section and a menu header menu item to the beginning of the menus array.
	 *
	 * @param WordPressAdminMenus $menus The WordPressAdminMenus instance to add the header to.
	 *
	 * @return WordPressAdminMenus A WordPressAdminMenus instance with the heeder items followed by normal menu items.
	 */
	public function process( WordPressAdminMenus $menus ) {
		// If there are no menus under this header, don't add the header.
		if ( ! $menus->hasMenus() ) {
			return $menus;
		}

		// Deterministic ID.
		$seed       = wp_json_encode( $menus );
		$section_id = md5( $seed ? $seed : '' );

		// If this is just a section ('no title is defined'), add a separator only
		// Otherwise add a separator and the menu header menu item.
		if ( '' === $this->title ) {
			$header_menus = new WordPressAdminMenus([
				[
					'',
					'read',
					'separator-' . $section_id,
					'',
					'wp-menu-separator',
				],
			], []);
		} else {
			$header_menus = new WordPressAdminMenus([
				[
					'',
					'read',
					'separator-' . $section_id,
					'',
					'nx-hierarchical-section wp-menu-separator',
				],
				[
					esc_html( $this->title ),
					'read',
					'#!',
					'',
					'menu-top menu-icon-' . $section_id,
					'menu-' . $section_id,
					sanitize_html_class( $this->icon ), // If we end up supporting SVG, this will likely change.
				],
			], []);
		}

		// Combine the header menus with the rest of the menus in this section.
		return $header_menus->combine( $menus );
	}
}
