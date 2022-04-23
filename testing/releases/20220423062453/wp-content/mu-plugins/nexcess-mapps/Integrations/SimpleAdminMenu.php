<?php

/**
 * Create a simplified administration menu by adding additional hierarchy.
 *
 * Uses the Ultimate Dashboard Pro plugin to re-order and add menu items.
 */

namespace Nexcess\MAPPS\Integrations;

use Nexcess\MAPPS\Concerns\HasAssets;
use Nexcess\MAPPS\Concerns\HasDashboardNotices;
use Nexcess\MAPPS\Concerns\HasHooks;
use Nexcess\MAPPS\Concerns\ManagesGroupedOptions;
use Nexcess\MAPPS\Services\Logger;
use Nexcess\MAPPS\Settings;
use Nexcess\MAPPS\Support\AdminMenus\MenuCustomizer;
use Nexcess\MAPPS\Support\AdminNotice;

class SimpleAdminMenu extends Integration {
	use HasAssets;
	use HasDashboardNotices;
	use HasHooks;
	use ManagesGroupedOptions;

	/**
	 * @var \Nexcess\MAPPS\Support\AdminMenus\MenuCustomizer
	 */
	protected $menuCustomizer;

	/**
	 * @var \Nexcess\MAPPS\Services\Logger
	 */
	protected $logger;

	/**
	 * @var \Nexcess\MAPPS\Settings
	 */
	protected $settings;

	/**
	 * The option name for SimpleAdminMenu settings.
	 */
	const OPTION_NAME = '_nexcess_simple_admin_menu';

	/**
	 * @param \Nexcess\MAPPS\Settings                          $settings
	 * @param \Nexcess\MAPPS\Support\AdminMenus\MenuCustomizer $menu_customizer
	 * @param \Nexcess\MAPPS\Services\Logger                   $logger
	 */
	public function __construct(
		Settings $settings,
		MenuCustomizer $menu_customizer,
		Logger $logger
	) {
		$this->settings       = $settings;
		$this->menuCustomizer = $menu_customizer;
		$this->logger         = $logger;
	}

	/**
	 * Only load this integration on quickstart sites with ultimate dashboard active.
	 *
	 * @return bool Whether or not this integration be loaded in this environment.
	 */
	public function shouldLoadIntegration() {
		return $this->settings->is_quickstart && ! empty( $this->getMenuSections() );
	}

	/**
	 * Retrieve the pre-init actions for the integration.
	 *
	 * @return array[]
	 */
	protected function getActions() {
		return [
			[ 'init', [ $this, 'initialize' ] ],
			[ 'admin_action_nexcess-mapps-toggle-simple-menu', [ $this, 'toggleSimpleMenu' ] ],
			[ 'admin_init', [ $this, 'welcomeNotice' ] ],
			[ 'adminmenu', [ $this, 'renderMenuToggle' ] ],
			[ 'admin_enqueue_scripts', [ $this, 'enqueueToggleStyles' ] ],
		];
	}

	/**
	 * Finish integration initialization based on status of user-specific settings.
	 */
	public function initialize() {
		// Only work in admin areas
		// Would be great to use admin_init, but this hook fires just too late to do the work we need.
		if ( ! is_admin() ) {
			return;
		}

		// When active, add the extra actions/filters to run the simple menu.
		if ( $this->isActive() ) {
			$this->removeUdbMenuCustomizations();

			// PHP_INT_MAX, since even with a value like 999999999 there were still menus being shown without getting
			// hidden as expected. This makes things more stable in that instance.
			add_action( 'admin_menu', [ $this, 'customizeMenu' ], PHP_INT_MAX );
			// Turn off the ability of other plugins to change menu order since the order is critical for proper
			// feature behavior.
			add_filter( 'custom_menu_order', '__return_false', PHP_INT_MAX );
			add_filter( 'admin_enqueue_scripts', [ $this, 'enqueueScripts' ], 1 );
			add_filter( 'default_hidden_meta_boxes', [ $this, 'filterDashboardMetaboxes' ], 99 );
		}
	}

