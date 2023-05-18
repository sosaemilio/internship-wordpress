<?php

/**
 * Quickly provision new sites with WP QuickStart.
 */

namespace Nexcess\MAPPS\Integrations;

use Nexcess\MAPPS\Concerns\HasAdminPages;
use Nexcess\MAPPS\Concerns\HasHooks;
use Nexcess\MAPPS\Concerns\MakesHttpRequests;
use Nexcess\MAPPS\Concerns\ManagesGroupedOptions;
use Nexcess\MAPPS\Services\Managers\DashboardWidgetManager;
use Nexcess\MAPPS\Settings;
use Nexcess\MAPPS\Support\Helpers;
use Nexcess\MAPPS\Widgets\DashboardWidget;
use StellarWP\PluginFramework\Exceptions\SignatureVerificationFailedException;

class QuickStart extends Integration {
	use HasAdminPages;
	use HasHooks;
	use MakesHttpRequests;
	use ManagesGroupedOptions;

	/**
	 * @var \Nexcess\MAPPS\Settings
	 */
	protected $settings;

	/**
	 * @var \Nexcess\MAPPS\Services\Managers\DashboardWidgetManager
	 */
	protected $widgetManager;

	/**
	 * The option name for QuickStart settings.
	 */
	const OPTION_NAME = '_nexcess_quickstart';

	/**
	 * Construct the integration.
	 *
	 * @param \Nexcess\MAPPS\Settings                                 $settings
	 * @param \Nexcess\MAPPS\Services\Managers\DashboardWidgetManager $widget_manager
	 */
	public function __construct( Settings $settings, DashboardWidgetManager $widget_manager ) {
		$this->settings      = $settings;
		$this->widgetManager = $widget_manager;
	}

	/**
	 * Perform any necessary setup for the integration.
	 *
	 * This method is automatically called as part of Plugin::registerIntegration(), and is the
	 * entry-point for all integrations.
	 */
	public function setup() {
		$this->addHooks();

		// Remove the default WordPress welcome panel.
		remove_action( 'welcome_panel', 'wp_welcome_panel' );
	}

	/**
	 * Determine whether or not this integration should be loaded.
	 *
	 * @return bool Whether or not this integration be loaded in this environment.
	 */
	public function shouldLoadIntegration() {
		return $this->settings->is_quickstart
				&& ! $this->settings->is_storebuilder;
	}

	/**
	 * Retrieve all actions for the integration.
	 *
	 * @return array[]
	 */
	protected function getActions() {
		return [
			[ 'welcome_panel', [ $this, 'renderWelcomePanel' ], -9999 ],
		];
	}

	/**
	 * Retrieve all filters for the integration.
	 *
	 * @return array[]
	 */
	protected function getFilters() {
		// phpcs:disable WordPress.Arrays
		return [
			[ 'default_hidden_meta_boxes',                [ $this, 'filterDashboardMetaboxes'  ] ],
			[ 'get_user_option_meta-box-order_dashboard', [ $this, 'filterDefaultMetaboxOrder' ] ],
			[ 'admin_body_class',                         [ $this, 'addBodyClass'              ] ],
			[ 'wp_dashboard_setup',                       [ $this, 'registerDashboardWidgets'  ] ],
		];
		// phpcs:enable WordPress.Arrays
	}

	/**
	 * Adds a body class in the admin which can be used to scope styles to only the quickstart feature set.
	 *
	 * @param string $classes The current admin classes string.
	 *
	 * @return string The updated admin classes string with nx-quickstart added.
	 */
	public function addBodyClass( $classes ) {
		$classes .= ' nx-quickstart ';
		return $classes;
	}

	/**
	 * Render the welcome panel on the WP Admin dashboard.
	 */
	public function renderWelcomePanel() {
		$dashboard = $this->getOption()->dashboard;
		$welcome   = isset( $dashboard['welcome'] ) ? $dashboard['welcome'] : [];

		$this->renderTemplate( 'widgets/quickstart-welcome', [
			'title'   => ! empty( $welcome['title'] ) ? $welcome['title'] : __( 'Welcome to your new WP QuickStart site!', 'nexcess-mapps' ),
			'columns' => ! empty( $welcome['columns'] ) ? (array) $welcome['columns'] : [],
		] );
	}

