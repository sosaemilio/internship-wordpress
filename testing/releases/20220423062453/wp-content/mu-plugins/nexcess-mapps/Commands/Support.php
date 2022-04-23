<?php

namespace Nexcess\MAPPS\Commands;

use Nexcess\MAPPS\Integrations\PageCache;
use Nexcess\MAPPS\Integrations\SupportUsers;
use WP_CLI;

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
	 * Prints information about this WordPress site.
	 *
	 * @since 1.0.0
	 *
	 * @global $wp_version
	 * @global $wpdb
	 */
	public function details() {
		global $wp_version;
		global $wpdb;

		$this->line()
			->line( $this->colorize( sprintf( '%%k%%7%s%%n', __( 'Nexcess Constants', 'nexcess-mapps' ) ) ) )
			->line();

		$constants = [
			'NEXCESS_MAPPS_SITE',
			'NEXCESS_MAPPS_MWCH_SITE',
			'NEXCESS_MAPPS_REGRESSION_SITE',
			'NEXCESS_MAPPS_STAGING_SITE',
			'NEXCESS_MAPPS_PLAN_NAME',
			'NEXCESS_MAPPS_PACKAGE_LABEL',
			'NEXCESS_MAPPS_ENDPOINT',
		];

		array_map( [ get_class(), 'format_constant_line' ], $constants );

		self::format_line(
			/* Translators: %1$s will display text for 'not set' or 'hidden for security'. */
			__( 'NEXCESS_MAPPS_TOKEN: %1$s', 'nexcess-mapps' ),
			defined( 'NEXCESS_MAPPS_TOKEN' )
				? '%G' . _x( '<hidden for security>', 'hidden API token', 'nexcess-mapps' )
				: '%R' . _x( '<not set>', 'displayed text when a constant is not defined', 'nexcess-mapps' ),
			'%_'
		);

		$this->line()
			->line( $this->colorize( sprintf( '%%k%%7%s%%n', __( 'Environment Settings', 'nexcess-mapps' ) ) ) )
			->line();

		self::format_boolean_line( defined( 'WP_DEBUG' ) && WP_DEBUG, __( 'Debug Mode', 'nexcess-mapps' ) );

		$this->line();

		/* Translators: %1$s is the server's host name. */
		self::format_line( __( 'Server Name: %1$s', 'nexcess-mapps' ), gethostname(), '%_' );
		/* Translators: %1$s is the Site's IP Address. */
		self::format_line( __( 'IP: %1$s', 'nexcess-mapps' ), gethostbyname( php_uname( 'n' ) ), '%_' );
		/* Translators: %1$s is server's Operating System Version. */
		self::format_line( __( 'OS Version: %1$s', 'nexcess-mapps' ), self::get_os_version(), '%_' );

		$this->line();

		/* Translators: %1$s is the current WordPress environment type. */
		self::format_line( __( 'Environment type: %1$s', 'nexcess-mapps' ), wp_get_environment_type(), '%_' );
		/* Translators: %1$s is the site's WordPress version. */
		self::format_line( __( 'WP Version: %1$s', 'nexcess-mapps' ), $wp_version, '%_' );
		/* Translators: %1$s is the PHP version defined used the site. */
		self::format_line( __( 'PHP Version (WP): %1$s', 'nexcess-mapps' ), phpversion(), '%_' );
		/* Translators: %1$s is the PHP memory limit. */
		self::format_line( __( 'PHP Memory Limit: %1$s', 'nexcess-mapps' ), ini_get( 'memory_limit' ), '%_' );
		/* Translators: %1$s is the PHP upload max file size. */
		self::format_line( __( 'PHP Upload Max Filesize: %1$s', 'nexcess-mapps' ), ini_get( 'upload_max_filesize' ), '%_' );
		/* Translators: %1$s is the MySQL version. */
		self::format_line( __( 'MySQL Version: %1$s', 'nexcess-mapps' ), $wpdb->get_var( 'SELECT VERSION()' ), '%_' );

		$this->line()
			->line( $this->colorize( sprintf( '%%k%%7%s%%n', __( 'WordPress Configuration', 'nexcess-mapps' ) ) ) )
			->line();

		/* Translators: %1$s is the memory limit for individual requests used by WordPress. */
		self::format_line( __( 'WP Memory Limit (WP_MEMORY_LIMIT): %1$s', 'nexcess-mapps' ), WP_MEMORY_LIMIT, '%_' );
		/* Translators: %1$s is the absolute file path to WordPress. */
		self::format_line( __( 'Absolute Path (ABSPATH): %1$s', 'nexcess-mapps' ), ABSPATH, '%_' );
		/* Translators: %1$s is the value of WP_CONTENT_DIR. */
		self::format_line( __( 'WP Content Directory (WP_CONTENT_DIR): %1$s', 'nexcess-mapps' ), WP_CONTENT_DIR, '%_' );
		/* Translators: %1$s is the value of WP_CONTENT_DIR. */
		self::format_line( __( 'WP Uploads Directory: %1$s', 'nexcess-mapps' ), wp_get_upload_dir()['basedir'], '%_' );
		/* Translators: %1$s is the language defined by WordPress. Will default to 'en_US' if not defined. */
		self::format_line( __( 'WPLANG: %1$s', 'nexcess-mapps' ), defined( 'WPLANG' ) && WPLANG ? WPLANG : 'en_US', '%_' );
		self::format_boolean_line( is_multisite(), __( 'WordPress Multisite', 'nexcess-mapps' ) );

		$this->line()
			->line( $this->colorize( sprintf( '%%k%%7%s%%n', __( 'Site Information', 'nexcess-mapps' ) ) ) )
			->line();

		/* Translators: %1$s is the site's home url. */
		self::format_line( __( 'Home URL: %1$s', 'nexcess-mapps' ), get_home_url(), '%_' );
		/* Translators: %1$s is the site's url. */
		self::format_line( __( 'Site URL: %1$s', 'nexcess-mapps' ), site_url(), '%_' );
		/* Translators: %1$s is the admin email address. */
		self::format_line( __( 'Admin Email: %1$s', 'nexcess-mapps' ), get_option( 'admin_email' ), '%_' );

		$permalink_structure = get_option( 'permalink_structure' ) ? get_option( 'permalink_structure' ) : 'Default';
		/* Translators: %1$s is the site's permalink structure. */
		$this->line( sprintf( __( 'Permalink Structure: %1$s', 'nexcess-mapps' ), $permalink_structure ) )
			->line()
			->line( $this->colorize( sprintf( '%%k%%7%s%%n', __( 'Cache Configuration', 'nexcess-mapps' ) ) ) )
			->line();

		$page_cache_plugins = $this->pageCache->getActivePageCachePlugins();
		if ( $this->pageCache->isPageCacheEnabled() ) {
			// Using the bundled page cache.
			self::format_boolean_line( true, __( 'Page Cache', 'nexcess-mapps' ) );
			$htaccess_valid = $this->pageCache->isHtaccessSectionValid();
			self::format_line(
				/* Translators: %1$s is the plugin provider, either 'Bundled', or a comma separated list of plugins. */
				__( 'Page Cache Provider: %1$s', 'nexcess-mapps' ),
				__( 'Bundled', 'nexcess-mapps' ),
				'%_'
			);
			self::format_line(
				/* Translators: %1$s is the string indicating valid or invalid. */
				__( 'Page Cache .htaccess Rules: %1$s', 'nexcess-mapps' ),
				$htaccess_valid ? __( 'Valid', 'nexcess-mapps' ) : __( 'Invalid', 'nexcess-mapps' ),
				$htaccess_valid ? '%G' : '%R'
			);
		} elseif ( $page_cache_plugins ) {
			// Using a page cache plugin.
			self::format_boolean_line( true, __( 'Page Cache', 'nexcess-mapps' ) );
			self::format_line(
				/* Translators: %1$s is the plugin provider, either 'Bundled', or a comma separated list of plugins. */
				__( 'Page Cache Provider: %1$s', 'nexcess-mapps' ),
				implode( ', ', $page_cache_plugins ),
				'%_'
			);
			// If more than one page cache providers are found, report a warning.
			if ( count( $page_cache_plugins ) > 1 ) {
				$this->warning( __( 'More than one page cache plugin appears to be active! This could cause unexpected behavior.', 'nexcess-mapps' ) );
			}
		} else {
			// Page cache disabled.
			self::format_boolean_line( false, __( 'Page Cache', 'nexcess-mapps' ) );
		}

		$this->line();
		/* Translators: %1$s is the caching provider as reported by wp cache type. (e.g. Redis, Memcached, etc.) */
		self::format_line( __( 'Object Cache Provider: %1$s', 'nexcess-mapps' ), $this->getObjectCacheProvider(), '%_' );
		$this->line();
	}

	/**
	 * Create a new, temporary support user.
	 *
	 * @alias support-user
	 * @subcommand user
	 *
	 * @throws \Exception If the user could not be created.
	 */
	public function supportUser() {
		$password = wp_generate_password();

		try {
			$user_id = SupportUsers::createSupportUser( [
				'user_pass' => $password,
			] );
			$user    = get_user_by( 'id', $user_id );

			if ( ! $user ) {
				throw new \Exception( sprintf( 'Could not find user with ID %d', $user_id ) );
			}
		} catch ( \Exception $e ) {
			return $this->error( 'Something went wrong creating a support user: ' . $e->getMessage() );
		}

		$this->success( 'A new support user has been created:' )
			->line()
			->line( $this->colorize( "\t%Wurl:%N " ) . wp_login_url() )
			->line( $this->colorize( "\t%Wusername:%N {$user->user_login}" ) )
			->line( $this->colorize( "\t%Wpassword:%N " ) . $password )
			->line()
			->line( 'This user will automatically expire in 72 hours. You may also remove it manually by running:' )
			->line( $this->colorize( "\t%c$ wp user delete {$user->ID}%n" ) );
	}

	/**
	 * Serves as a shorthand wrapper for WP_CLI::line() combined with WP_CLI::colorize().
	 *
	 * @since 1.0.0
	 * @access protected
	 * @static
	 *
	 * @param string $text        Base text with specifier.
	 * @param mixed  $replacement Replacement text used for sprintf().
	 * @param string $color       Optional. Color code. See WP_CLI::colorize(). Default empty.
	 */
	protected static function format_line( $text, $replacement, $color = '' ) {
		WP_CLI::line( sprintf( $text, WP_CLI::colorize( $color . $replacement . '%N' ) ) );
	}

	/**
	 * Helper function to format the output of a boolean variable.
	 *
	 * @since 1.4.0
	 * @access protected
	 * @static
	 *
	 * @param bool   $enabled      Whether the variable is enabled or not.
	 * @param string $display_name Display name for the variable.
	 */
	protected static function format_boolean_line( $enabled, $display_name ) {
		self::format_line(
			sprintf( '%s: %%s', $display_name ),
			$enabled ? __( 'Enabled', 'nexcess-mapps' ) : __( 'Disabled', 'nexcess-mapps' ),
			$enabled ? '%G' : '%R'
		);
	}

	/**
	 * Helper function to format the output of a constant.
	 *
	 * @since 1.4.0
	 * @access protected
	 * @static
	 *
	 * @param string $name Constant name.
	 */
	protected static function format_constant_line( $name ) {
		self::format_line(
			/* Translators: %1$s is the name of the constant. %%s will be either 'Enabled' or 'Disabled' */
			sprintf( __( '%1$s: %%s', 'nexcess-mapps' ), $name ),
			defined( $name ) ? constant( $name ) : _x( '<not set>', 'displayed text when a constant is not defined', 'nexcess-mapps' ),
			'%_'
		);
	}

	/**
	 * Retrieve and process the details for the underlying Operating System.
	 *
	 * @since 1.4.0
	 * @access protected
	 * @static
	 *
	 * @return string The OS version or the string 'Unknown' if unable to read or parse config file.
	 */
	protected static function get_os_version() {
		$name = _x( 'Unknown', 'Unknown Operating System Version', 'nexcess-mapps' );

		if ( is_file( '/etc/os-release' ) && is_readable( '/etc/os-release' ) ) {
			$os_details = parse_ini_file( '/etc/os-release' );

			if ( is_array( $os_details ) && isset( $os_details['PRETTY_NAME'] ) ) {
				$name = $os_details['PRETTY_NAME'];
			}
		}

		return $name;
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
