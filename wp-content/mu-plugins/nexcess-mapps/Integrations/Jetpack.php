<?php

/**
 * Integration with Automattic's Jetpack plugin.
 *
 * For a full list of available modules, please visit the Jetpack GitHub repository.
 *
 * @link https://github.com/Automattic/jetpack/tree/master/modules
 */

namespace Nexcess\MAPPS\Integrations;

use Jetpack_Options;
use Nexcess\MAPPS\Concerns\HasHooks;
use Nexcess\MAPPS\Concerns\HasWordPressDependencies;
use Nexcess\MAPPS\Settings;
use WP_User;

class Jetpack extends Integration {
	use HasHooks;
	use HasWordPressDependencies;

	/**
	 * Post meta keys that should never be synchronized.
	 *
	 * Note that these keys will *always* be blocked, regardless of post type.
	 *
	 * @var string[]
	 */
	protected $privatePostMeta = [
		'_created_via',
	];

	/**
	 * Post types that should never be synchronized to Jetpack.
	 *
	 * @var string[]
	 */
	protected $privatePostTypes = [
		'feedback',
		'scheduled-action',
		'shop_coupon',
		'shop_order',
		'shop_order_refund',
	];

	/**
	 * Post types that should never be synchronized to Jetpack.
	 *
	 * @var string[]
	 */
	protected $privateCommentTypes = [
		'action_log',
		'review',
		'order_note',
		'webhook_delivery',
	];

	/**
	 * Taxonomies whose terms should never be synchronized to Jetpack.
	 *
	 * @var string[]
	 */
	protected $privateTaxonomies = [
		'action-group',
	];

	/**
	 * Unless a user has capabilities beyond those defined here, their data should not be sent to
	 * Jetpack Sync.
	 *
	 * @var string[]
	 */
	protected $privateCapabilities = [
		'customer',
		'read',
	];

	/**
	 * @var \Nexcess\MAPPS\Settings
	 */
	protected $settings;

