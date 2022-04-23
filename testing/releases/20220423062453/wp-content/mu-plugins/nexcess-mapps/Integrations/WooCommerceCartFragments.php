<?php

/**
 * WooCommerce Cart Fragments.
 */

namespace Nexcess\MAPPS\Integrations;

use Nexcess\MAPPS\Concerns\HasAdminPages;
use Nexcess\MAPPS\Concerns\HasHooks;
use Nexcess\MAPPS\Concerns\HasWordPressDependencies;
use Nexcess\MAPPS\Concerns\ManagesGroupedOptions;
use Nexcess\MAPPS\HealthChecks\WooCommerceCartFragments as WooCommerceCartFragmentsHealthCheck;
use Nexcess\MAPPS\Services\Managers\SiteHealthManager;
use Nexcess\MAPPS\Services\Options;
use Nexcess\MAPPS\Settings;
use Nexcess\MAPPS\Support\Branding;

class WooCommerceCartFragments extends Integration {
	use HasAdminPages;
	use HasHooks;
	use HasWordPressDependencies;
	use ManagesGroupedOptions;

	/**
	 * @var \Nexcess\MAPPS\Settings
	 */
	protected $settings;

	/**
	 * @var \Nexcess\MAPPS\Services\Managers\SiteHealthManager
	 */
	protected $siteHealthManager;

	/**
	 * @var \Nexcess\MAPPS\Services\Options
	 */
	protected $options;

	/**
	 * List of common plugins used to disable Woo cart fragments.
	 *
	 * @var array
	 */
	public $cartFragmentPlugins = [
		'ajax-cart-autoupdate-for-woocommerce/ajax-cart-autoupdate-for-woocommerce.php',
		'disable-cart-fragments/disable-cart-fragments.php',
		'wpc-ajax-add-to-cart/wpc-ajax-add-to-cart.php',
	];

	/**
	 * List of common plugins that include the ability to disable Woo cart fragments, but
	 * includes other features that are not included in our integration. We still want to check
	 * for these in the HealthChecks, but account for them in the plug-in notifications.
	 *
	 * @var array
	 */
	public $cartPlugins = [
		'mini-ajax-woo-cart/mini-ajax-cart.php',
		'woo-fly-cart/wpc-fly-cart.php',
		'wp-menu-cart/wp-menu-cart.php',
		'woocommerce-menu-bar-cart/wp-menu-cart.php',
	];

	/**
	 * The option for disabling cart fragments.
	 */
	const OPTION_NAME = 'nexcess_mapps_woocommerce';

	/**
	 * @param \Nexcess\MAPPS\Settings                            $settings
	 * @param \Nexcess\MAPPS\Services\Managers\SiteHealthManager $site_health_manager
	 * @param \Nexcess\MAPPS\Services\Options                    $options
	 */
	public function __construct( Settings $settings, SiteHealthManager $site_health_manager, Options $options ) {
		$this->settings          = $settings;
		$this->siteHealthManager = $site_health_manager;
		$this->options           = $options;
	}

	/**
	 * Determine whether or not this integration should be loaded.
	 *
	 * @return bool Whether or not this integration be loaded in this environment.
	 */
	public function shouldLoadIntegration() {
		return $this->isPluginActive( 'woocommerce/woocommerce.php' );
	}

	/**
	 * Set up the integration.
	 */
	public function setup() {
		$this->siteHealthManager->addCheck( WooCommerceCartFragmentsHealthCheck::class, false );

		$this->addHooks();

		$this->registerOption();
	}

	/**
	 * Retrieve all actions for the integration.
	 *
	 * @return array[]
	 */
	protected function getActions() {
		$base = [
			[ 'wp_enqueue_scripts', [ $this, 'dequeueCartFragments' ], 20 ],
		];

		$actions = [];
		foreach ( $this->cartFragmentPlugins as $plugin ) {
			$actions[] = [ 'after_plugin_row_' . $plugin, [ $this, 'renderCartFragmentNotice' ], 10, 2 ];
		}

		return array_merge( $base, $actions );
	}

