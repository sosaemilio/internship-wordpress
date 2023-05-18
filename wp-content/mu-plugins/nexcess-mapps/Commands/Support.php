<?php

namespace Nexcess\MAPPS\Commands;

use Nexcess\MAPPS\Integrations\PageCache;
use Nexcess\MAPPS\Modules\SupportUsers;
use WP_CLI;
use const Nexcess\MAPPS\PLUGIN_VERSION;

/**
 * WP-CLI methods for Nexcess support.
 */
class Support extends Command {

	/**
	 * @var \Nexcess\MAPPS\Integrations\PageCache
	 */
	protected $pageCache;

	/**
	 * Create a new instance of the Support command class.
	 *
	 * @param \Nexcess\MAPPS\Integrations\PageCache $page_cache
	 */
	public function __construct( PageCache $page_cache ) {
		$this->pageCache = $page_cache;
	}

	/**
	 * Get Nexcess MAPPS MU plugin version.
	 *
	 * @return string
	 */
	protected function getNexcessMappsVersion() {
		$plugins = get_mu_plugins();

		if ( isset( $plugins['nexcess-mapps-bootstrap.php'] ) ) {
			return PLUGIN_VERSION . '+dev';
		}

		return PLUGIN_VERSION;
	}

	/**
	 * Prints information about this WordPress site.
	 * Each section of information can be extended with extra information.
	 * The filter 'Nexcess\MAPPS\Support\Details\section_<section_name>' can be used to add extra information.
	 *
	 * An example of the filter to add 'Hello: World' to the Site Information section.
	 *
	 * add_action( 'Nexcess\MAPPS\Support\Details\section_site_info', function( $data ) {
	 *      return array_merge( $data, [ 'hello' => 'world' ] );
	 * } );
	 *
	 * ## OPTIONS
	 *
	 * [--format=<json>]
	 * : Output format
	 *
	 * @since 1.0.0
	 *
	 * @global $wp_version
	 * @global $wpdb
	 *
	 * @param mixed[] $args       Positional arguments.
	 * @param mixed[] $assoc_args Associative arguments.
	 */
	public function details( $args, $assoc_args ) {
		global $wp_version;
		global $wpdb;

		$sections = [];

		$sections[] = [
			'Nexcess Must-Use Plugin Details',
			[
				/* Translators: %1$s is the must-use plugin version. */
				__( 'Must-Use Plugin Version', 'nexcess-mapps' ) => $this->getNexcessMappsVersion(),
			],
			'nxmapps_plugin',
		];

		$sections[] = [
			'Nexcess Constants',
			self::formatConstants( [
				'NEXCESS_MAPPS_SITE',
				'NEXCESS_MAPPS_MWCH_SITE',
				'NEXCESS_MAPPS_REGRESSION_SITE',
				'NEXCESS_MAPPS_STAGING_SITE',
				'NEXCESS_MAPPS_PLAN_NAME',
				'NEXCESS_MAPPS_PACKAGE_LABEL',
				'NEXCESS_MAPPS_ENDPOINT',
				'NEXCESS_MAPPS_TOKEN',
			] ),
			'constants',
		];

		$sections[] = [
			'Environment Settings',
			[
				'Debug Mode'              => defined( 'WP_DEBUG' ) && WP_DEBUG,
				'Server Name'             => gethostname(),
				'IP'                      => gethostbyname( php_uname( 'n' ) ),
				'OS Version'              => self::get_os_version(),
				''                        => '',
				'Environment type'        => wp_get_environment_type(),
				'WP Version'              => $wp_version,
				'PHP Version (WP)'        => phpversion(),
				'PHP Memory Limit'        => ini_get( 'memory_limit' ),
				'PHP Upload Max Filesize' => ini_get( 'upload_max_filesize' ),
				'MySQL Version'           => $wpdb->get_var( 'SELECT VERSION()' ),
			],
			'environment',
		];

		$sections[] = [
			'WordPress Configuration',
			[
				'WP Memory Limit (WP_MEMORY_LIMIT)'     => WP_MEMORY_LIMIT,
				'Absolute Path (ABSPATH)'               => ABSPATH,
				'WP Content Directory (WP_CONTENT_DIR)' => WP_CONTENT_DIR,
				'WP Uploads Directory'                  => wp_get_upload_dir()['basedir'],
				'WPLANG'                                => defined( 'WPLANG' ) && WPLANG ? WPLANG : 'en_US',
				'WordPress Multisite'                   => is_multisite(),
			],
			'wp_info',
		];

		$sections[] = [
			'Site Information',
			[
				'Home URL'            => get_home_url(),
				'Site URL'            => site_url(),
				'Admin Email'         => get_option( 'admin_email' ),
				'Permalink Structure' => get_option( 'permalink_structure' ) ? str_replace( '%', '%%', get_option( 'permalink_structure' ) ) : 'Default',
			],
			'site_info',
		];

		$cache_config = wp_parse_args( $this->getCacheConfiguration( $this->pageCache->getActivePageCachePlugins() ), [
			'enabled'  => 'Disabled',
			'provider' => '',
			'htaccess' => '',
		] );

		$sections[] = [
			'Cache Configuration',
			[
				'Page Cache'                 => $cache_config['enabled'],
				'Page Cache Provider'        => $cache_config['provider'],
				'Page Cache .htaccess Rules' => $cache_config['htaccess'],
				'Object Cache Provider'      => $this->getObjectCacheProvider(),
			],
			'page_cache',
		];

		if ( isset( $assoc_args['format'] ) && 'json' === $assoc_args['format'] ) {
			$data = [];
			foreach ( $sections as $section ) {
				$data[ $section[2] ] = $section[1];
			}
			$json_data = wp_json_encode( $data );
			if ( $json_data ) {
				WP_CLI::line( $json_data );
			}
		} else {
			foreach ( $sections as $section ) {
				self::section( $section[0], $section[1], $section[2] );
			}
		}

		if ( isset( $cache_config['plugins'] ) && count( $cache_config['plugins'] ) > 1 ) {
			$this->warning( 'More than one page cache plugin appears to be active! This could cause unexpected behavior.' );
		}
	}

