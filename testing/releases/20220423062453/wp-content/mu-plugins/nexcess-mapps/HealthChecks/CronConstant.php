<?php

/**
 * Verify that the DISABLE_WP_CRON constant is set.
 */

namespace Nexcess\MAPPS\HealthChecks;

use Nexcess\MAPPS\Support\AdminNotice;
use Nexcess\MAPPS\Support\Branding;

class CronConstant extends HealthCheck {

	/**
	 * The health check's ID.
	 *
	 * @var string
	 */
	protected $id = 'mapps_wp_cron_constant';

	/**
	 * Construct a new instance of the health check.
	 */
	public function __construct() {
		$this->label       = _x( 'WordPress is using the system cron', 'Site Health check', 'nexcess-mapps' );
		$this->description = __( 'Using the system cron improves both performance and the reliability of scheduled events.', 'nexcess-mapps' );
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
		if ( defined( 'DISABLE_WP_CRON' ) ) {
			if ( ! DISABLE_WP_CRON ) {
				$this->label        = _x( 'WordPress is explicitly using its default cron', 'Site Health check', 'nexcess-mapps' );
				$this->description .= PHP_EOL . PHP_EOL;
				$this->description .= __( 'The <code>DISABLE_WP_CRON</code> constant is defined as "false" in your wp-config.php file, meaning WordPress will use its default, request-based event scheduling rather than the system cron.', 'nexcess-mapps' );
				$this->description .= PHP_EOL . PHP_EOL;
				$this->description .= __( 'If you would like to disable WP-Cron and use the system cron scheduler, you may disable WP-Cron by updating your wp-config.php file manually or clicking below:', 'nexcess-mapps' );
				$this->description .= PHP_EOL . $this->getCronNotice();
				$this->actions      = sprintf(
					'<form method="post"><button name="action" value="mapps_disable_wp_cron" type="submit" class="button">%1$s</button>%2$s</form>',
					_x( 'Update my wp-config.php file to disable WP-Cron', 'Site Health action', 'nexcess-mapps' ),
					wp_nonce_field( 'mapps_add_disable_wp_cron_constant', '_wpnonce', true, false )
				);
			}

			return true;
		}

		$this->label        = _x( 'You should disable WP-Cron', 'Site Health check', 'nexcess-mapps' );
		$this->description .= PHP_EOL . PHP_EOL;
		$this->description .= __( 'By default, <a href="https://developer.wordpress.org/plugins/cron/">WordPress uses its own event scheduling</a> instead of a proper system cron: when the site receives traffic, it spawns a background process that runs any actions that are due to be run.', 'nexcess-mapps' );
		$this->description .= PHP_EOL . PHP_EOL;
		$this->description .= __( 'However, this method is completely dependent upon a regular stream of visitors and can be disrupted by full-page caching solutions that bypass WordPress. Furthermore, it tends to slow down requests to your site as it\'s constantly checking to see if the cron process needs to be spawned.', 'nexcess-mapps' );
		$this->description .= PHP_EOL . PHP_EOL;
		$this->description .= sprintf(
			/* Translators: %1$s is the branded platform name. */
			__( 'For the best performance and most reliable scheduling, we recommend disabling the default WP-Cron engine and using the system cron that is already configured as part of the %1$s platform.', 'nexcess-mapps' ),
			Branding::getCompanyName()
		);
		$this->description .= PHP_EOL . $this->getCronNotice();

		$this->actions = sprintf(
			'<form method="post"><button name="action" value="mapps_disable_wp_cron" type="submit" class="button">%1$s</button>%2$s</form>',
			_x( 'Update my wp-config.php file to disable WP-Cron', 'Site Health action', 'nexcess-mapps' ),
			wp_nonce_field( 'mapps_add_disable_wp_cron_constant', '_wpnonce', true, false )
		);

		return false;
	}

	/**
	 * Get an AdminNotice with instructions on re-adding the cron job if it's missing.
	 *
	 * @return AdminNotice
	 */
	protected function getCronNotice() {
		$message  = __( 'Note that the system cron job must be present or event scheduling won\'t work. The cron job should have been created by default, but if you had previously removed it <a href="https://help.nexcess.net/client-portal/67-how-to-schedule-cron-jobs-in-nexcess-cloud">you\'ll need to re-add it</a>.', 'nexcess-mapps' );
		$message .= sprintf( '<pre style="overflow: auto;"><code>*/5 * * * * /usr/sbin/relax php -f %swp-cron.php</code></pre>', ABSPATH );

		return ( new AdminNotice( $message, 'info', false, 'system-cron-must-be-defined' ) )
			->setInline( true )
			->setAlt( true );
	}
}
