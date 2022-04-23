<?php

/**
 * Adds a WP Pointer for new storebuilder users. This will add the
 * self::POINTER_ACTION option to the user meta. You can quickly
 * remove it for a user by the following cli command for single
 * users or all users:
 * `wp user meta delete <user_id> dismiss-storebuilder-v3-upgrade-pointer`
 * `wp user list --field=ID | xargs -I % wp user meta delete % dismiss-storebuilder-v3-upgrade-pointer`.
 */

namespace Nexcess\MAPPS\Integrations\StoreBuilder;

use Nexcess\MAPPS\Concerns\HasHooks;
use Nexcess\MAPPS\Concerns\HasWordPressDependencies;
use Nexcess\MAPPS\Integrations\Integration;
use Nexcess\MAPPS\Settings;

class AdminPointer extends Integration {
	use HasHooks;
	use HasWordPressDependencies;

	const POINTER_SLUG   = 'storebuilder-v3-upgrade-pointer';
	const POINTER_ACTION = 'dismiss-storebuilder-v3-upgrade-pointer';

	/**
	 * @var \Nexcess\MAPPS\Settings
	 */
	protected $settings;

	/**
	 * @var StoreBuilderFTC
	 */
	protected $storebuilder_ftc;

	/**
	 * Initialize the dependencies.
	 *
	 * @param \Nexcess\MAPPS\Settings $settings
	 * @param StoreBuilderFTC         $storebuilder_ftc
	 */
	public function __construct( Settings $settings, StoreBuilderFTC $storebuilder_ftc ) {
		$this->settings         = $settings;
		$this->storebuilder_ftc = $storebuilder_ftc;
	}

	/**
	 * Perform any necessary setup for the integration.
	 *
	 * This method is automatically called as part of Plugin::loadIntegration(), and is the
	 * entry-point for all integrations.
	 */
	public function setup() {
		$this->addHooks();
	}

	/**
	 * Only load this integration if we are a storebuilder instance, coming
	 * from Storebuilder 2, and an admin user.
	 *
	 * @return bool Whether or not this integration should be loaded in this environment.
	 */
	public function shouldLoadIntegration() {
		if ( ! $this->settings->is_storebuilder || ! $this->isPluginActive( 'woocommerce/woocommerce.php' ) ) {
			return false;
		}

		return version_compare( $this->storebuilder_ftc->getStorebuilderVersion(), '3.0', '<' );
	}

	/**
	 * Retrieve all actions for the integration.
	 *
	 * @return array[]
	 */
	protected function getActions() {
		// phpcs:disable WordPress.Arrays
		return [
			[ 'in_admin_header', [ $this, 'loadPointerScripts' ] ],
			[ 'admin_init',      [ $this, 'dismissPointer' ] ],
		];
		// phpcs:enable WordPress.Arrays
	}

	/**
	 * Loads the scripts required for the pointer to show on the Admin
	 * Dashboard. The should_load_integration() method loads too early
	 * in the life cylce to test if we are on the dashboard page so we
	 * are doing it before enqueing and displaying the scripts.
	 */
	public function loadPointerScripts() {

		$screen = get_current_screen();
		if ( ! isset( $screen ) || 'dashboard' !== $screen->id || ! current_user_can( 'administrator' ) ) {
			return;
		}

		wp_enqueue_script( 'jquery' );
		wp_enqueue_style( 'wp-pointer' );
		wp_enqueue_script( 'wp-pointer' );

		if ( ! get_user_meta( get_current_user_id(), self::POINTER_ACTION, true ) ) {
			$pointer_content = sprintf(
				'<h3>%s</h3><p>%s</p>',
				esc_html__( 'Updates for you', 'nexcess-mapps' ),
				esc_html__( 'Store setup options have been moved to our new Setup screen.', 'nexcess-mapps' )
			);
			?>

			<script type="text/javascript">
			//<![CDATA[
				jQuery( function () {
					jQuery( '#adminmenu #toplevel_page_storebuilderapp' ).pointer( {
						content: '<?php echo wp_kses_post( $pointer_content ); ?>',
						position: {
									edge: 'left',
									align: 'center'
								},
						show: function( event, t ){
							t.pointer.css( {
								position: 'fixed'
							} );
						},
						close: function() {
							jQuery.post( ajaxurl, {
								pointer: '<?php echo esc_js( self::POINTER_SLUG ); ?>',
								action: '<?php echo esc_js( self::POINTER_ACTION ); ?>',
								_nonce: '<?php echo esc_js( wp_create_nonce( self::POINTER_SLUG ) ); ?>'
							});
						}
					} ).pointer( 'open' );
				} );
			//]]>
			</script>
			<?php
		}
	}

	/**
	 * Catches the AJAX request to dismiss the pointer and sets the user meta
	 * to not show the pointer again.
	 */
	public function dismissPointer() {
		if ( ! isset( $_REQUEST['action'] ) || ! isset( $_REQUEST['_nonce'] ) ) {
			return;
		}

		if ( self::POINTER_ACTION === $_REQUEST['action'] && wp_verify_nonce( $_REQUEST['_nonce'], self::POINTER_SLUG ) ) {
			update_user_meta( get_current_user_id(), self::POINTER_ACTION, true, true );
		}
	}
}
