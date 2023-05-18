<?php

/**
 * Full-page cache integration.
 */

namespace Nexcess\MAPPS\Integrations;

use Cache_Enabler;
use Cache_Enabler_Disk;
use Nexcess\MAPPS\Concerns\HasHooks;
use Nexcess\MAPPS\Concerns\HasWordPressDependencies;
use Nexcess\MAPPS\Concerns\ManagesHtaccess;
use Nexcess\MAPPS\Concerns\ManagesPermalinks;
use Nexcess\MAPPS\Modules\Telemetry;
use Nexcess\MAPPS\Services\DropIn;
use Nexcess\MAPPS\Services\WPConfig;
use Nexcess\MAPPS\Settings;
use Nexcess\MAPPS\Support\AdminNotice;
use Nexcess\MAPPS\Support\Apache;
use Nexcess\MAPPS\Support\Filesystem;
use StellarWP\PluginFramework\Exceptions\FilesystemException;
use StellarWP\PluginFramework\Exceptions\WPConfigException;
use StellarWP\PluginFramework\Support\Branding;

use const Nexcess\MAPPS\VENDOR_DIR;

class PageCache extends Integration {
	use HasHooks;
	use HasWordPressDependencies;
	use ManagesHtaccess;
	use ManagesPermalinks;

	/**
	 * @var \Nexcess\MAPPS\Services\WPConfig
	 */
	protected $config;

	/**
	 * @var \Nexcess\MAPPS\Services\DropIn
	 */
	protected $dropIn;

	/**
	 * Whether or not we should load the bundled page caching solution.
	 *
	 * @var bool
	 */
	protected $loadBundledVersion = true;

	/**
	 * A list of known page caching plugins (minus Cache Enabler).
	 *
	 * If any of these are active following the migration to the bundled page cache, we'll disable
	 * our version in favor of these.
	 *
	 * @var string[]
	 */
	protected $pageCachePlugins = [
		'breeze/breeze.php',
		'comet-cache/comet-cache.php',
		'comet-cache-pro/comet-cache-pro.php',
		'hummingbird-performance/wp-hummingbird.php',
		'nitropack/main.php',
		'speed-booster-pack/speed-booster-pack.php',
		'swift-performance-lite/performance.php',
		'swift-performance/performance.php',
		'w3-total-cache/w3-total-cache.php',
		'wp-fastest-cache/wpFastestCache.php',
		'wp-optimize/wp-optimize.php',
		'wp-rocket/wp-rocket.php',
		'wp-super-cache/wp-cache.php',
	];

	/**
	 * @var \Nexcess\MAPPS\Settings
	 */
	protected $settings;

	/**
	 * The marker name used in the Htaccess file.
	 *
	 * This value should not be changed without accounting for previous value(s) in the injection
	 * and removal methods!
	 */
	const HTACCESS_MARKER = 'Cache Enabler';

	/**
	 * The key used in the telemetry report which contains the relevant integration info.
	 */
	const TELEMETRY_FEATURE_KEY = 'page_cache';

	/**
	 * The key used to report whether the integration is enabled.
	 */
	const TELEMETRY_FEATURE_ENABLED_KEY = 'enabled';

	/**
	 * The key used to report whether the integration is in use.
	 */
	const TELEMETRY_FEATURE_IN_USE_KEY = 'in_use';

	/**
	 * The key used to report the integration provider.
	 */
	const TELEMETRY_FEATURE_PROVIDER_KEY = 'provider';

	/**
	 * The default provider used in the report when WP CLI does not report one, which indicates it's ours.
	 */
	const TELEMETRY_FEATURE_PROVIDER_DEFAULT = 'Nexcess MU Plugin';

	/**
	 * @param \Nexcess\MAPPS\Settings          $settings
	 * @param \Nexcess\MAPPS\Services\WPConfig $wp_config
	 * @param \Nexcess\MAPPS\Services\DropIn   $drop_in
	 */
	public function __construct(
		Settings $settings,
		WPConfig $wp_config,
		DropIn $drop_in
	) {
		$this->settings = $settings;
		$this->config   = $wp_config;
		$this->dropIn   = $drop_in;
	}