	/**
	 * Customize the WordPress `$menu` and `$submenu` globals based on the stored configuration.
	 */
	public function customizeMenu() {
		global $menu;
		global $submenu;
		global $pagenow;
		global $plugin_page;

		$menu_sections = $this->getMenuSections();

		$customized_menus = $this->menuCustomizer->customizeMenu(
			$menu_sections,
			$menu,
			$submenu,
			wp_get_current_user(),
			$pagenow,
			$plugin_page
		);

		if ( ! $customized_menus->hasMenus() ) {
			$this->logger->error( 'Unable to output the simple admin menu structure. Reverting to the standard menu.' );

			// Only in debug mode, output the structures in the logs.
			// phpcs:disable WordPress.PHP.DevelopmentFunctions.error_log_var_export
			$this->logger->debug( sprintf(
				"\nGlobal Menus:\n%s\nMenu Sections:\n%s",
				var_export( $menu, true ),
				var_export( $menu_sections, true )
			) );
			// phpcs:enable WordPress.PHP.DevelopmentFunctions.error_log_var_export

			// Turn off the script injection as we no longer have hierarchy as expected in the customized menu.
			remove_filter( 'admin_enqueue_scripts', [ $this, 'enqueueScripts' ], 1 );
			remove_filter( 'menu_order', '__return_false', PHP_INT_MAX );
			remove_filter( 'default_hidden_meta_boxes', [ $this, 'filterDashboardMetaboxes' ], 99 );

			$this->removeHooks();

			return;
		}

		// We have to stomp these globals to get WP to actually use the customized menu definitions.
		// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
		$menu = $customized_menus->menu;
		// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
		$submenu = $customized_menus->submenu;
	}

	/**
	 * Check user meta to determine if the simplified menu features are active.
	 *
	 * @return bool whether the simple admin menu is currently active.
	 */
	public function isActive() {
		return ! get_user_meta( get_current_user_id(), 'disable_simple_admin', true );
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
		return array_unique( array_merge( $meta_boxes, [
			'dashboard_activity',
			'dashboard_primary',
			'dashboard_rediscache',
			'dashboard_quick_press',
			'dashboard_site_health',
			'dashboard_right_now',
			'wp_mail_smtp_reports_widget_lite',
			'tribe_dashboard_widget',
			'wpseo-dashboard-overview',
		] ) );
	}

	/**
	 * Get the menu definition as defined by the option, or an empty array.
	 *
	 * @return array[] The array of menu section definitions from the option
	 */
	public function getMenuSections() {
		$sections = $this->getOption()->menuSections;

		return ! empty( $sections )
			? $sections
			: [];
	}

	/**
	 * Register and enqueue styles for the admin menu toggle.
	 */
	public function enqueueToggleStyles() {
		$this->enqueueStyle( 'nexcess-mapps-simple-admin-menu-toggle', 'simple-admin-menu-toggle.css', [ 'dashicons' ] );
	}

	/**
	 * Register and/or enqueue custom scripts and styles.
	 */
	public function enqueueScripts() {
		$this->enqueueScript( 'nexcess-mapps-simple-admin-menu', 'simple-admin-menu.js' );
		$this->enqueueStyle( 'nexcess-mapps-simple-admin-menu', 'simple-admin-menu.css', [ 'dashicons' ] );
	}

