<?php

/**
 * Collect feedback from customers.
 */

namespace Nexcess\MAPPS\Integrations;

use Nexcess\MAPPS\Concerns\HasAdminPages;
use Nexcess\MAPPS\Concerns\HasHooks;
use Nexcess\MAPPS\Settings;

class Feedback extends Integration {
	use HasAdminPages;
	use HasHooks;

	/**
	 * @var \Nexcess\MAPPS\Settings
	 */
	protected $settings;

	/**
	 * @param \Nexcess\MAPPS\Settings $settings
	 */
	public function __construct( Settings $settings ) {
		$this->settings = $settings;
	}

	/**
	 * Determine whether or not this integration should be loaded.
	 *
	 * @return bool Whether or not this integration be loaded in this environment.
	 */
	public function shouldLoadIntegration() {
		return (bool) $this->settings->canny_board_token
			&& apply_filters( 'nexcess_mapps_branding_enable_feedback_template', true );
	}

	/**
	 * Retrieve all actions for the integration.
	 *
	 * @return array[]
	 */
	protected function getActions() {
		return [
			[ 'admin_init', [ $this, 'registerFeedbackPage' ], 1200 ], // Feedback tabs start at 1000, so this is the second one.
		];
	}

	/**
	 * Register the feedback page.
	 */
	public function registerFeedbackPage() {
		add_settings_section(
			'feedback',
			_x( 'Beta Feedback', 'settings section', 'nexcess-mapps' ),
			function () {
				$this->renderTemplate( 'feedback', [
					'settings' => $this->settings,
				] );
			},
			Dashboard::ADMIN_MENU_SLUG
		);
	}
}