	/**
	 * Perform any necessary setup for the integration.
	 *
	 * This method is automatically called as part of Plugin::loadIntegration(), and is the
	 * entry-point for all integrations.
	 */
	public function setup() {
		if (
			$this->isPluginActive( 'cache-enabler/cache-enabler.php' )
			|| $this->isPluginBeingActivated( 'cache-enabler/cache-enabler.php' )
			|| is_multisite()
		) {
			$this->loadBundledVersion = false;
		}

		// Register hooks, which may vary based on $this->loadBundledVersion.
		$this->addHooks();

		// Prevent conflicts by returning early if standard Cache Enabler is present.
		if ( ! $this->loadBundledVersion ) {
			return;
		}

		// Otherwise, load the bundled version.
		$this->loadPlugin( 'keycdn/cache-enabler/cache-enabler.php' );
	}

	/**
	 * Retrieve all filters for the integration.
	 *
	 * @return array[]
	 */
	protected function getFilters() {
		return [
			[ Telemetry::REPORT_DATA_FILTER, [ $this, 'addFeatureToTelemetry' ] ],
		];
	}

	/**
	 * Retrieve all actions for the integration.
	 *
	 * @return array[]
	 */
	protected function getActions() {
		$base = [
			[ 'default_option_cache_enabler', [ $this, 'getCacheEnablerSettings' ] ],
			[ 'activate_plugin', [ $this, 'activateAlternatePageCache' ], -10000 ],
			[ 'deactivated_plugin', [ $this, 'deactivateAlternatePageCache' ], 1 ],
			[ 'after_plugin_row_cache-enabler/cache-enabler.php', [ $this, 'renderCacheEnablerNotice' ], 10, 2 ],

			// Check cache directory permissions early and often.
			[ 'cache_enabler_complete_cache_cleared', [ $this, 'setCacheDirectoryPermissions' ] ],
			[ Maintenance::DAILY_MAINTENANCE_CRON_ACTION, [ $this, 'runPageCacheMaintenance' ] ],
			[ Maintenance::DAILY_MAINTENANCE_CRON_ACTION, [ $this, 'cleanCacheEnablerWpConfig' ] ],
		];

		// Actions that vary based on whether or not we're loading the bundled solution.
		if ( $this->loadBundledVersion ) {
			$actions = [
				[ 'plugins_loaded', [ $this, 'customizeCacheEnabler' ], 11 ],
			];
		} else {
			// Simply help with adding + removing rewrite rules.
			$actions = [
				[ 'activate_cache-enabler/cache-enabler.php', [ $this, 'injectCacheEnablerRewriteRules' ] ],
				[ 'deactivate_cache-enabler/cache-enabler.php', [ $this, 'removeCacheEnablerRewriteRules' ] ],
			];
		}

		return array_merge( $base, $actions );
	}

	/**
	 * Determine whether or not the site is currently running some form of Cache Enabler, of either
	 * the bundled or stand-alone variety.
	 *
	 * @return bool True if either the bundled page cache is active *or* the Cache Enabler plugin
	 *              is installed and active, false otherwise.
	 */
	public function isCacheEnablerActive() {
		return $this->isPageCacheEnabled() || $this->isPluginActive( 'cache-enabler/cache-enabler.php' );
	}

	/**
	 * Get the currently active page-caching plugins based on the known list of plugins.
	 *
	 * @return string[] An array of active page-caching plugins.
	 */
	public function getActivePageCachePlugins() {
		$plugins   = $this->pageCachePlugins;
		$plugins[] = 'cache-enabler/cache-enabler.php';
		return $this->findActivePlugins( $plugins );
	}

	/**
	 * Actions to run when activating one of the alternate page cache plugins.
	 *
	 * If the customer is activating a different page caching solution, we should intelligently
	 * turn off our solution so we don't get in their way.
	 *
	 * @param string $plugin The plugin that has been activated.
	 */
	public function activateAlternatePageCache( $plugin ) {
		$plugins   = $this->pageCachePlugins;
		$plugins[] = 'cache-enabler/cache-enabler.php';

		if ( ! in_array( $plugin, $plugins, true ) ) {
			return;
		}

		// Was the bundled version previously active?
		$was_using_bundled = $this->isPageCacheEnabled();

		// Remove the admin notice if we've previously told the user we've deactivated this plugin.
		AdminNotice::forgetPersistentNotice( 'page-cache-deactivated-' . $plugin );

		try {
			// Disable our page cache in favor of the one they're currently activating.
			$this->disablePageCache();

			// Notify the user if the bundled page cache was disabled.
			if ( $was_using_bundled ) {
				$this->pluginActivationNotice( $plugin );
			}
		} catch ( \Exception $e ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			error_log( esc_html( sprintf(
				'[MAPPS][warning] Something went wrong disabling the bundled page cache: %s',
				$e->getMessage()
			) ) );
		}
	}