	/**
	 * The minimum supported version of Jetpack.
	 *
	 * Sites running Jetpack below this version will continue to work, but this integration will
	 * not be loaded.
	 */
	const MINIMUM_SUPPORTED_VERSION = '8.2';

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
		return $this->isPluginActive( 'jetpack/jetpack.php' )
			&& ! $this->settings->customer_jetpack
			&& in_array( $this->settings->package_label, self::getEligiblePlans(), true )
			&& defined( 'JETPACK__VERSION' )
			&& version_compare( JETPACK__VERSION, self::MINIMUM_SUPPORTED_VERSION, '>=' );
	}

	/**
	 * Perform any necessary setup for the integration.
	 *
	 * This method is automatically called as part of Plugin::loadIntegration(), and is the
	 * entry-point for all integrations.
	 */
	public function setup() {
		$this->addHooks();
		$this->setOfflineMode();

		// Disable the "Just In Time Message" feature within Jetpack.
		remove_action( 'init', [ 'Jetpack_JITM', 'init' ] );
	}

	/**
	 * Retrieve plan codes that should get the full Jetpack integration.
	 *
	 * @return string[]
	 */
	public static function getEligiblePlans() {
		return [
			Settings::PLAN_BASIC,
			Settings::PLAN_BUSINESS,
			Settings::PLAN_FREELANCE,
			Settings::PLAN_PERSONAL,
			Settings::PLAN_PLUS,
			Settings::PLAN_PRO,
			Settings::PLAN_PROFESSIONAL,
			Settings::PLAN_STANDARD,
			Settings::PLAN_MWC_ENTERPRISE,
		];
	}

	/**
	 * Retrieve the current version of Jetpack installed.
	 *
	 * The JETPACK__VERSION constant has been available since Jetpack 1.2, so it's definitely
	 * a reliable source.
	 *
	 * @link https://github.com/Automattic/jetpack/commit/8c037e22fefe0a3f7289a469394be356ee6f49ef#diff-b0f04b3cb3558222de8cf6bca0d4ec45
	 *
	 * @return string
	 */
	protected function getJetpackVersion() {
		return defined( 'JETPACK__VERSION' ) ? JETPACK__VERSION : '1.1';
	}

	/**
	 * Toggle "offline" (previously "development") mode when running on a temporary domain.
	 *
	 * Connecting to WordPress.com while on a temporary domain frequently causes connectivity
	 * issues between Jetpack and WordPress.com, so it's best to connect after a live domain is
	 * applied.
	 */
	protected function setOfflineMode() {
		if ( ! $this->settings->is_temp_domain ) {
			return;
		}

		// Jetpack 8.8 changed "development" to "offline" mode.
		if ( version_compare( '8.8', $this->getJetpackVersion(), '<=' ) ) {
			add_filter( 'jetpack_offline_mode', '__return_true' );
		} else {
			add_filter( 'jetpack_development_mode', '__return_true' );
		}
	}

	/**
	 * Retrieve all filters for the integration.
	 *
	 * @return array[]
	 */
	protected function getFilters() {
		// phpcs:disable WordPress.Arrays
		return [
			[ 'jetpack_get_default_modules',   [ $this, 'setDefaultModules' ] ],
			[ 'jetpack_get_available_modules', [ $this, 'disableModules'    ] ],
			[ 'jetpack_photon_pre_args',       [ $this, 'configurePhoton'   ] ],
			[ 'updating_jetpack_version',      [ $this, 'disableJumpstart'  ] ],

			// Jetpack Sync filtering.
			[ 'jetpack_sync_before_enqueue_jetpack_sync_save_post',        [ $this, 'blockPrivatePostTypesFromSync'  ], 11 ],
			[ 'jetpack_sync_before_enqueue_added_post_meta',               [ $this, 'blockPrivatePostMetaFromSync'   ], 11 ],
			[ 'jetpack_sync_before_enqueue_updated_post_meta',             [ $this, 'blockPrivatePostMetaFromSync'   ], 11 ],
			[ 'jetpack_sync_before_enqueue_deleted_post_meta',             [ $this, 'blockPrivatePostMetaFromSync'   ], 11 ],
			[ 'jetpack_sync_before_enqueue_jetpack_sync_save_term',        [ $this, 'blockPrivateTaxonomiesFromSync' ], 11 ],
			[ 'jetpack_sync_before_enqueue_jetpack_sync_add_term',         [ $this, 'blockPrivateTaxonomiesFromSync' ], 11 ],
			[ 'jetpack_sync_before_enqueue_jetpack_sync_add_user',         [ $this, 'filterCustomersFromSync'        ], 11 ],
			[ 'jetpack_sync_before_enqueue_jetpack_sync_register_user',    [ $this, 'filterCustomersFromSync'        ], 11 ],
			[ 'jetpack_sync_before_enqueue_jetpack_sync_save_user',        [ $this, 'filterCustomersFromSync'        ], 11 ],

			// These filters may be redundant, but still act as fail-safes.
			[ 'jetpack_sync_before_send_woocommerce_new_order',            '__return_false', 11 ],
			[ 'jetpack_sync_before_send_added_order_item_meta',            '__return_false', 11 ],
			[ 'jetpack_sync_before_send_woocommerce_new_order_item',       '__return_false', 11 ],
			[ 'jetpack_sync_before_send_woocommerce_update_order_item',    '__return_false', 11 ],
			[ 'jetpack_sync_before_send_woocommerce_order_status_changed', '__return_false', 11 ],

			// Hide Jetpack's default backup UI.
			[ 'jetpack_show_backups', '__return_false' ],

			// Don't activate the SSO module by default.
			[ 'jetpack_start_enable_sso', '__return_false' ],

			// Disable the "Just In Time Message" feature within Jetpack.
			[ 'jetpack_just_in_time_msgs', '__return_false' ],
		];
		// phpcs:enable WordPress.Arrays
	}

	/**
	 * Set the modules that should be activated by default.
	 *
	 * @param string[] $modules Array of default Jetpack modules. Will not be used.
	 *
	 * @return string[] Filtered module array.
	 */
	public function setDefaultModules( $modules ) {
		return [
			'lazy-images',
			'photon',
			'protect',
			'search',
		];
	}

	/**
	 * Disable modules within Jetpack.
	 *
	 * Disabling a module will prevent it from appearing in the UI and/or being activated by the site
	 * owner. Be forewarned that this should only be used for modules that could cause conflicts on
	 * the platform!
	 *
	 * @param string[] $modules Array of available Jetpack modules.
	 *
	 * @return string[] Filtered module array.
	 */
	public function disableModules( $modules ) {
		$disabled = [
			'vaultpress',
		];

		return array_diff_key( $modules, array_fill_keys( $disabled, true ) );
	}

	/**
	 * Customize the default settings for Jetpack's Photon module.
	 *
	 * @link https://developer.jetpack.com/hooks/jetpack_photon_pre_args/
	 *
	 * @param mixed[] $args The Photon arguments.
	 *
	 * @return mixed[] The filtered array of arguments.
	 */
	public function configurePhoton( $args ) {

		// By default, use a quality of 90%.
		$args['quality'] = 90;

		// Strip all image metadata.
		$args['strip'] = 'all';

		return $args;
	}

	/**
	 * Disable the Jumpstart screen.
	 */
	public function disableJumpstart() {
		if ( Jetpack_Options::is_valid( 'jumpstart' ) ) {
			Jetpack_Options::update_option( 'jumpstart', 'jetpack_action_taken' );
		}
	}

	/**
	 * Exclude $this->privatePostTypes from Jetpack Sync.
	 *
	 * @param mixed[]|bool $args The Jetpack Sync arguments, or false if Jetpack has already filtered
	 *                           this post type.
	 *
	 * @return mixed[]|bool The (possibly) filtered results.
	 */
	public function blockPrivatePostTypesFromSync( $args ) {
		if (
			is_array( $args )
			&& isset( $args[1]->post_type )
			&& in_array( $args[1]->post_type, $this->privatePostTypes, true )
		) {
			return false;
		}

		return $args;
	}

	/**
	 * Exclude meta keys from $this->privatePostMeta from being sent to Jetpack Sync.
	 *
	 * @param mixed[]|bool $args {
	 *
	 *   [0] => $id
	 *   [1] => $post_id
	 *   [2] => $meta_key
	 *   [3] => $meta_value
	 * }
	 *
	 * The Jetpack Sync arguments, or false if Jetpack has already filtered this post meta.
	 *
	 * @return mixed[]|bool The (possibly) filtered results.
	 */
	public function blockPrivatePostMetaFromSync( $args ) {
		if ( ! is_array( $args ) || ! isset( $args[1], $args[2] ) ) {
			return $args;
		}

		// Block everything for private post types.
		if ( in_array( get_post_type( $args[1] ), $this->privatePostTypes, true ) ) {
			return false;
		}

		// Return false if the meta key should be private.
		if ( in_array( $args[2], $this->privatePostMeta, true ) ) {
			return false;
		}

		return $args;
	}

	/**
	 * Exclude $this->privateTaxonomies from Jetpack Sync.
	 *
	 * @param mixed[]|bool $args The Jetpack Sync arguments, or false if Jetpack has already
	 *                           filtered this taxonomy.
	 *
	 * @return mixed[]|bool The (possibly) filtered results.
	 */
	public function blockPrivateTaxonomiesFromSync( $args ) {
		if (
			is_array( $args )
			&& isset( $args[0]->taxonomy )
			&& in_array( $args[0]->taxonomy, $this->privateTaxonomies, true )
		) {
			return false;
		}

		return $args;
	}

	/**
	 * Don't send customer data to Jetpack Sync.
	 *
	 * @param mixed[]|bool $args The Jetpack Sync arguments, or false if Jetpack has already filtered
	 *                           this user.
	 *
	 * @return mixed[]|bool The (possibly) filtered results.
	 */
	public function filterCustomersFromSync( $args ) {
		if ( is_array( $args ) && isset( $args[0] ) && $args[0] instanceof WP_User ) {
			$capabilities = array_keys( array_filter( $args[0]->get_role_caps() ) );

			// The user only has capabilities that are within $this->privateCapabilities.
			if ( empty( array_diff( $capabilities, $this->privateCapabilities ) ) ) {
				return false;
			}
		}

		return $args;
	}
}
