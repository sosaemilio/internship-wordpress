<?php

/**
 * Functionality related to Nexcess support.
 */

namespace Nexcess\MAPPS\Integrations;

use Nexcess\MAPPS\Concerns\HasHooks;
use Nexcess\MAPPS\Exceptions\WPErrorException;
use WP_Error;
use WP_User;

class SupportUsers extends Integration {
	use HasHooks;

	/**
	 * The meta key used to track support users.
	 */
	const USER_EXPIRATION_META_KEY = '_nexcess_mapps_support_user_expires_at';

	/**
	 * How long (in seconds) since last login a support user should persist before being purged.
	 */
	const USER_EXPIRATION_TIME = 259200; // 72 hours.

	/**
	 * Retrieve all actions for the integration.
	 *
	 * @return array[]
	 */
	protected function getActions() {
		// phpcs:disable WordPress.Arrays
		return [
			[ 'authenticate',                             [ $this, 'authenticate'                ], 0,  2 ],
			[ 'wp_login',                                 [ $this, 'extendSupportUserExpiration' ], 10, 2 ],
			[ 'wp_head',                                  [ $this, 'blockRobots'                 ], 0     ],
			[ Maintenance::DAILY_MAINTENANCE_CRON_ACTION, [ $this, 'cleanUpSupportUsers'         ]        ],
		];
		// phpcs:enable WordPress.Arrays
	}

	/**
	 * Retrieve all filters for the integration.
	 *
	 * @return array[]
	 */
	protected function getFilters() {
		return [
			[ 'author_link', [ $this, 'replaceAuthorLink' ], 10, 2 ],
		];
	}

	/**
	 * Check for expired (but not-yet purged) users at authentication time.
	 *
	 * @param null|WP_User|WP_Error $user     WP_User if the user is authenticated.
	 *                                        WP_Error or null otherwise.
	 * @param string                $username Username or email address.
	 *
	 * @return null|WP_User|WP_Error If the user is an expired support user, a WP_Error object will
	 *                               be returned. Otherwise, $user will be returned unaltered.
	 */
	public function authenticate( $user, $username ) {
		if ( ! $username ) {
			return $user;
		}

		$user_object = get_user_by( 'login', $username );

		if ( ! $user_object instanceof WP_User ) {
			return $user;
		}

		$expiration = get_user_meta( $user_object->ID, self::USER_EXPIRATION_META_KEY, true );

		if ( ! empty( $expiration ) && $expiration <= time() ) {
			$this->deleteUser( $user_object->ID );

			return new WP_Error(
				'nexcess-support-user-expired',
				__( 'This support user has expired and is no longer available.', 'nexcess-mapps' ),
				[
					'user_login' => $username,
					'expired_at' => $expiration,
				]
			);
		}

		return $user;
	}

	/**
	 * Clean up expired support users.
	 */
	public function cleanUpSupportUsers() {
		$expired = get_users( [
			'blog_id'      => 0,
			'meta_key'     => self::USER_EXPIRATION_META_KEY,
			'meta_value'   => time(),
			'meta_compare' => '<=',
			'fields'       => 'ID',
		] );

		array_map( [ $this, 'deleteUser' ], $expired );
	}

	/**
	 * Extend a support user's expiration time upon login.
	 *
	 * @param string  $user_login The user login.
	 * @param WP_User $user       The WP_User object.
	 */
	public function extendSupportUserExpiration( $user_login, WP_User $user ) {
		if ( empty( get_user_meta( $user->ID, self::USER_EXPIRATION_META_KEY, true ) ) ) {
			return;
		}

		update_user_option( $user->ID, self::USER_EXPIRATION_META_KEY, time() + self::USER_EXPIRATION_TIME, true );
	}

	/**
	 * Explicitly block robots from indexing support users.
	 *
	 * @global $wp_query
	 */
	public function blockRobots() {
		global $wp_query;

		// Not an author archive.
		if ( ! $wp_query->is_author() ) {
			return;
		}

		// Not a support user.
		if ( empty( get_user_meta( $wp_query->get_queried_object()->ID, self::USER_EXPIRATION_META_KEY, true ) ) ) {
			return;
		}

		/*
		 * Explicitly block robots from indexing this archive.
		 *
		 * This will use the WP Robots API for WordPress 5.7+, but fall back to printing a meta tag
		 * for older versions.
		 */
		if ( function_exists( 'wp_robots' ) && function_exists( 'wp_robots_no_robots' ) ) {
			add_filter( 'wp_robots', 'wp_robots_no_robots' );
		} else {
			echo '<meta name="robots" content="noindex" />';
		}
	}

	/**
	 * Filter the "author_link" to avoid sending traffic to an author archive for a support user.
	 *
	 * @param string $link    The author posts link.
	 * @param int    $user_id The user ID.
	 *
	 * @return string $link The filtered posts link.
	 */
	public function replaceAuthorLink( $link, $user_id ) {
		if ( empty( get_user_meta( $user_id, self::USER_EXPIRATION_META_KEY, true ) ) ) {
			return $link;
		}

		return Support::SUPPORT_URL;
	}

	/**
	 * Delete a user by ID.
	 *
	 * If the user has created any content, it will be reassigned to the oldest *non-support*
	 * administrator on the site.
	 *
	 * @see wp_delete_user()
	 *
	 * @param int $user_id The user ID to delete.
	 *
	 * @return bool True if the user has been deleted, false otherwise.
	 */
	protected function deleteUser( $user_id ) {
		if ( is_multisite() ) {
			require_once ABSPATH . '/wp-admin/includes/ms.php';

			revoke_super_admin( $user_id );
			return wpmu_delete_user( $user_id );
		}

		require_once ABSPATH . '/wp-admin/includes/user.php';

		return wp_delete_user( $user_id );
	}

	/**
	 * Create a new, temporary support user.
	 *
	 * Support users will automatically get deleted three days after their last login.
	 *
	 * @param mixed[] $userdata Details about the user being created. @see wp_insert_user() for a
	 *                          full list of available arguments.
	 *
	 * @throws \Nexcess\MAPPS\Exceptions\WPErrorException If the user cannot be created.
	 *
	 * @return int The ID of the newly-created user.
	 */
	public static function createSupportUser( array $userdata = [] ) {
		$uniqid   = uniqid();
		$userdata = wp_parse_args( $userdata, [
			'user_pass'    => wp_generate_password(),
			'user_login'   => 'nexcess_support_' . $uniqid,
			'user_url'     => 'https://nexcess.net/support',
			'user_email'   => sprintf( 'devnull+%s@nexcess.net', $uniqid ),
			'display_name' => 'Nexcess Support',
			'nickname'     => 'Nexcess Support',
			'first_name'   => 'Nexcess',
			'last_name'    => 'Support',
			'description'  => 'This is a temporary user generated by Nexcess support. It will automatically be cleaned up once your support request has been resolved.',
			'use_ssl'      => true,
			'role'         => 'administrator',
		] );

		$user_id = wp_insert_user( $userdata );

		if ( is_wp_error( $user_id ) ) {
			throw new WPErrorException( $user_id );
		}

		// Set an expiration time on the user.
		update_user_option( $user_id, self::USER_EXPIRATION_META_KEY, time() + self::USER_EXPIRATION_TIME, true );

		// Grant the user super-admin privileges.
		if ( is_multisite() ) {
			grant_super_admin( $user_id );
		}

		return $user_id;
	}
}
