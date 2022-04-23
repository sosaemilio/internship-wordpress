<?php

namespace Nexcess\MAPPS\Integrations\StoreBuilder;

use Nexcess\MAPPS\Concerns\HasHooks;

class StoreBuilderUser {
	use HasHooks;

	const SB_USER_ACTION = 'sb_user_edit';

	/**
	 * Check for post data username and password. If it exists, save it and reissue cookies.
	 */
	public function __construct() {
		$this->addHooks();
	}

	/**
	 * Sets the actions.
	 */
	protected function getActions() {
		return [
			[ 'admin_init', [ $this, 'createUser' ] ],
		];
	}

	/**
	 * Sets the User Values.
	 */
	public function createUser() {
		if ( ! isset( $_REQUEST['action'] ) || self::SB_USER_ACTION !== $_REQUEST['action'] ) {
			return;
		}

		if ( ! isset( $_REQUEST['_wpnonce'] ) || false === wp_verify_nonce( $_REQUEST['_wpnonce'], 'wp_rest' ) ) {
			return;
		}

		$update = false;
		$user   = wp_get_current_user();

		if ( isset( $_REQUEST['password'] ) ) {
			$password = sanitize_text_field( $_REQUEST['password'] );
			wp_set_password( $password, $user->ID );
			$update = true;
		}

		if ( isset( $_REQUEST['username'] ) ) {
			$username = sanitize_text_field( $_REQUEST['username'] );
			if ( $user->user_login !== $username && $this->validateUsername( $username ) ) {
				global $wpdb;
				$update = $wpdb->update(
					$wpdb->users,
					[ 'user_login' => $username ],
					[ 'ID' => $user->ID ]
				);
			}
		}

		if ( $update ) {
			clean_user_cache( $user->ID );
			$user = wp_set_current_user( $user->ID );
			wp_set_auth_cookie( $user->ID );
			do_action( 'wp_login', $user->user_login, $user );
		}
	}

	/**
	 * Validates the username entered.
	 *
	 * @param string $username The username to validate.
	 *
	 * @return bool True if the username is valid, false otherwise.
	 */
	public function validateUsername( $username ) {
		if ( ! validate_username( $username ) ) {
			return false;
		}

		$illegal_logins = (array) apply_filters( 'illegal_user_logins', [
			'adm',
			'admin',
			'admin1',
			'hostname',
			'manager',
			'qwerty',
			'root',
			'support',
			'sysadmin',
			'test',
			'user',
			'webmaster',
		] );

		if ( in_array( strtolower( $username ), array_map( 'strtolower', $illegal_logins ), true ) ) {
			return false;
		}

		return true;
	}
}
