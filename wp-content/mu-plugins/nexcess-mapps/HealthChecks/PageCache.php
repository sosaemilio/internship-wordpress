<?php

/**
 * Determine whether or not a site is running a full-page cache.
 */

namespace Nexcess\MAPPS\HealthChecks;

use StellarWP\PluginFramework\Support\Branding;

class PageCache extends HealthCheck {

	/**
	 * The health check's ID.
	 *
	 * @var string
	 */
	protected $id = 'mapps_page_cache';

	/**
	 * Construct a new instance of the health check.
	 */
	public function __construct() {
		$this->label       = _x( 'Page caching is enabled', 'Site Health check', 'nexcess-mapps' );
		$this->description = __( 'Full-page caching can dramatically improve your site performance by serving cached versions of pages to visitors.', 'nexcess-mapps' );
		$this->badgeLabel  = _x( 'Performance', 'Site Health category', 'nexcess-mapps' );
	}

	/**
	 * Perform the site health check.
	 *
	 * This method is responsible for running the check and updating object properties accordingly.
	 *
	 * @return bool True if the check passes, false if it fails.
	 */
	public function performCheck() {
		$settings_page = admin_url( 'admin.php?page=mapps-page-cache' );

		if ( defined( 'WP_CACHE' ) && WP_CACHE ) {
			if ( file_exists( WP_CONTENT_DIR . '/advanced-cache.php' ) ) {
				return true;
			}

			$this->label        = _x( 'Full-page caching is not fully enabled', 'Site Health check', 'nexcess-mapps' );
			$this->description .= PHP_EOL . PHP_EOL;
			$this->description .= __( '<code>WP_CACHE</code> is defined in your wp-config.php file, but no advanced-cache.php drop-in was found.', 'nexcess-mapps' );
			$this->description .= PHP_EOL . PHP_EOL;
			$this->description .= sprintf(
				/* Translators: %1$s is the branded company name, %2$s is the Nexcess > Page Cache URL. */
				__( 'If you\'re already using a full-page caching solution, it may require additional configuration. Otherwise, %1$s has <a href="%2$s">a built-in page cache solution</a>.', 'nexcess-mapps' ),
				Branding::getCompanyName(),
				$settings_page
			);
			$this->description .= PHP_EOL . PHP_EOL;
			$this->description .= __( 'If you do not wish to use full-page caching, you should remove <code>WP_CACHE</code> from your wp-config.php file.', 'nexcess-mapps' );
		} else {
			$this->label        = _x( 'You should enable full-page caching', 'Site Health check', 'nexcess-mapps' );
			$this->description .= PHP_EOL . PHP_EOL;
			$this->description .= sprintf(
				/* Translators: %1$s is the branded company name, %2$s is the Nexcess > Page Cache URL. */
				__( '%1$s has <a href="%2$s">a built-in page cache solution</a>, but you might also consider a premium plugin like <a href="https://wp-rocket.me/">WP Rocket</a>.', 'nexcess-mapps' ),
				Branding::getCompanyName(),
				$settings_page
			);
		}

		$this->actions = sprintf(
			'<a href="%1$s">%2$s</a>',
			$settings_page,
			_x( 'Configure and activate full-page caching', 'Site Health action', 'nexcess-mapps' )
		);

		return false;
	}
}
