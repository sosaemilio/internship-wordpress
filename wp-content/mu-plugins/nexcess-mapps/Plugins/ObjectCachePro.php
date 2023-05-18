<?php

/**
 * Object Cache Pro.
 *
 * @link https://objectcache.pro/
 */

namespace Nexcess\MAPPS\Plugins;

use Nexcess\MAPPS\Concerns\HasCronEvents;
use Nexcess\MAPPS\Integrations\ObjectCache;
use Nexcess\MAPPS\Services\DropIn;
use Nexcess\MAPPS\Services\WPConfig;
use Nexcess\MAPPS\Settings;
use StellarWP\PluginFramework\Exceptions\WPConfigException;

class ObjectCachePro extends Plugin {
	use HasCronEvents;

	/**
	 * @var \Nexcess\MAPPS\Services\WPConfig
	 */
	protected $config;

	/**
	 * @var \Nexcess\MAPPS\Services\DropIn
	 */
	protected $dropIn;

	/**
	 * @var \Nexcess\MAPPS\Integrations\ObjectCache
	 */
	protected $objectCache;

	/**
	 * @var \Nexcess\MAPPS\Settings
	 */
	protected $settings;

	/**
	 * @var array
	 */
	protected $freeVersionConstants = [
		'WP_REDIS_HOST',
		'WP_REDIS_PORT',
		'WP_REDIS_PATH',
		'WP_REDIS_SCHEME',
		'WP_REDIS_DATABASE',
		'WP_REDIS_PREFIX',
		'WP_CACHE_KEY_SALT',
		'WP_REDIS_MAXTTL',
		'WP_REDIS_CLIENT',
		'WP_REDIS_TIMEOUT',
		'WP_REDIS_READ_TIMEOUT',
		'WP_REDIS_IGNORED_GROUPS',
		'WP_REDIS_RETRY_INTERVAL',
		'WP_REDIS_GLOBAL_GROUPS',
		'WP_REDIS_METRICS_MAX_TIME',
		'WP_REDIS_IGBINARY',
		'WP_REDIS_SERIALIZER',
		'WP_REDIS_DISABLED',
		'WP_REDIS_DISABLE_METRICS',
		'WP_REDIS_DISABLE_BANNERS',
		'WP_REDIS_DISABLE_DROPIN_AUTOUPDATE',
		'WP_REDIS_GRACEFUL',
		'WP_REDIS_SELECTIVE_FLUSH',
		'WP_REDIS_UNFLUSHABLE_GROUPS',
	];

	/**
	 * Set our cron-related constants.
	 */
	const CLEAN_CONSTANTS_CRON         = 'load_ocp_clean_constants';
	const CLEAN_CONSTANTS_CHECK_ACTION = 'Nexcess\\MAPPS\\Plugins\\ObjectCachePro\\CleanConstantsCron';

	/**
	 * Construct the plugin instance.
	 *
	 * @param \Nexcess\MAPPS\Settings                 $settings
	 * @param \Nexcess\MAPPS\Services\DropIn          $drop_in
	 * @param \Nexcess\MAPPS\Services\WPConfig        $config
	 * @param \Nexcess\MAPPS\Integrations\ObjectCache $object_cache
	 */
	public function __construct( Settings $settings, DropIn $drop_in, WPConfig $config, ObjectCache $object_cache ) {
		$this->settings    = $settings;
		$this->dropIn      = $drop_in;
		$this->config      = $config;
		$this->objectCache = $object_cache;
	}

	/**
	 * Actions to perform every time the plugin is loaded.
	 *
	 * Check for constants belonging to the free version of Redis.
	 * Set a cron to clean up any constants that should be removed.
	 */
	public function load() {

		// Bail if we already did this.
		if ( get_option( 'nexcess_load_ocp_cleaned_constants', false ) ) {
			return;
		}

		if ( $this->config->hasConstant( 'WP_REDIS_HOST' ) ) {
			$this->registerCronEvent(
				self::CLEAN_CONSTANTS_CRON,
				null,
				current_datetime()->add( new \DateInterval( 'PT1M' ) )
			)->scheduleEvents();
			add_action( self::CLEAN_CONSTANTS_CRON, [ $this, 'runCleanConstantsCron' ] );
		}
	}

