<?php

namespace Nexcess\MAPPS\Support;

use Nexcess\MAPPS\Concerns\HasWordPressDependencies;
use StellarWP\PluginFramework\Support\Branding;

class PlatformRequirements {
	use HasWordPressDependencies;

	/**
	 * The minimum supported WordPress version.
	 *
	 * We officially and actively support the latest and previous major release of WordPress, but
	 * this is the absolute minimum version; anything lower will prevent the plugin from loading.
	 */
	const MINIMUM_WP_VERSION = '5.0';

	/**
	 * The WordPress version that we begin deprecating.
	 *
	 * This constant will be used when we have upcoming changes that will impact customers running
	 * on a lower version of WordPress, however, the current features would not be impacted.
	 */
	const DEPRECATED_WP_VERSION = '5.2'; // HEY, LISTEN! Make sure to update the date in getDeprecatedWordPressNotice() when this gets updated.

	/**
	 * Verify that the site meets the minimum requirements for the full MAPPS experience.
	 *
	 * @return bool Returns true if all dependencies are met, false otherwise.
	 */
	public function siteMeetsMinimumRequirements() {
		return $this->currentWordPressVersionIs( '>=', self::MINIMUM_WP_VERSION );
	}

	/**
	 * Check if the site is running on a version of WordPress that is deprecated.
	 *
	 * @return bool Returns true if the site is running on a deprecated version of WordPress.
	 */
	public function siteMeetsDeprecatedRequirements() {
		return $this->currentWordPressVersionIs( '>', self::DEPRECATED_WP_VERSION );
	}

	/**
	 * Get the message to display to the user when the site does not meet the minimum requirements.
	 *
	 * @return string The message to display to the user.
	 */
	public function getDeprecatedWordPressNotice() {
		return sprintf(
			'<strong>%1$s</strong><p>%2$s>',
			__( 'Your site is currently running an outdated version of WordPress!', 'nexcess-mapps' ),
			sprintf(
				/* translators: %1$s: start of strong tag, %2$s: end of strong tag, %3$s: start of link tag, %4$s: end of link tag, %5$s: platform name, such as "Nexcess MAPPS platform". */
				__( 'On %1$sJanuary 3, 2022%2$s, the version of WordPress you are on will no longer be supported. <p>We recommend %3$supgrading WordPress%4$s as soon as possible to get the most out of the %5$s.', 'nexcess-mapps' ),
				'<strong>',
				'</strong>',
				'<a href="' . admin_url( 'update-core.php' ) . '">',
				'</a>',
				Branding::getPlatformName()
			)
		);
	}

	/**
	 * Get the message to display to the user when the site does not meet the minimum requirements.
	 *
	 * @return string The message to display to the user.
	 */
	public function getUnsupportedWordPressNotice() {
		return sprintf(
			'<strong>%1$s</strong><p>%2$s>',
			__( 'Your site is currently running an outdated version of WordPress!', 'nexcess-mapps' ),
			sprintf(
				/* translators: %1$s is start of link tag, %2$s is the closing link tag, %3$s is the platform name, such as Nexcess MAPPS platform. */
				__( 'We recommend %1$supgrading WordPress%2$s as soon as possible to get the most out of the %3$s.', 'nexcess-mapps' ),
				'<a href="' . admin_url( 'update-core.php' ) . '">',
				'</a>',
				Branding::getPlatformName()
			)
		);
	}

	/**
	 * Display an admin notice if the site is running an unsupported version of WordPress.
	 *
	 * We do this here, rather than inside an integration, like for the deprecated
	 * version, because we want this to display, and if the minimum requirements
	 * are not met, we don't load the plugin at all.
	 */
	public function renderUnsupportedWordPressVersionNotice() {
		if ( ! is_admin() ) {
			return;
		}

		$notice = new AdminNotice( $this->getUnsupportedWordPressNotice(), 'error', false, 'unsupported-wp-version' );

		$notice->setCapability( 'update_core' );

		add_action( 'admin_notices', [ $notice, 'output' ] );
	}
}
