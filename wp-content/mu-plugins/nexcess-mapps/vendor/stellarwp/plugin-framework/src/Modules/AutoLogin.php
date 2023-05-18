<?php

namespace StellarWP\PluginFramework\Modules;

use StellarWP\PluginFramework\Exceptions\RequestException;
use StellarWP\PluginFramework\Services\Nexcess\MappsApiClient;
use WP_Error;
use WP_User;

class AutoLogin extends Module
{
    /**
     * @var MappsApiClient The client for connecting to the MAPPS API.
     */
    protected $client;

    /**
     * Construct a new AutoLogin instance.
     *
     * @param MappsApiClient $client
     */
    public function __construct(MappsApiClient $client)
    {
        $this->client = $client;
    }

    /**
     * Perform any necessary setup for the module.
     *
     * @return void
     */
    public function setup()
    {
        add_action('login_init', [ $this, 'checkParameters']);
    }

    /**
     * Check to see if the authentication parameters are present in the request, adding the authenticate filter if so.
     *
     * @return void
     */
    public function checkParameters()
    {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        if (! isset($_GET['token'])) {
            return;
        }

        // 50 will run this after core's main login functions (which run at 30), and before core's spam check filter
        add_filter('authenticate', [ $this, 'validateAutoLogin' ], 50);
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
    public function validateAutoLogin($user)
    {
        // If the user is already set, do not perform login.
        if ($user instanceof WP_User) {
            return $user;
        }

        try {
            // This is not form data, so there is no nonce. It is generated and validated using the MAPPS API.
            // phpcs:ignore WordPress.Security.NonceVerification.Recommended,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
            $request_token = isset($_GET['token']) ? sanitize_text_field(strval(wp_unslash($_GET['token']))) : '';
            $username = $this->client->validateAutoLogin($request_token);
        } catch (RequestException $e) {
            return new WP_Error('login', sprintf('Unexpected error attempting auto login: %s', $e->getMessage()));
        }

        if (! $username) {
            return new WP_Error('login', 'The automatic login token provided was not valid. Please log in again.');
        }

        // This only works for the default user. If they are not present, do nothing.
        $auto_user = get_user_by('login', $username);
        if (! $auto_user instanceof WP_User) {
            return new WP_Error(
                'login',
                sprintf('The %s user is missing, unable to continue automatic login.', $username)
            );
        }

        do_action('stellarwp_autologin');

        return $auto_user;
    }
}
