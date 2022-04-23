<?php

namespace Nexcess\MAPPS\Integrations\PerformanceMonitor;

/**
 * Helper class that provides access to methods for attributing
 * file URL to a particular theme or plugin.
 */
class FileSource {

	/**
	 * Returns an array describing the source of the file.
	 *
	 * @param string $url URL of a file to identify the source of.
	 *
	 * @return Array<string,string>
	 */
	public function getSource( $url ) {
		$uploads_info    = wp_upload_dir();
		$url_no_protocol = $this->strip_protocol_from_url( $url );

		$source_types = [
			'Theme'  => $this->strip_protocol_from_url( get_theme_root_uri() ),
			'Plugin' => $this->strip_protocol_from_url( plugin_dir_url( '' ) ),
			'Upload' => $this->strip_protocol_from_url( $uploads_info['baseurl'] ),
		];

		foreach ( $source_types as $source_type => $source_base_url ) {
			if ( 0 === strpos( $url_no_protocol, $source_base_url ) ) {
				$source_callable = [ $this, sprintf( 'getSource%s', $source_type ) ];

				if ( is_callable( $source_callable ) ) {
					return call_user_func( $source_callable, $url_no_protocol );
				}
			}
		}

		/**
		 * Third-party assets.
		 */
		$current_site_url = get_site_url();
		$site_domain      = wp_parse_url( $current_site_url, PHP_URL_HOST );
		$file_domain      = wp_parse_url( $url, PHP_URL_HOST );

		if ( $site_domain !== $file_domain ) {
			return [
				'type' => 'third-party',
				'name' => $file_domain,
			];
		}

		return [];
	}

	/**
	 * Return source information for uploaded files.
	 *
	 * @param string $url_no_protocol URL without protocol, e.g. `//example.com/file.txt`.
	 *
	 * @return Array<string,(string|int)>
	 */
	public function getSourceUpload( $url_no_protocol ) {
		$uploads_info            = wp_upload_dir();
		$uploads_url_no_protocol = $this->strip_protocol_from_url( $uploads_info['baseurl'] );
		$file_path               = str_replace( $uploads_url_no_protocol, $uploads_info['basedir'], $url_no_protocol );

		if ( is_file( $file_path ) ) {
			$file_modified_timestamp = intval( filemtime( $file_path ) );

			if ( $file_modified_timestamp ) {
				$file_modified_date = new \DateTime( '@' . $file_modified_timestamp );

				/**
				 * Need to set timezone explicitly. Setting it as a constructor
				 * parameter of `DateTime` is not going to work, because its ignored
				 * when a timestamp is used to initialize the object.
				 *
				 * @link https://www.php.net/manual/en/datetime.construct.php
				 */
				$file_modified_date->setTimezone( wp_timezone() );

				return [
					'type' => 'upload',
					'date' => $file_modified_date->format( 'Y-m-d H:i:s' ),
				];
			}
		}
		return [];
	}

	/**
	 * Return source information for plugin files.
	 *
	 * @param string $url_no_protocol URL without protocol, e.g. `//example.com/file.txt`.
	 *
	 * @return Array<string,string>
	 */
	public function getSourcePlugin( $url_no_protocol ) {
		$plugins = get_plugins();

		foreach ( $plugins as $plugin_path => $plugin_data ) {
			$base_plugin_url        = plugin_dir_url( '' );
			$plugin_url             = plugin_dir_url( $plugin_path );
			$plugin_url_no_protocol = $this->strip_protocol_from_url( $plugin_url );

			/**
			 * Ignore single file plugins directly in `/plugins/*`.
			 */
			if ( $base_plugin_url === $plugin_url ) {
				continue;
			}

			if ( 0 === strpos( $url_no_protocol, $plugin_url_no_protocol ) ) {
				return [
					'type' => 'plugin',
					'name' => (string) $plugin_data['Name'],
				];
			}
		}
		return [];
	}

	/**
	 * Return source information for theme files.
	 *
	 * @param string $url_no_protocol URL without protocol, e.g. `//example.com/file.txt`.
	 *
	 * @return Array<string,string>
	 */
	public function getSourceTheme( $url_no_protocol ) {
		$current_theme = wp_get_theme();
		$parent_theme  = $current_theme->parent();

		$themes = [ $current_theme ];
		if ( $parent_theme ) {
			$themes[] = $parent_theme;
		}

		foreach ( $themes as $theme ) {
			$theme_uri             = $theme->get_template_directory_uri();
			$theme_uri_no_protocol = $this->strip_protocol_from_url( $theme_uri );

			if ( 0 === strpos( $url_no_protocol, $theme_uri_no_protocol ) ) {
				$theme_name = $theme->get( 'Name' );

				if ( is_string( $theme_name ) ) {
					return [
						'type' => 'theme',
						'name' => $theme_name,
					];
				}
			}
		}

		return [];
	}

	/**
	 * Strips protocol from URL string.
	 *
	 * @param string $url URL.
	 *
	 * @return string
	 */
	protected function strip_protocol_from_url( $url ) {
		return (string) preg_replace( '~^[^/]*//~', '//', $url );
	}
}