	/**
	 * Removes the Ultimate Dashboard Pro hooks which customize the menu, if available and set.
	 *
	 * Since we are taking over the Ultimate Dashboard Pro functionality, we no longer need it to get hooked in. However
	 * if it is active and the menus are set, it may still try to customize the menus. To avoid conflicts, we first
	 * check to ensure both admin menu output class and its get instance method exists, and then use that to get and
	 * then call the 'remove_output_actions' method which will unhook the customization features.
	 *
	 * If the class is missing (because the plugin is not installed), or the methods do not exist (because it got
	 * renamed or something similar), this method will do nothing, and the site will continue operating as normal.
	 *
	 * PHP Stan is ignored in a couple key locations. This functionality has been locally tested, but the static
	 * analysis tools do not seem to recognize the existence of the Ultimate Dashboard Pro classes or methods.
	 */
	public function removeUdbMenuCustomizations() {
		$udb_menu_class = '\UdbPro\AdminMenu\Admin_Menu_Output';
		// @phpstan-ignore-next-line
		if ( ! class_exists( $udb_menu_class ) || ! method_exists( $udb_menu_class, 'get_instance' ) ) {
			return;
		}

		$udb_menus = \UdbPro\AdminMenu\Admin_Menu_Output::get_instance();
		if ( ! method_exists( $udb_menus, 'remove_output_actions' ) ) {
			return;
		}

		$this->logger->info( 'Removing Ultimate Dashboard customizations in favor of the custom menu' );
		// @phpstan-ignore-next-line
		$udb_menus->remove_output_actions();
	}

	/**
	 * Renders the markup needed to display the streamlined menu toggle below the WordPress admin menu.
	 */
	public function renderMenuToggle() {
		$template = <<<'HTML'
			<li class="nx-simple-admin-menu-toggle %1$s">
				<form method="post" action="%2$s">
					<button name="action" type="submit" value="nexcess-mapps-toggle-simple-menu">
						<span class="nx-simple-admin-menu-icon"></span>
						<span class="nx-simple-admin-menu-label">%3$s</span>
					</button>
					%4$s
				</form>
			</li>
HTML;

		$current_admin_path = '';
		if ( ! empty( $_SERVER['REQUEST_URI'] ) ) {
			/*
			 * Toggling breaks when there is a query string in the form action URI.
			 *
			 * To get around this, get the current path (excluding the /wp-admin/ prefix) without
			 * any query variables.
			 */
			$current_admin_path = preg_replace(
				'~^.*/wp-admin/~',
				'',
				wp_parse_url( $_SERVER['REQUEST_URI'], PHP_URL_PATH ) ?: ''
			);
		}
		// Note this template is output directly since the markup in it is hard coded. All of the variables in this
		// are properly escaped, but it is not run through wp_kses_post since that strips out the form markup. This is
		// no different than core which outputs this type of form directly in the admin bar. It could be run through a
		// custom wp_kses set, but it is a lot of overhead when the markup is actually hardcoded above. If that changes,
		// this should run through sanitization.
		printf(
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			$template,
			sanitize_html_class( $this->isActive() ? 'nx-simple-admin-menu-enabled' : 'nx-simple-admin-menu-disabled' ),
			esc_url( admin_url( $current_admin_path ) ),
			esc_html(
				$this->isActive()
					? __( 'Show full menu', 'nexcess-mapps' )
					: __( 'Streamline menu', 'nexcess-mapps' )
			),
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			wp_nonce_field( 'nexcess-mapps-toggle-simple-menu', 'nonce', true, false )
		);
	}

