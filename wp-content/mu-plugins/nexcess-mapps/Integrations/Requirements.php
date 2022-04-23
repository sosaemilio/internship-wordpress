<?php

/**
 * WordPress / PHP version requirements.
 */

namespace Nexcess\MAPPS\Integrations;

use Nexcess\MAPPS\Concerns\HasHooks;
use Nexcess\MAPPS\Concerns\HasWordPressDependencies;
use Nexcess\MAPPS\Services\AdminBar;
use Nexcess\MAPPS\Settings;
use Nexcess\MAPPS\Support\AdminNotice;
use Nexcess\MAPPS\Support\PHPVersions;
use Nexcess\MAPPS\Support\PlatformRequirements;

class Requirements extends Integration {
	use HasWordPressDependencies;
	use HasHooks;

	/**
	 * @var \Nexcess\MAPPS\Services\AdminBar
	 */
	protected $adminBar;

	/**
	 * @var \Nexcess\MAPPS\Settings
	 */
	protected $settings;

	/**
	 * @var \Nexcess\MAPPS\Support\PlatformRequirements
	 */
	protected $platformRequirements;

	/**
	 * @param \Nexcess\MAPPS\Settings                     $settings
	 * @param \Nexcess\MAPPS\Services\AdminBar            $admin_bar
	 * @param \Nexcess\MAPPS\Support\PlatformRequirements $platform_requirements
	 */
	public function __construct( Settings $settings, AdminBar $admin_bar, PlatformRequirements $platform_requirements ) {
		$this->settings             = $settings;
		$this->adminBar             = $admin_bar;
		$this->platformRequirements = $platform_requirements;
	}

	/**
	 * Retrieve all actions for the integration.
	 *
	 * @return array[]
	 */
	protected function getActions() {
		// phpcs:disable WordPress.Arrays
		return [
			[ 'admin_init', [ $this, 'checkPHPVersion'       ], 1  ],
			[ 'admin_init', [ $this, 'checkWordPressVersion' ], 2  ],
		];
		// phpcs:enable WordPress.Arrays
	}

	/**
	 * Check to see if the current PHP version is supported.
	 *
	 * If the site is running a version of PHP that has reached end-of-life (EOL), a notice should
	 * be displayed to administrators.
	 *
	 * @link https://www.php.net/supported-versions.php
	 * @link https://www.php.net/eol.php
	 */
	public function checkPHPVersion() {
		if ( ! current_user_can( 'manage_options' ) || ! PHPVersions::hasReachedEOL( $this->settings->php_version ) ) {
			return;
		}

		$notice = sprintf(
			/* Translators: %1$s is the site's current PHP version, %2$s is its EOL date, %3$s is the kb URL. */
			__(
				'<p><strong>Your site is currently running on an out-of-date version of PHP!</strong></p>
				<p>PHP is the underlying programming language that WordPress and its themes/plugins are written in. Newer releases bring more features, better performance, and regular security fixes.</p>
				<p>Your site is currently running on PHP <strong>%1$s</strong>, which stopped receiving security updates on %2$s!</p>
				<p>For improved performance and security, we recommend <a href="%3$s">upgrading your site\'s PHP version</a> at your earliest convenience.</p>
				',
				'nexcess-mapps'
			),
			$this->settings->php_version,
			PHPVersions::getEOLDate( $this->settings->php_version )->format( get_option( 'date_format', 'F j, Y' ) ),
			'https://help.nexcess.net/74095-wordpress/upgrading-your-php-installation-in-managed-wordpress-and-managed-woocommerce-hosting'
		);

		$this->adminBar->addNotice( new AdminNotice( $notice, 'warning', false ), 'php-version' );
	}

	/**
	 * Check to see if the current WordPress version is supported.
	 *
	 * If the site is running a version of WordPress that has been deprecated, a notice should
	 * be displayed to administrators.
	 */
	public function checkWordPressVersion() {
		if ( ! current_user_can( 'update_core' ) ) {
			return;
		}

		if ( $this->platformRequirements->siteMeetsDeprecatedRequirements() ) {
			return;
		}

		$this->adminBar->addNotice( new AdminNotice( $this->platformRequirements->getDeprecatedWordPressNotice(), 'warning', false, 'wordpress-version' ), 'wordpress-version' );
	}
}
