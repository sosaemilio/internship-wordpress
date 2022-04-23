<?php

/**
 * Responsible for managing custom plugin configurations.
 */

namespace Nexcess\MAPPS\Services\Managers;

use Nexcess\MAPPS\Container;
use Nexcess\MAPPS\Exceptions\InvalidPluginException;
use Nexcess\MAPPS\Plugins;
use Nexcess\MAPPS\Plugins\Plugin;

class PluginConfigManager {

	/**
	 * The DI container, used to resolve plugin classes.
	 *
	 * @var \Nexcess\MAPPS\Container
	 */
	protected $container;

	/**
	 * An array of registered plugin objects.
	 *
	 * The key corresponds to the plugin basename, while the value represents the corresponding
	 * Plugin class.
	 *
	 * @var Array<string,string>
	 */
	protected $plugins = [
		'redis-cache/redis-cache.php' => Plugins\RedisCache::class,
		'wp-redis/wp-redis.php'       => Plugins\WPRedis::class,
	];

	/**
	 * A cache of resolved instances with paths.
	 *
	 * @var Array<string,Plugin>
	 */
	protected $resolved = [];

	/**
	 * Construct the manager instance.
	 *
	 * @param \Nexcess\MAPPS\Container $container The DI container.
	 */
	public function __construct( Container $container ) {
		$this->container = $container;
	}

	/**
	 * Return all registered plugins.
	 *
	 * @return Array<string,string> An array consisting of Plugin definitions.
	 */
	public function all() {
		return $this->plugins;
	}

	/**
	 * Resolve the given plugin through the DI container.
	 *
	 * @param string $plugin The plugin basename.
	 *
	 * @throws \Nexcess\MAPPS\Exceptions\InvalidPluginException If the plugin is not registered.
	 *
	 * @return Plugin
	 */
	public function get( $plugin ) {
		if ( isset( $this->resolved[ $plugin ] ) ) {
			return $this->resolved[ $plugin ];
		}

		if ( ! isset( $this->plugins[ $plugin ] ) ) {
			throw new InvalidPluginException( sprintf( 'No plugin configuration exists for %s.', $plugin ) );
		}

		$this->resolved[ $plugin ] = $this->container->get( $this->plugins[ $plugin ] )
			->setPluginDir( $this->getPluginPath( $plugin ) );

		return $this->resolved[ $plugin ];
	}

	/**
	 * Determine whether or not we have a definition for the given plugin basename.
	 *
	 * @param string $plugin The plugin to check.
	 *
	 * @return bool True if we have a definition, false otherwise.
	 */
	public function has( $plugin ) {
		if ( ! (string) $plugin ) {
			return false;
		}

		return isset( $this->plugins[ (string) $plugin ] );
	}

	/**
	 * Resolve the given plugin through the DI container.
	 *
	 * Unlike get(), this method will not cache its result, making it suitable for resolving for
	 * one-time actions, such as deactivate().
	 *
	 * @param string $plugin The plugin basename.
	 *
	 * @throws \Nexcess\MAPPS\Exceptions\InvalidPluginException If the plugin is not registered.
	 *
	 * @return Plugin
	 */
	public function make( $plugin ) {
		if ( ! isset( $this->plugins[ $plugin ] ) ) {
			throw new InvalidPluginException( sprintf( 'No plugin configuration exists for %s.', $plugin ) );
		}

		return $this->container->make( $this->plugins[ $plugin ] )
			->setPluginDir( $this->getPluginPath( $plugin ) );
	}

	/**
	 * Retrieve an array of resolved Plugin classes.
	 *
	 * This corresponds to any plugins that have been resolved via the manager's get() method.
	 *
	 * @param string $class Optional. Filter results to those of a specific class. Default is empty,
	 *                      meaning all resolved instances will be returned.
	 *
	 * @return Array<string,Plugin>
	 */
	public function resolved( $class = '' ) {
		return empty( $class )
			? $this->resolved
			: array_filter( $this->resolved, function ( $instance ) use ( $class ) {
				return $instance instanceof $class;
			} );
	}

	/**
	 * Get the absolute system path to the given $plugin directory.
	 *
	 * @param string $plugin The plugin string to parse.
	 *
	 * @return string The system path to that plugin directory, without a trailing slash.
	 */
	protected function getPluginPath( $plugin ) {
		$dir = dirname( $plugin );

		if ( '.' === $dir ) {
			$dir = '';
		}

		return untrailingslashit( WP_PLUGIN_DIR . '/' . $dir );
	}
}
