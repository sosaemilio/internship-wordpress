<?php

namespace Nexcess\MAPPS\Commands;

use Nexcess\MAPPS\Services\WPConfig;
use StellarWP\PluginFramework\Exceptions\WPConfigException;

/**
 * WP-CLI sub-commands for modifying a site's wp-config.php file.
 */
class Config extends Command {

	/**
	 * @var WPConfig
	 */
	protected $config;

	/**
	 * Create a new command instance.
	 *
	 * @param WPConfig $config
	 */
	public function __construct( WPConfig $config ) {
		$this->config = $config;
	}

	/**
	 * Regenerate the WP_CACHE_KEY_SALT constant.
	 *
	 * ## EXAMPLES
	 *
	 * $ wp nxmapps config regenerate-cache-key
	 * Success: WP_CACHE_KEY_SALT regenerated.
	 *
	 * @subcommand regenerate-cache-key
	 */
	public function regenerate_cache_key() {
		$salt = wp_generate_password( 64, true, true );

		try {
			$this->config->setConstant( 'WP_CACHE_KEY_SALT', $salt );
		} catch ( WPConfigException $e ) {
			$this->error( 'Unable to update WP_CACHE_KEY_SALT: ' . $e->getMessage(), 1 );
		}

		$this->success( 'The WP_CACHE_KEY_SALT constant has been rotated.' );
	}
}