	/**
	 * Actions to run when deactivating one of the alternate page cache plugins.
	 *
	 * @param string $plugin The plugin that has been activated.
	 */
	public function deactivateAlternatePageCache( $plugin ) {
		$plugins   = $this->pageCachePlugins;
		$plugins[] = 'cache-enabler/cache-enabler.php';

		if ( ! in_array( $plugin, $plugins, true ) ) {
			return;
		}

		// Remove the admin notice if we've previously told the user we've deactivated this plugin.
		AdminNotice::forgetPersistentNotice( 'page-cache-activated-alternate' );
	}

	/**
	 * Periodically run through the wp-config.php file and clean up leftover comments about
	 * Cache Enabler.
	 */
	public function cleanCacheEnablerWpConfig() {
		$file = ABSPATH . 'wp-config.php';

		if ( ! file_exists( $file ) || ! is_writable( $file ) ) {
			return;
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		$contents = (string) file_get_contents( $file );
		$pattern  = '/\/\*{1,2} Enables page caching for Cache Enabler\. \*\/\s*if \( ! defined\( \'WP_CACHE\'(?: \)){2}\s*{\s*\}\s*/im';
		$updated  = preg_replace( $pattern, '', $contents );

		// Only write the contents if the file has changed.
		if ( $contents !== $updated ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_read_file_put_contents
			file_put_contents( $file, $updated, LOCK_EX );
		}
	}

	/**
	 * Provide customizations to Cache Enabler when running the bundled version.
	 *
	 * This should be run on plugins_loaded at a priority *greater than* 10, which is when the
	 * Cache_Enabler::init() method is normally run.
	 */
	public function customizeCacheEnabler() {
		// Move Settings > Cache Enabler to Nexcess > Page Cache.
		remove_action( 'admin_menu', 'Cache_Enabler::add_settings_page' );
		add_action( 'admin_menu', function () {
			add_submenu_page(
				Dashboard::ADMIN_MENU_SLUG,
				_x( 'Page Cache', 'page title', 'nexcess-mapps' ),
				_x( 'Page Cache', 'menu item title', 'nexcess-mapps' ),
				'manage_options',
				'mapps-page-cache',
				'Cache_Enabler::settings_page'
			);
		} );

		// Remove the requirements check, as we already do this.
		remove_action( 'admin_notices', 'Cache_Enabler::requirements_check' );

		// Use our handler when the plugin settings are changed.
		remove_action( 'add_option_cache_enabler', 'Cache_Enabler::on_update_backend', 10 );
		remove_action( 'update_option_cache_enabler', 'Cache_Enabler::on_update_backend', 10 );
		add_action( 'update_option_cache_enabler', [ $this, 'settingsUpdated' ], 10, 2 );

		// Only show the Admin Bar item if the bundled page cache is active.
		if ( ! $this->isPageCacheEnabled() || $this->isAtLeastOnePluginActive( $this->pageCachePlugins ) ) {
			remove_action( 'admin_bar_menu', 'Cache_Enabler::add_admin_bar_items', 90 );
		}
	}

	/**
	 * Disable full-page caching.
	 *
	 * Since the bundled plugin doesn't have access to traditional activation hooks, explicitly run
	 * the deactivation steps.
	 *
	 * @throws \StellarWP\PluginFramework\Exceptions\FilesystemException If the htaccess file cannot be read.
	 * @throws \StellarWP\PluginFramework\Exceptions\WPConfigException   If the config file cannot be written.
	 */
	public function disablePageCache() {
		// Explicitly set the flag in wp_options.
		remove_action( 'update_option_cache_enabler', [ $this, 'settingsUpdated' ], 10 );
		update_option( 'cache_enabler', array_merge( get_option( 'cache_enabler', [] ), [
			'enabled' => 0,
		] ) );

		try {
			if ( ! $this->removeCacheEnablerRewriteRules() ) {
				throw new FilesystemException( 'Unable to remove rules from the Htaccess file.' );
			}

			$this->dropIn->remove( 'advanced-cache.php', VENDOR_DIR . 'keycdn/cache-enabler/advanced-cache.php' );
			$this->config->removeConstant( 'WP_CACHE' );
			$this->flushPageCache();
		} catch ( \Exception $e ) {
			throw new WPConfigException( $e->getMessage(), $e->getCode(), $e );
		}
	}

	/**
	 * Enable full-page caching.
	 *
	 * Since the bundled plugin doesn't have access to traditional activation hooks, explicitly run
	 * the activation steps.
	 *
	 * @throws \StellarWP\PluginFramework\Exceptions\FilesystemException If the htaccess file cannot be written.
	 * @throws \StellarWP\PluginFramework\Exceptions\WPConfigException   If anything goes wrong.
	 */
	public function enablePageCache() {
		// First, deactivate any known page cache plugins.
		add_action( 'deactivated_plugin', [ $this, 'pluginDeactivationNotice' ] );
		$this->deactivatePlugin( $this->pageCachePlugins, false );
		AdminNotice::forgetPersistentNotice( 'page-cache-activated-alternate' );

		// Explicitly set the flag in wp_options.
		remove_action( 'update_option_cache_enabler', [ $this, 'settingsUpdated' ], 10 );
		update_option( 'cache_enabler', array_merge( get_option( 'cache_enabler', $this->getCacheEnablerSettings() ), [
			'enabled' => 1,
		] ) );

		// Now, set up everything we need for our solution.
		try {
			$this->setDefaultPermalinkStructure();
			$this->config->setConstant( 'WP_CACHE', true );
			$this->dropIn->install( 'advanced-cache.php', VENDOR_DIR . 'keycdn/cache-enabler/advanced-cache.php', true );

			if ( ! $this->injectCacheEnablerRewriteRules() ) {
				throw new FilesystemException( 'Unable to write rules to the Htaccess file.' );
			}
		} catch ( \Exception $e ) {
			throw new WPConfigException( $e->getMessage(), $e->getCode(), $e );
		}
	}

	/**
	 * Flush the page cache.
	 */
	public function flushPageCache() {
		if ( class_exists( 'Cache_Enabler' ) ) {
			Cache_Enabler::clear_complete_cache();
		}
	}

	/**
	 * Retrieve our default Cache Enabler settings.
	 *
	 * These settings correspond to the Cache_Enabler::settings_page() method.
	 *
	 * @link https://www.keycdn.com/support/wordpress-cache-enabler-plugin
	 *
	 * @return mixed[]
	 */
	public function getCacheEnablerSettings() {
		/*
		 * A collection of paths that should be excluded.
		 *
		 * When adding a path here, it's essentially excluding the following pattern from the
		 * full-page cache:
		 *
		 *   /some-path*
		 */
		$excluded_paths = [
			'account',
			'addons',
			'administrator',
			'affiliate-area.php',
			'cart',
			'checkout',
			'events',
			'lock.php',
			'login',
			'mepr',
			'my-account',
			'page/ref',
			'purchase-confirmation',
			'ref',
			'register',
			'resetpass',
			'store',
			'thank-you',
			'wp-cron.php',
			'wp-includes',
			'wp-json',
			'xmlrpc.php',
		];

		/*
		 * A collection of cookie prefixes that, if present, should cause the cache to be bypassed.
		 */
		$excluded_cookie_prefixes = [
			'comment_author_',
			'edd_cart',
			'edd_cart_fees',
			'edd_cart_messages',
			'edd_discounts',
			'edd_items_in_cart',
			'edd_purchase',
			'edd_resume_payment',
			'mplk',
			'mp3pi141592pw',
			'preset_discount',
			'woocommerce_',
			'wordpress_',
			'wp-postpass_',
			'wp-resetpass-',
			'wp-settings-',
			'wp_woocommerce_session',
		];

		// Escape anything that might break regular expressions.
		$excluded_cookie_prefixes = array_map( 'preg_quote', $excluded_cookie_prefixes );
		$excluded_paths           = array_map( function ( $path ) {
			return preg_quote( $path, '/' );
		}, $excluded_paths );

		// phpcs:disable WordPress.Arrays
		return [
			// Do not activate by default, as this option will constantly get created by Cache_Enabler::update_backend().
			'enabled' => 0,

			// Store the version or Cache Enabler will try to re-calculate everything.
			'version' => defined( 'CACHE_ENABLER_VERSION' ) ? CACHE_ENABLER_VERSION : null,

			// Whether or not the cache should expire.
			'cache_expires' => 1,

			// Cached pages expire X hours after being created.
			'cache_expiry_time' => 2,

			// Clear the site cache if any post type has been published, updated, or trashed (instead of only the page and/or associated cache).
			'clear_site_cache_on_saved_post' => 0,

			// Clear the site cache if a comment has been saved (instead of only the page cache).
			'clear_site_cache_on_saved_comment' => 0,

			// Clear the site cache if any plugin has been activated, updated, or deactivated.
			'clear_site_cache_on_changed_plugin' => 0,

			// Create an additional cached version for WebP image support.
			'convert_image_urls_to_webp' => 0,

			// Pre-compress cached pages with Gzip.
			'compress_cache' => 0,

			// Minify HTML in cached pages.
			'minify_html' => 0,

			// Minify inline CSS + JavaScript.
			'minify_inline_css_js' => 0,

			// Create additional cached versions for mobile users.
			'mobile_cache' => 0,

			// Post IDs that should bypass the cache.
			'excluded_post_ids' => '',

			// A regex matching page paths that should bypass the cache.
			'excluded_page_paths' => sprintf( '/^\/(%1$s)\/?/', implode( '|', $excluded_paths ) ),

			// A regex matching cookies that should bypass the cache.
			'excluded_cookies' => sprintf( '/^(?!wordpress_test_cookie)(%1$s).*/', implode( '|', $excluded_cookie_prefixes ) ),

			// Query strings that should bypass the cache.
			'excluded_query_strings' => '',
		];
		// phpcs:enable WordPress.Arrays
	}

	/**
	 * Determine whether or not the built-in page cache is currently enabled.
	 *
	 * Note that there's a difference between being *enabled* and in-use — this method specifically
	 * reads the "enabled" key (if available) within the cache_enabler option.
	 *
	 * @return bool True if the "enabled" flag has explicitly been set, false otherwise.
	 */
	public function isPageCacheEnabled() {
		$option = get_option( 'cache_enabler' );

		return isset( $option['enabled'] ) ? (bool) $option['enabled'] : false;
	}

	/**
	 * Actions to perform after the Cache Enabler configuration has changed.
	 *
	 * @param mixed[] $old The previous configuration.
	 * @param mixed[] $new The new configuration.
	 */
	public function settingsUpdated( $old, $new ) {
		Cache_Enabler_Disk::create_settings_file( $new );

		/*
		 * Handle toggling the page cache on or off.
		 *
		 * The `wp nxmapps setup` command explicitly enables the page cache, so "enabled" should
		 * always exist — if it doesn't, something's not right and it's best not to risk
		 * accidentally enabling the page cache.
		 */
		if ( isset( $old['enabled'] ) && $old['enabled'] !== $new['enabled'] ) {
			try {
				if ( 1 === $new['enabled'] ) {
					$this->enablePageCache();
				} else {
					$this->disablePageCache();
				}
			} catch ( \Exception $e ) {
				// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
				error_log( '[MAPPS][error] Unable to toggle page cache: ' . esc_html( $e->getMessage() ) );
			}

			// Return early, as there's nothing more that needs to be done.
			return;
		}

		/*
		 * Update the Htaccess snippet when changes are made.
		 *
		 * We'll only update the rules if all of the following conditions are met:
		 *
		 * 1. The page cache is enabled.
		 * 2. There is an existing snippet in the Htaccess file.
		 */
		if (
			isset( $new['enabled'] ) && 1 === $new['enabled']
			&& ! empty( $this->getHtaccessFileSection( self::HTACCESS_MARKER ) )
		) {
			$this->injectCacheEnablerRewriteRules();
		}
	}

	/**
	 * Display a notice about Nexcess page caching.
	 *
	 * Since this feature is now built into the platform, customers no longer need to install Cache Enabler.
	 *
	 * @global $wp_list_table
	 *
	 * @param string  $file   Path to the plugin file relative to the plugins directory.
	 * @param mixed[] $plugin An array of plugin data.
	 */
	public function renderCacheEnablerNotice( $file, $plugin ) {
		global $wp_list_table;

		$message = sprintf(
			/* Translators: %1$s is the company name, %2$s is Cache Enabler's name. */
			__( 'Full-page caching is now handled automatically by the %1$s platform. You may safely remove %2$s.', 'nexcess-mapps' ),
			Branding::getCompanyName(),
			$plugin['Name']
		);
		$template = <<<'EOT'
<tr class="plugin-update-tr mapps-plugin-notice%1$s" id="%2$s-update" data-slug="%2$s" data-plugin="%3$s">
	<td colspan="%4$d" class="plugin-update colspanchange">
		<div class="notice inline notice-info notice-alt">
			<p>%5$s</p>
		</div>
	</td>
</tr>
EOT;

		printf(
			$template,
			$this->isPluginActive( $file ) ? ' active' : '',
			esc_attr( $plugin['slug'] ),
			esc_attr( $file ),
			count( $wp_list_table->get_columns() ),
			esc_html( $message )
		);
	}

	/**
	 * Ensure the wp-content/cache/ directory both exists and is writable.
	 *
	 * If the cache directory cannot be created/modified with the correct permissions, we'll run
	 * $this->removeCacheEnablerRewriteRules() to ensure sites aren't trying to serve from a
	 * directory they can't read and/or write to.
	 *
	 * @throws \StellarWP\PluginFramework\Exceptions\FilesystemException If something goes wrong.
	 *
	 * @return bool True if permissions were set/okay, false otherwise.
	 */
	public function setCacheDirectoryPermissions() {
		$fs = Filesystem::init();

		try {
			$directories = [
				$fs->wp_content_dir() . '/cache',
				$fs->wp_content_dir() . '/cache/cache-enabler',
				$fs->wp_content_dir() . '/settings',
				$fs->wp_content_dir() . '/settings/cache-enabler',
			];

			foreach ( $directories as $dir ) {
				// Something exists here, but it's not a directory.
				if ( $fs->exists( $dir ) && ! $fs->is_dir( $dir ) ) {
					/*
					 * If the directory is a symlink, chances are someone has gone out of their way
					 * to set this up. Move along so we don't risk messing up whatever they might
					 * be doing.
					 */
					if ( is_link( $dir ) ) {
						continue;
					}

					throw new FilesystemException(
						sprintf( '%s exists, but is not a directory', $dir )
					);
				}

				// Create the directory if it doesn't exist.
				if ( ! $fs->exists( $dir ) && ! $fs->mkdir( $dir, 0755 ) ) {
					throw new FilesystemException(
						sprintf( 'Unable to create the directory %s', $dir )
					);
				}

				// Verify permissions.
				if ( '755' === $fs->getchmod( $dir ) ) {
					continue;
				}

				if ( ! $fs->chmod( $dir, 0755 ) ) {
					throw new FilesystemException(
						sprintf( 'Unable to set permissions for directory %s', $dir )
					);
				}
			}
		} catch ( FilesystemException $e ) {
			// Prevent sites from trying to serve caches that don't exist.
			$this->removeCacheEnablerRewriteRules();
			return false;
		}

		return true;
	}

	/**
	 * Checks to ensure the htaccess section matches the expected value if using the bundled page cache.
	 *
	 * @return bool Whether or not the htaccess section matches the expected value.
	 */
	public function isHtaccessSectionValid() {
		$htaccess_section = $this->getHtaccessFileSection( self::HTACCESS_MARKER );
		if ( ! $this->isPageCacheEnabled() ) {
			return '' === $htaccess_section;
		}

		return $htaccess_section === $this->getCacheEnablerHtaccess();
	}

	/**
	 * Get the Htaccess rewrite rules for Cache Enabler.
	 *
	 * @return string The rewrite rules, with each line as an array value.
	 */
	public function getCacheEnablerHtaccess() {
		$template = dirname( __DIR__ ) . '/assets/snippets/cache-enabler-htaccess.conf';

		if ( ! is_readable( $template ) ) {
			return '';
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		$htaccess = (string) file_get_contents( $template );
		$settings = get_option( 'cache_enabler' );

		// Replace default lines from the Htaccess file with those matching current settings.
		if ( ! empty( $settings['excluded_cookies'] ) ) {
			$cookies = Apache::regexToRewriteCond( $settings['excluded_cookies'] );

			if ( $cookies ) {
				$htaccess = (string) preg_replace(
					'@RewriteCond %\{HTTP_COOKIE\} !.+@',
					sprintf( 'RewriteCond %%{HTTP_COOKIE} !%1$s', $cookies ),
					$htaccess
				);
			}
		}

		if ( ! empty( $settings['excluded_page_paths'] ) ) {
			$paths = Apache::regexToRewriteCond( $settings['excluded_page_paths'] );

			if ( $paths ) {
				$htaccess = (string) preg_replace(
					'@RewriteCond %\{ENV\:CE_PATH\} !.+@',
					sprintf( 'RewriteCond %%{ENV:CE_PATH} !%1$s', $paths ),
					$htaccess
				);
			}
		}

		return $htaccess;
	}

	/**
	 * Inject the Cache Enabler rewrite rules into the site's Htaccess file.
	 *
	 * @link https://www.keycdn.com/support/wordpress-cache-enabler-plugin#apache
	 *
	 * @throws \Exception If something goes wrong.
	 *
	 * @return bool True if the lines were added, false otherwise.
	 */
	public function injectCacheEnablerRewriteRules() {
		// Before we do anything else, make sure the file permissions allow full-page caching.
		if ( ! $this->setCacheDirectoryPermissions() ) {
			return false;
		}

		$rules = $this->getCacheEnablerHtaccess();

		try {
			if ( ! $this->writeHtaccessFileSection( self::HTACCESS_MARKER, $rules, true ) ) {
				throw new \Exception( 'Unable to write to Htaccess file.' );
			}
		} catch ( \Exception $e ) {
			$message  = '<strong>';
			$message .= __( 'We were unable to update your .htaccess file', 'nexcess-mapps' );
			$message .= '</strong>' . PHP_EOL . PHP_EOL;
			$message .= __( 'To take full advantage of full-page caching, the following block should be added to your site\'s .htaccess file before <code># BEGIN WordPress</code>:', 'nexcess-mapps' );
			$message .= '<pre style="overflow: auto;"># BEGIN ' . self::HTACCESS_MARKER . PHP_EOL;
			$message .= esc_html( $rules );
			$message .= PHP_EOL . '# END ' . self::HTACCESS_MARKER . '</pre>';

			( new AdminNotice( $message, 'warning', true, 'page-cache-htaccess-rules' ) )
				->setCapability( 'manage_options' )
				->persist();
			return false;
		}

		return true;
	}

	/**
	 * Remove the Cache Enabler rewrite rules from the site's Htaccess file.
	 *
	 * @throws \Exception If something goes wrong.
	 *
	 * @return bool True if the lines were removed, false otherwise.
	 */
	public function removeCacheEnablerRewriteRules() {
		try {
			if ( ! $this->writeHtaccessFileSection( self::HTACCESS_MARKER, '' ) ) {
				throw new \Exception( 'Unable to write to Htaccess file.' );
			}
		} catch ( \Exception $e ) {
			$message  = '<strong>';
			$message .= __( 'We were unable to update your .htaccess file', 'nexcess-mapps' );
			$message .= '</strong>' . PHP_EOL . PHP_EOL;
			$message .= __( 'Your site\'s .htaccess file contains rewrite rules that may cause old, cached pages to be served.', 'nexcess-mapps' );
			$message .= PHP_EOL . PHP_EOL;
			$message .= sprintf(
				/* Translators: %1$s is the Htaccess marker, %2$s is the support URL. */
				__( 'Please edit this file and remove everything between <code># BEGIN %1$s</code> and <code># END %1$s</code> or <a href="%2$s">contact support</a>.', 'nexcess-mapps' ),
				self::HTACCESS_MARKER,
				Branding::getSupportUrl()
			);

			( new AdminNotice( $message, 'error', true, 'page-cache-htaccess-rules' ) )
				->setCapability( 'manage_options' )
				->persist();
			return false;
		}

		return true;
	}

	/**
	 * Persist a notification about the bundled solution being disabled due to an alternate plugin
	 * being activated.
	 *
	 * @param string $plugin The plugin file.
	 */
	public function pluginActivationNotice( $plugin ) {
		$plugin_name = $this->getPluginName( $plugin );
		$message     = sprintf(
			'<strong>%1$s</strong>' . PHP_EOL . '%2$s',
			sprintf(
				/* Translators: %1$s is the plugin name. */
				__( 'The built-in page cache has been deactivated to prevent conflicts with %1$s.', 'nexcess-mapps' ),
				$plugin_name
			),
			sprintf(
				/* Translators: %1$s is the plugin name, %2$sis the URL to the built-in page cache settings page. */
				__( 'If you would prefer to continue using the built-in page cache, you may <a href="%1$s">reactivate it</a> and %2$s will be deactivated automatically.', 'nexcess-mapps' ),
				admin_url( 'admin.php?page=mapps-page-cache' ),
				$plugin_name
			)
		);

		( new AdminNotice( $message, 'info', true, 'page-cache-activated-alternate' ) )
			->setCapability( 'manage_options' )
			->persist();
	}

	/**
	 * Persist a notification about a page cache plugin being deactivated to prevent conflicts with
	 * the bundled page cache solution.
	 *
	 * @param string $plugin The plugin file.
	 */
	public function pluginDeactivationNotice( $plugin ) {
		$plugin_name = $this->getPluginName( $plugin );
		$message     = sprintf(
			'<strong>%1$s</strong>' . PHP_EOL . '%2$s',
			sprintf(
				/* Translators: %1$s is the plugin name. */
				__( '%1$s has been deactivated to avoid conflicts with your site\'s page cache.', 'nexcess-mapps' ),
				$plugin_name
			),
			sprintf(
				/* Translators: %1$s is the plugin name, %2$s is the URL to the plugins page within WP Admin. */
				__( 'If you would prefer to continue using %1$s, you may <a href="%2$s">reactivate it</a> and it will be used instead.', 'nexcess-mapps' ),
				$plugin_name,
				admin_url( 'plugins.php' )
			)
		);

		( new AdminNotice( $message, 'info', true, 'page-cache-deactivated-' . $plugin ) )
			->setCapability( 'activate_plugins' )
			->persist();
	}

	/**
	 * The central hub for daily maintenance routines related to Cache Enabler and the bundled page
	 * cache solution.
	 *
	 * If a Cache Enabler variant IS being used:
	 *
	 * - Ensure the wp-content/cache/cache-enabler/ and wp-content/settings/cache-enabler/
	 *   directories both exist and are writable {@see setCacheDirectoryPermissions()}.
	 *
	 * If a Cache Enabler variant is NOT being used:
	 *
	 * - Make sure the corresponding rewrite rules have been removed from the Htaccess file
	 *   {@see removeCacheEnablerRewriteRules()}.
	 *
	 * REGARDLESS of Cache Enabler's status:
	 *
	 * - Clean up *empty* Cache Enabler comments in the wp-config.php file
	 *   {@see cleanCacheEnablerWpConfig()}.
	 */
	public function runPageCacheMaintenance() {
		if ( $this->isCacheEnablerActive() ) {
			$this->setCacheDirectoryPermissions();
		} else {
			/*
			 * If Cache Enabler is not being used, simply try to clean up artifacts that may have
			 * been leftover from previous installations.
			 */
			$this->removeCacheEnablerRewriteRules();
		}

		// Clean up empty Cache Enabler comments in the wp-config.php file.
		$this->cleanCacheEnablerWpConfig();
	}

	/**
	 * Determine whether or not we should be allowed to replace advanced-cache.php.
	 *
	 * To avoid overwriting an advanced page cache from another plugin, we'll explicitly look for
	 * instances of advanced-cache.php from Cache Enabler. If the file exists and doesn't meet our
	 * checks, we'll leave it in-place.
	 *
	 * @return bool True if advanced-cache.php either doesn't exist or appears to be from Cache
	 *              Enabler, false otherwise.
	 */
	protected function shouldReplaceDropIn() {
		$file = WP_CONTENT_DIR . '/advanced-cache.php';

		if ( ! file_exists( $file ) ) {
			return true;
		}

		try {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
			$contents = (string) file_get_contents( $file );
		} catch ( \Exception $e ) {
			return false;
		}

		return (bool) preg_match( '/cache[\s\-_]enabler/i', $contents );
	}

	/**
	 * Adds feature integration information to the telemetry report.
	 *
	 * @param array[] $report The gathered report data.
	 *
	 * @return array[] The $report array.
	 */
	public function addFeatureToTelemetry( array $report ) {
		$report['features'][ self::TELEMETRY_FEATURE_KEY ] = [
			self::TELEMETRY_FEATURE_ENABLED_KEY  => $this->isPageCacheEnabled(),
			self::TELEMETRY_FEATURE_IN_USE_KEY   => $this->isPageCacheEnabled() && $this->isHtaccessSectionValid(),
			self::TELEMETRY_FEATURE_PROVIDER_KEY => empty( $this->getActivePageCachePlugins() ) ? [ self::TELEMETRY_FEATURE_PROVIDER_DEFAULT ] : $this->getActivePageCachePlugins(),
		];

		return $report;
	}
}