	/**
	 * Runs the cleanup process to remove unnecessary constants.
	 *
	 * @return mixed
	 */
	public function runCleanConstantsCron() {
		// Make sure that we don't run this action multiple times in one request.
		if ( did_action( self::CLEAN_CONSTANTS_CHECK_ACTION ) ) {
			return;
		}

		// Fire the action to avoid a loop.
		do_action( self::CLEAN_CONSTANTS_CHECK_ACTION );

		// Remove the constants that are no longer needed.
		$count = 0;
		foreach ( $this->freeVersionConstants as $constant ) {
			if ( $this->config->hasConstant( $constant ) ) {
				$this->config->removeConstant( $constant );
				$count++;
			}
		}

		update_option( 'nexcess_load_ocp_cleaned_constants', true );
		return $count;
	}

	/**
	 * Actions to perform upon plugin activation.
	 *
	 * @param bool $network_wide Optional. Is the plugin being activated network-wide?
	 *                           Default is false.
	 */
	public function activate( $network_wide = false ) {
		if ( ! $this->settings->redis_host || ! $this->settings->redis_port ) {
			return;
		}

		// We have to force the install here, else this will fail if other drop-ins are installed at this point.
		if ( ! $this->dropIn->install( 'object-cache.php', $this->pluginDir . '/stubs/object-cache.php', true ) ) {
			return;
		}

		$this->writeConfig();
		wp_cache_flush();
	}

	/**
	 * Actions to perform upon plugin deactivation.
	 *
	 * @param bool $network_wide Optional. Is the plugin being deactivated network-wide?
	 *                           Default is false.
	 */
	public function deactivate( $network_wide = false ) {
		if ( $this->dropIn->remove( 'object-cache.php', $this->pluginDir . '/stubs/object-cache.php' ) ) {
			$this->objectCache->installObjectCacheDropIn();
		}

		$this->config->removeConstant( 'WP_REDIS_CONFIG' );
		$this->config->removeConstant( 'WP_REDIS_DISABLED' );
		wp_cache_flush();
	}

	/**
	 * Writes the Object Cache Pro configuration constants to the wp-config.php file.
	 *
	 * @return bool True when the config is successfully written to, false otherwise.
	 */
	public function writeConfig() {
		try {
			$this->config->setConfig( 'constant', 'WP_REDIS_CONFIG', $this->getRedisConfig(), [
				'raw' => true,
			] );
			$this->config->setConstant( 'WP_REDIS_DISABLED', false );
		} catch ( WPConfigException $e ) {
			return false;
		}

		return true;
	}

	/**
	 * Generate the configuration needed for Object Cache Pro.
	 *
	 * @return string Configuration as a string
	 */
	public function getRedisConfig() {
		$license    = get_option( 'object_cache_pro_license', '' );
		$redis_host = $this->settings->redis_host;
		$redis_port = $this->settings->redis_port;

		// Use the socket if it is available, otherwise continue with the default IP:port.
		if ( file_exists( $this->settings->redis_socket ) && is_readable( $this->settings->redis_socket ) ) {
			$redis_host = $this->settings->redis_socket;
			$redis_port = '0';
		}

		$config_array = [
			'token'            => "'{$license}'",
			'host'             => "'{$redis_host}'",
			'port'             => "'{$redis_port}'",
			'database'         => "'0'",
			'maxttl'           => '86400 * 7',
			'timeout'          => '1.0',
			'read_timeout'     => '1.0',
			'retry_interval'   => 10,
			'retries'          => 3,
			'backoff'          => "'smart'",
			'compression'      => "'zstd'",
			'serializer'       => "'igbinary'",
			'async_flush'      => 'true',
			'split_alloptions' => 'true',
			'prefetch'         => 'true',
			'debug'            => 'false',
			'save_commands'    => 'false',
		];

		$array_string = "[\n";
		foreach ( $config_array as $key => $value ) {
			$array_string .= "\t'{$key}' => {$value},\n";
		}
		$array_string .= ']';

		return $array_string;
	}
}