	/**
	 * Create a new, temporary support user.
	 *
	 * @alias support-user
	 *
	 * @subcommand user
	 *
	 * @throws \Exception If the user could not be created.
	 */
	public function supportUser() {
		$password = wp_generate_password();

		$user_id = SupportUsers::createSupportUser( [ 'user_pass' => $password ] );
		$user    = get_user_by( 'id', $user_id );

		if ( ! $user ) {
			return $this->error( 'Something went wrong creating a support user, please try again.' );
		}

		$this->success( 'A new support user has been created!' )->line();

		self::output( 'URL     ', wp_login_url(), 'W' );
		self::output( 'Username', $user->user_login, 'W' );
		self::output( 'Password', $password, 'W' );

		$this->line()->line( 'This user will automatically expire in 72 hours. You may also remove it manually by running:' )->line();
		$this->line( $this->colorize( "   %c wp user delete {$user->ID} --network --reassign=1%n" ) )->line();
	}

	/**
	 * Serves as a shorthand wrapper for WP_CLI::line() combined with WP_CLI::colorize().
	 *
	 * @since 1.0.0
	 *
	 * @access protected
	 *
	 * @static
	 *
	 * @param string $text        Base text with specifier.
	 * @param mixed  $replacement Replacement text used for sprintf().
	 * @param string $color       Optional. Color code. See WP_CLI::colorize(). Default empty.
	 */
	protected static function outputLine( $text, $replacement, $color = '' ) {
		$color = empty( $color ) ? '' : '%' . $color;
		WP_CLI::line( sprintf( $text, WP_CLI::colorize( $color . $replacement . '%N' ) ) );
	}

	/**
	 * Output two blank lines and a section header.
	 *
	 * @param string $text Section header text.
	 */
	protected static function header( $text ) {
		WP_CLI::line();
		WP_CLI::line( WP_CLI::colorize( sprintf( '%%k%%7 %s %%n', $text ) ) );
		WP_CLI::line();
	}