	/**
	 * Enable cart fragments.
	 *
	 * These booleans are backwards from what you'd expect, but that is because
	 * we want the option to be 'status' to prevent confusion, but we also want
	 * the option page to be 'turn ON this setting to turn OFF cart fragments'.
	 */
	public function enableCartFragments() {
		$this->getOption()->set( 'cart_fragments_status', false )->save();
	}

	/**
	 * Disable cart fragments.
	 */
	public function disableCartFragments() {
		$this->getOption()->set( 'cart_fragments_status', true )->save();
	}

	/**
	 * Get the current setting for Cart Fragments.
	 *
	 * @return string Either 'enabled' or 'disabled'.
	 */
	public function getCartFragmentsSetting() {
		return $this->getOption()->cart_fragments_status ? 'disabled' : 'enabled';
	}

	/**
	 * Add a toggle to the settings page.
	 */
	public function registerOption() {
		$this->options->addOption(
			[ self::OPTION_NAME, 'cart_fragments_status' ],
			'checkbox',
			__( 'Disable WooCommerce Cart Fragments', 'nexcess-mapps' ),
			[ 'description' => __( "By default, WooCommerce includes a 'cart fragments' script that makes a number of uncached AJAX requests on every page load, which can hurt site performance. It's recommended to disable cart fragments unless absolutely necessary.", 'nexcess-mapps' ) ]
		);
	}

	/**
	 * Determine whether or not to dequeue the cart fragments script.
	 *
	 * @return bool Whether or not to dequeue the cart fragments script.
	 */
	public function shouldDequeueCartFragments() {
		return ( 'disabled' === $this->getCartFragmentsSetting() );
	}

	/**
	 * Dequeue the cart fragment JS if needed.
	 */
	public function dequeueCartFragments() {
		if ( wp_script_is( 'wc-cart-fragments', 'enqueued' ) && $this->shouldDequeueCartFragments() ) {
			wp_dequeue_script( 'wc-cart-fragments' );
		}
	}

	/**
	 * Display a notice that WooCommerce Cart Fragments are now handled by Nexcess.
	 *
	 * Since this feature is now built into the platform, customers no longer need to install other cart fragment managers.
	 *
	 * @global $wp_list_table
	 *
	 * @param string  $file   Path to the plugin file relative to the plugins directory.
	 * @param mixed[] $plugin An array of plugin data.
	 */
	public function renderCartFragmentNotice( $file, $plugin ) {
		global $wp_list_table;

		$message = sprintf(
		/* Translators: %1$s is the company name, %2$s is the plugin name or generic text. */
			__( 'WooCommerce cart fragments can be <a href=%3$s>enabled and disabled</a> in the %1$s dashboard. You may safely remove %2$s.', 'nexcess-mapps' ),
			Branding::getCompanyName(),
			isset( $plugin['Name'] ) ? $plugin['Name'] : _x( 'other cart fragment plugins', 'used as a fallback for a missing plugin name', 'nexcess-mapps' ),
			esc_url( 'https://help.nexcess.net/79236-woocommerce/1016076-how-to-disable-cart-fragments-on-your-woocommerce-site' )
		);

		printf(
			'<tr class="plugin-update-tr mapps-plugin-notice%1$s" id="%3$s-update" data-slug="%3$s" data-plugin="%4$s">'
			. '<td colspan="%5$d" class="plugin-update colspanchange">'
			. '  <div class="notice inline notice-info %2$s">'
			. '    <p>%6$s</p>'
			. '  </div>'
			. '</td>'
			. '</tr>',
			$this->isPluginActive( $file ) ? 'active' : '',
			esc_attr( ( ! $this->isPluginActive( $file ) ) ? 'notice-alt' : '' ),
			esc_attr( ( ! empty( $plugin['slug'] ) ) ? esc_attr( $plugin['slug'] ) : __( 'this plugin', 'nexcess-mapps' ) ),
			esc_attr( $file ),
			count( $wp_list_table->get_columns() ),
			wp_kses_post( $message )
		);
	}

	/**
	 * Quick helper method so we don't have to pull both arrays into the HealthCheck separately.
	 *
	 * @return array|string[]
	 */
	public function fullCartPluginList() {
		return array_merge( $this->cartPlugins, $this->cartFragmentPlugins );
	}
}
