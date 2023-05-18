<?php

namespace Nexcess\MAPPS\Integrations;

use Nexcess\MAPPS\Concerns\HasCronEvents;
use Nexcess\MAPPS\Concerns\HasWordPressDependencies;
use Nexcess\MAPPS\Services\MappsApiClient;
use StellarWP\PluginFramework\Exceptions\RequestException;
use WP_Error;
use WP_User;

class AutoLogin extends Integration {
	use HasCronEvents;
	use HasWordPressDependencies;

	/**
	 * @var MappsApiClient The client for connecting to the MAPPS API.
	 */
	protected $client;

	/**
	 * Cron hook for attempting to complete auto signin loop.
	 */
	const SITE_SETUP_COMPLETE_HOOK = 'nexcess_mapps_site_setup_complete';

	/**
	 * Constant to specify the correct option during setup.
	 */
	const AUTO_LOGIN_INITIAL_SETUP_COMPLETE = 'nexcess_mapps_password_changed';

	/**
	 * Construct a new AutoLogin instance.
	 *
	 * @param MappsApiClient $client
	 */
	public function __construct( MappsApiClient $client ) {
		$this->client = $client;
	}

	/**
	 * Determine whether or not this integration should be loaded.
	 *
	 * @return bool Whether or not this integration should be loaded in this environment.
	 */
	public function shouldLoadIntegration() {
		return $this->siteIsAtLeastWordPressVersion( '5.8' );
	}

	/**
	 * Retrieve all actions for the integration.
	 *
	 * @return array[]
	 */
	protected function getActions() {
		// phpcs:disable WordPress.Arrays
		return [
			[ 'login_init',                            [ $this, 'checkParameters'          ] ],
			[ 'after_password_reset',                  [ $this, 'handlePasswordReset'      ], 10, 2 ],
			[ 'profile_update',                        [ $this, 'handleProfileUpdate'      ], 10, 3 ],
			[ 'wme_sitebuilder_user_password_updated', [ $this, 'handleStoreBuilderUpdate' ], 10, 1 ],
			[ self::SITE_SETUP_COMPLETE_HOOK,          [ $this, 'completeInitialSiteSetup' ] ],
		];
		// phpcs:enable WordPress.Arrays
	}

	/**
	 * Check to see if the authentication parameters are present in the request, adding the authenticate filter if so.
	 */
	public function checkParameters() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( ! isset( $_GET['token'] ) ) {
			return;
		}