	/**
	 * Output a line of text with a label and a value.
	 *
	 * @param string      $text  The text to output before the value.
	 * @param string|bool $value The value to output.
	 * @param string      $color A color token to pass to WP_CLI::colorize().
	 */
	protected static function output( $text, $value, $color = '' ) {
		if ( is_bool( $value ) ) {
			$value = $value ? 'Enabled' : 'Disabled';
		}

		self::outputLine( $text . ': %1$s', $value, $color );
	}

	/**
	 * Output a section of debug values.
	 *
	 * @param string $header Header text.
	 * @param array  $data   Data to output. Key is label, value is value.
	 * @param string $key    Key to use for dynamic filter.
	 */
	protected static function section( $header, $data, $key ) {
		// Out the header.
		self::header( $header );

		$data  = apply_filters( 'Nexcess\MAPPS\Support\Details\section_' . $key, $data );
		$width = (int) max( array_map( function( $header ) {
			return strlen( (string) $header );
		}, array_keys( $data ) ) );

		// Go through the array and output each Label: Value pair.
		foreach ( $data as $label => $value ) {
			// If both label & value are blank, then output an empty line.
			if ( '' === $label && '' === $value ) {
				WP_CLI::line();
			} else {
				// Out the line.
				$label = str_pad( $label, $width + 1, ' ', STR_PAD_RIGHT );
				self::output( $label, $value );

			}
		}
	}

	/**
	 * Convert an array of constants into a useful array of values.
	 *
	 * @param array $constants Array of constants.
	 *
	 * @return array Array of constants, keyed by name, value is value or string.
	 */
	protected static function formatConstants( $constants ) {
		$return = [];
		foreach ( $constants as $name ) {
			// Don't want to leak API keys.
			if ( 'NEXCESS_MAPPS_TOKEN' === $name ) {
				$return[ $name ] = defined( 'NEXCESS_MAPPS_TOKEN' ) ? '%K<hidden for security>' : '%R<not set>';
			} else {
				$return[ $name ] = defined( $name ) ? constant( $name ) : '<not set>';
			}
		}

		return $return;
	}

	/**
	 * Retrieve and process the details for the underlying Operating System.
	 *
	 * @since 1.4.0
	 *
	 * @access protected
	 *
	 * @static
	 *
	 * @return string The OS version or the string 'Unknown' if unable to read or parse config file.
	 */
	protected static function get_os_version() {
		$name = 'Unknown';

		if ( is_file( '/etc/os-release' ) && is_readable( '/etc/os-release' ) ) {
			$os_details = parse_ini_file( '/etc/os-release' );

			if ( is_array( $os_details ) && isset( $os_details['PRETTY_NAME'] ) ) {
				$name = $os_details['PRETTY_NAME'];
			}
		}

		return $name;
	}

	/**
	 * Get the details for the cache configuration.
	 *
	 * @param array $page_cache_plugins Array of page cache plugins.
	 *
	 * @return array Array of details.
	 */
	protected function getCacheConfiguration( $page_cache_plugins ) {
		if ( $this->pageCache->isPageCacheEnabled() ) {
			return [
				'enabled'  => 'Enabled',
				'provider' => 'Bundled',
				'htaccess' => $this->pageCache->isHtaccessSectionValid() ? '%GValid' : '%RInvalid',
			];
		}

		if ( $page_cache_plugins ) {
			return [
				'enabled'  => 'Enabled',
				'provider' => implode( ', ', $page_cache_plugins ),
				'plugins'  => $page_cache_plugins,
			];
		}

		return [ 'enabled' => 'Disabled' ];
	}

	/**
	 * Proxies to the WP ClI wp_get_cache_type method to report the cache provider.
	 *
	 * @return string The object cache provider.
	 */
	protected function getObjectCacheProvider() {
		return WP_CLI\Utils\wp_get_cache_type();
	}
}