	/**
	 * Filter the default dashboard meta boxes shown to users.
	 *
	 * Once a user shows or hides a meta box, their selections are saved and will be used for
	 * subsequent page loads.
	 *
	 * @param string[] $meta_boxes An array of meta box keys that should be hidden by default.
	 *
	 * @return string[] The filtered $meta_boxes array.
	 */
	public function filterDashboardMetaboxes( array $meta_boxes ) {
		$option = $this->getOption()->dashboard;

		return ! empty( $option['hidden'] )
			? array_unique( array_merge( $meta_boxes, (array) $option['hidden'] ) )
			: $meta_boxes;
	}

	/**
	 * Register custom dashboard widgets.
	 */
	public function registerDashboardWidgets() {
		$option = $this->getOption()->dashboard;

		// No widgets to define.
		if ( empty( $option['link_widgets'] ) ) {
			return;
		}

		foreach ( (array) $option['link_widgets'] as $widget ) {
			$this->widgetManager->addWidget(
				new DashboardWidget(
					$widget['id'],
					$widget['name'],
					'widgets/icon-links',
					[ 'icon_links' => $widget['links'] ]
				)
			);
		}

		$this->widgetManager->registerWidgets();
	}

	/**
	 * Filter the default ordering of dashboard meta boxes unless the user has set their own.
	 *
	 * @param string[] $order A user-defined ordering, in the form of "column_name:id1,id2".
	 *
	 * @return string[] Either the $order array or an array of reasonable defaults if $order is empty.
	 */
	public function filterDefaultMetaboxOrder( $order ) {
		if ( ! empty( $order ) ) {
			return $order;
		}

		$option = $this->getOption()->dashboard;

		return empty( $option['order'] ) ? [] : array_map( function ( $widgets ) {
			// Flatten the order array into a comma-separated string.
			return implode( ',', (array) $widgets );
		}, (array) $option['order'] );
	}

	/**
	 * Retrieve site details from the WP QuickStart SaaS.
	 *
	 * @param string $site_id Optional. The QuickStart site UUID. Defaults to the value
	 *                        $this->settings->quickstart_site_id.
	 *
	 * @throws \StellarWP\PluginFramework\Exceptions\WPErrorException If the HTTP request returns a non-200 status.
	 *
	 * @return mixed[] The JSON response, decoded as an array.
	 */
	public function getSiteDetails( $site_id = '' ) {
		$site_id  = $site_id ?: $this->settings->quickstart_site_id;
		$url      = sprintf( '%s/api/sites/%s/config', $this->settings->quickstart_app_url, $site_id );
		$response = wp_remote_get( $url, [
			'headers' => [
				'Accept' => 'application/json',
			],
		] );

		$body      = $this->validateHttpResponse( $response, 200 );
		$signature = wp_remote_retrieve_header( $response, 'X-QuickStart-Signature' );

		if ( is_array( $signature ) ) {
			$signature = (string) current( $signature );
		}

		$this->verifySignedResponse( $signature, $body );

		$decoded = json_decode( $body, true );

		return ! empty( $decoded['data'] ) ? $decoded['data'] : [];
	}

	/**
	 * Validates the signature on a QuickStart HTTP response.
	 *
	 * @param string $signature The signature over the HTTP body.
	 * @param string $body      The raw HTTP body bytes which were signed.
	 *
	 * @throws \StellarWP\PluginFramework\Exceptions\SignatureVerificationFailedException If the the signature is missing or can not be verified.
	 */
	public function verifySignedResponse( $signature, $body ) {
		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode
		$pubkey = base64_decode( $this->settings->quickstart_public_key );

		// Verify the response against the X-QuickStart-Signature signature.
		if ( ! $signature ) {
			throw new SignatureVerificationFailedException( 'The site details response was missing the X-Quickstart-Signature header.' );
		}

		if ( ! function_exists( 'sodium_crypto_sign_verify_detached' ) ) {
			throw new SignatureVerificationFailedException( 'Unable to verify site details: verify method missing. Are you running at least PHP 7.2?' );
		}

		if ( ! sodium_crypto_sign_verify_detached( Helpers::base64_urldecode( $signature ), $body, $pubkey ) ) {
			throw new SignatureVerificationFailedException( 'The site details did not match the response signature.' );
		}
	}
}