	/**
	 * Callback for requests to toggle the simple admin menu.
	 *
	 * Note: Notices in this method are added using `->persist()` so they survive over the redirect after processing.
	 */
	public function toggleSimpleMenu() {
		if (
			! isset( $_REQUEST['nonce'] )
			|| ! wp_verify_nonce( $_REQUEST['nonce'], 'nexcess-mapps-toggle-simple-menu' )
		) {
			// Send back a one-time displayed message which tells them the operation was unsuccessful.
			( new AdminNotice(
				__( 'We were unable to toggle your menu setting, please try again.', 'nexcess-mapps' ),
				'error',
				false
			) )->persist();
			return $this->redirectToSelf();
		}

		if ( $this->isActive() ) {
			update_user_meta( get_current_user_id(), 'disable_simple_admin', true );
		} else {
			delete_user_meta( get_current_user_id(), 'disable_simple_admin' );
		}

		// If the user has not previously dismissed the notice, dismiss it since they have now interacted with the feature.
		AdminNotice::dismissNotice( get_current_user_id(), 'simple-admin-menu-welcome' );

		// Set up a new one-time displayed notice which tells them what they did and how they can reverse it.
		( new AdminNotice(
			sprintf(
				'<strong>%1$s</strong>' . PHP_EOL . PHP_EOL . '%2$s',
				$this->isActive()
					? _x( 'The streamlined menu is now active', 'admin bar menu title', 'nexcess-mapps' )
					: _x( 'The streamlined menu is no longer active', 'admin bar menu title', 'nexcess-mapps' ),
				$this->isActive()
					? $this->disableHint()
					: $this->enableHint()
			),
			'success',
			false,
			'simple-admin-menu-toggle'
		) )->persist();

		return $this->redirectToSelf();
	}

	/**
	 * Renders the welcome notice pointing them to where the streamlined menu features can be disabled if not dismissed.
	 */
	public function welcomeNotice() {
		if ( ! apply_filters( 'Nexcess\\MAPPS\\SimpleAdminMenu\\ShowWelcomeNotice', true ) ) {
			return;
		}

		$message = sprintf(
			'<strong>%1$s</strong>' . PHP_EOL . PHP_EOL . '%2$s',
			__( 'We have created a more streamlined menu experience for you!', 'nexcess-mapps' ),
			$this->disableHint()
		);
		$this->addGlobalNotice( new AdminNotice( $message, 'info', true, 'simple-admin-menu-welcome' ) );
	}

	/**
	 * Redirects back to the current page, or if not found, redirects to the admin dashboard (index.php).
	 */
	protected function redirectToSelf() {
		add_filter( 'wp_doing_ajax', '__return_true' );
		$referer = wp_get_raw_referer();
		if ( ! $referer ) {
			$referer = admin_url( 'index.php' );
		}
		wp_safe_redirect( $referer );
		die();
	}

	/**
	 * Gets the translated text which provides a hint to where a user can disable the simple admin menu.
	 *
	 * @return string The translated text with correct branding applied.
	 */
	protected function disableHint() {
		return sprintf(
			/* Translators: %1$s is the example menu markup to show users where to click*/
			__(
				'If at any point you wish to use the full WordPress menu, click %1$s at the bottom of the menu.',
				'nexcess-mapps'
			),
			sprintf(
				'<span class="nx-simple-menu-example"><span class="nx-sample-menu-icon-disable"></span> %1$s</span>',
				$this->getDisableMenuText()
			)
		);
	}

	/**
	 * Gets the translated text which provides a hint to where a user can enable the simple admin menu.
	 *
	 * @return string The translated text with correct branding applied.
	 */
	protected function enableHint() {
		return sprintf(
			/* Translators: %1$s is the example menu markup to show users where to click*/
			__(
				'If at any point you wish to use the streamlined admin menu, click %1$s at the bottom of the menu.',
				'nexcess-mapps'
			),
			sprintf(
				'<span class="nx-simple-menu-example"><span class="nx-sample-menu-icon-enable"></span> %1$s</span>',
				$this->getEnableMenuText()
			)
		);
	}

	/**
	 * Gets the text to use in the menu for turning the simple admin menu off.
	 *
	 * @return string The translated text for the button
	 */
	protected function getDisableMenuText() {
		return _x( 'Show full menu', 'admin bar menu title', 'nexcess-mapps' );
	}

	/**
	 * Gets the text to use in the menu for turning the simple admin menu on.
	 *
	 * @return string The translated text for the button
	 */
	protected function getEnableMenuText() {
		return _x( 'Streamline menu', 'admin bar menu title', 'nexcess-mapps' );
	}
}