		// 50 will run this after core's main login functions (which run at 30), and before core's spam check filter
		add_filter( 'authenticate', [ $this, 'validateAutoLogin' ], 50 );
	}

	/**
	 * Validate an automatic login request on the authenticate filter.
	 *
	 * If a user is already present, this filter does nothing. If not, this method attempts to auto-login the user.
	 *
	 * This only works for the SiteWorx managed user, and the token must validate at the MAPPS API.
	 *
	 * If it does, this validates the the username matches the SiteWorx admin user. If auth failes, it will provide an
	 * error message on the login page to indicate auto-login was unsuccessful.
	 *
	 * @param WP_User|WP_Error|null $user The current user, a WP_Error if a different login error has occurred, or null.
	 *
	 * @return WP_User|WP_Error Either the logged in user (sandbox if this logged them in), or an Error if unsuccessful.
	 */
	public function validateAutoLogin( $user ) {
		// If the user is already set, do not perform login.
		if ( $user instanceof WP_User ) {
			return $user;
		}

		try {
			// This is not form data, so there is no nonce. It is generated and validated using the MAPPS API.
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$request_token = isset( $_GET['token'] ) ? sanitize_text_field( wp_unslash( $_GET['token'] ) ) : '';
			$username      = $this->client->validateAutoLogin( $request_token );
		} catch ( RequestException $e ) {
			return new WP_Error( 'login', sprintf( 'Unexpected error attempting auto login: %s', $e->getMessage() ) );
		}

		if ( ! $username ) {
			return new WP_Error( 'login', 'The automatic login token provided was not valid. Please log in again.' );
		}

		// This only works for the default user. If they are not present, do nothing.
		$auto_user = get_user_by( 'login', $username );
		if ( ! $auto_user instanceof WP_User ) {
			return new WP_Error(
				'login',
				sprintf( 'The %s user is missing, unable to continue automatic login.', $username )
			);
		}

		do_action( 'nexcess_autologin' );

		return $auto_user;
	}

	/**
	 * Checks if user created by Nexcess platform is user 1. Kicks off notification of Nocworx API.
	 *
	 * @param WP_User $user
	 * @param string  $new_pass
	 */
	public function handlePasswordReset( $user, $new_pass ) {
		if ( 1 !== $user->ID ) {
			return;
		}

		$this->completeInitialSiteSetup();
	}

	/**
	 * Checks if user created by Nexcess is the primary admin, and the password is actually being changed.
	 *  Kicks off notification of Nocworx API.
	 *
	 * @param int     $user_id
	 * @param WP_User $old_user_data
	 * @param array   $userdata
	 */
	public function handleProfileUpdate( $user_id, $old_user_data, $userdata ) {
		if ( 1 !== $user_id ) {
			return;
		}

		if ( empty( $userdata['user_pass'] ) ) {
			return;
		}

		$this->completeInitialSiteSetup();
	}

	/**
	 * Checks if user created by Nexcess is the primary admin, and the password is actually being changed.
	 *  Kicks off notification of Nocworx API.
	 *
	 * @param int $user_id
	 */
	public function handleStoreBuilderUpdate( $user_id ) {
		if ( 1 !== $user_id ) {
			return;
		}

		$this->completeInitialSiteSetup();
	}

	/**
	 * Makes a check against the Nexcess MAPPS API to determine if this site has
	 * already processed a successful operation.
	 *
	 * @return string | null Will result in one of the following states based on the
	 *                       list of operations (failure|success|pending|new).
	 */
	public function isInitialSetupComplete() {
		$operations = $this->client->isSiteSetupComplete();

		// If we don't have any operations in the response, we can assume we haven't
		// attempted to informed the Nexcess MAPPS API the site setup is completed.
		if ( empty( $operations ) ) {
			return 'new';
		}

		$operation_states = wp_list_pluck( $operations, 'state' );

		if ( in_array( 'success', $operation_states, true ) ) {
			return 'success';
		}

		if ( count( array_intersect( [ 'running', 'waiting' ], $operation_states ) ) > 0 ) {
			return 'pending';
		}

		if ( in_array( 'failure', $operation_states, true ) ) {
			return 'failure';
		}

		return null;
	}

	/**
	 * Schedule a follow-up check to see if the operation is succesfully completed the provided interval of time.
	 *
	 * @param string $interval Must be a valid \DateInterval string.
	 */
	public function scheduleFollowUpCheck( $interval ) {
		$this->registerCronEvent(
			self::SITE_SETUP_COMPLETE_HOOK,
			null,
			current_datetime()->add( new \DateInterval( $interval ) )
		);

		$this->scheduleEvents();
	}

	/**
	 * Handle checking and notifying the Nexcess MAPPS API when we believe the site has fully
	 * been set up and the AutoLogin credentials can be turned off.
	 */
	public function completeInitialSiteSetup() {

		// If we've already marked the auto login as completed and received confirmation
		// from the API, we don't need to do anything else.
		if ( get_option( self::AUTO_LOGIN_INITIAL_SETUP_COMPLETE, false ) ) {
			return;
		}

		$status = $this->isInitialSetupComplete();

		switch ( $status ) {
			case 'new':
			case 'failure':
				// Intentionally using add_option so this will not overwrite
				// the value if it's already been set to true.
				add_option( self::AUTO_LOGIN_INITIAL_SETUP_COMPLETE, false );

				// Inform Nexcess MAPPS API that the initial site setup is completed.
				$this->client->siteSetupComplete();

				// Set the interval to 5 minutes if this is the first time we're attempting
				// to inform the Nexcess MAPPS API. Otherwise, we'll check the status in a day.
				$this->scheduleFollowUpCheck( ( 'new' === $status ) ? 'PT5M' : 'P1D' );
				break;
			case 'pending':
				// We're not going to trying calling the Nexcess MAPPS API because we already have a
				// operation in progress. Instead, we'll schedule a cron event to check back in an hour.
				$this->scheduleFollowUpCheck( 'PT1H' );
				break;
			case 'success':
				// Update the option for checking later to short-circuit the need
				// to call the Nexcess MAPPS API.
				update_option( self::AUTO_LOGIN_INITIAL_SETUP_COMPLETE, true );
				break;
			default:
				// Don't do anything if we don't get a status we recognize.
				break;
		}
	}
}
