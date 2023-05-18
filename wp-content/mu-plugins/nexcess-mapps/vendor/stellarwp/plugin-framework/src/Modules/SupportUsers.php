<?php

namespace StellarWP\PluginFramework\Modules;

use StellarWP\PluginFramework\Contracts\ProvidesSettings;
use StellarWP\PluginFramework\Contracts\ProvidesSupportUsers;
use StellarWP\PluginFramework\Exceptions\DatabaseException;
use StellarWP\PluginFramework\Exceptions\WPErrorException;
use StellarWP\PluginFramework\Services\Managers\CronEventManager;
use StellarWP\PluginFramework\Support\CronEvent;
use WP_Error;
use WP_Query;
use WP_User;

/**
 * The basic definition for a module.
 */
class SupportUsers extends Module implements ProvidesSupportUsers
{
    /**
     * The name of the daily cron event for cleaning up expired support users.
     */
    const MAINTENANCE_CRON_HOOK = 'stellarwp_clean_support_users';

    /**
     * The meta key used to store support user expiration details.
     */
    const USER_META_KEY = '_stellarwp_expires_at';

    /**
     * How long (in seconds) support users should exist before expiring.
     */
    const USER_EXPIRES_IN = DAY_IN_SECONDS;

    /**
     * The cron event manager.
     *
     * @var CronEventManager
     */
    protected $cronManager;

    /**
     * @var ProvidesSettings
     */
    protected $settings;

    /**
     * Construct a new instance of the SupportUsers module.
     *
     * @param CronEventManager $cronManager The cron event manager.
     * @param ProvidesSettings $settings Plugin framework settings.
     */
    public function __construct(CronEventManager $cronManager, ProvidesSettings $settings)
    {
        $this->settings    = $settings;
        $this->cronManager = $cronManager;
    }

    /**
     * {@inheritDoc}
     */
    public function setup()
    {
        add_action('wp_head', [$this, 'blockRobots'], 0);
        add_action('wp_login', [$this, 'extendSupportUserExpiration'], 10, 2);

        // Register the cron event to clean up expired users once a day.
        $this->cronManager->register(static::MAINTENANCE_CRON_HOOK, CronEvent::DAILY, current_datetime());
        // @phpstan-ignore-next-line Function is also used directly, so we're ok with it having a return.
        add_action(static::MAINTENANCE_CRON_HOOK, [$this, 'removeExpiredUsers']);

        add_filter('authenticate', [$this, 'handleAuthentication'], 0, 2);
        add_filter('author_link', [$this, 'replaceAuthorLink'], 10, 2);
    }

    /**
     * {@inheritDoc}
     */
    public function all()
    {
        return get_users([
            'blog_id'      => 0,
            'meta_key'     => static::USER_META_KEY,
            'meta_compare' => 'EXISTS',
            'fields'       => 'all',
        ]);
    }

    /**
     * Explicitly block robots from indexing support users.
     *
     * @return void
     */
    public function blockRobots()
    {
        /** @var WP_Query $wp_query */
        global $wp_query;

        // Not an author archive.
        if (! $wp_query->is_author()) {
            return;
        }

        /** @var WP_USER $author */
        $author = $wp_query->get_queried_object();

        // Not a support user.
        if (empty(get_user_meta($author->ID, static::USER_META_KEY, true))) {
            return;
        }

        /*
         * Explicitly block robots from indexing this archive.
         *
         * This will use the WP Robots API for WordPress 5.7+, but fall back to printing a meta tag
         * for older versions.
         */
        if (function_exists('wp_robots') && function_exists('wp_robots_no_robots')) {
            add_filter('wp_robots', 'wp_robots_no_robots');
        } else {
            echo '<meta name="robots" content="noindex" />';
        }
    }

    /**
     * {@inheritDoc}
     *
     * @throws DatabaseException If the newly-created user cannot be retrieved from the database.
     * @throws WPErrorException  If an error occurs creating the new user.
     *
     * @param array<string, mixed> $args Arguments provided.
     * @param ?int $expires_in Expiration number in seconds.
     *
     * @return WP_User
     */
    public function create(array $args = [], $expires_in = null)
    {
        $args = wp_parse_args($args, $this->getDefaultUserAttributes());

        if (null === $expires_in) {
            $expires_in = static::USER_EXPIRES_IN;
        }

        // Make sure there's always a password set.
        if (empty($args['user_pass'])) {
            $args['user_pass'] = wp_generate_password();
        }

        // Don't let an ID explicitly be set, as we should not be updating existing users.
        unset($args['ID']);

        // Explicitly grant the support user administrator privileges.
        $args['role'] = 'administrator';

        $user_id = wp_insert_user($args);

        if (is_wp_error($user_id)) {
            throw new WPErrorException($user_id);
        }

        /*
         * WordPress 5.9 introduced $args['meta_input'], which accepts an array of meta keys to set automatically.
         *
         * Once WordPress < 5.9 no longer needs to be supported, this can be included in the $args array.
         */
        update_user_option($user_id, static::USER_META_KEY, time() + $expires_in, true);

        // Grant the user super-admin privileges on multisite instances.
        if (is_multisite()) {
            grant_super_admin($user_id);
        }

        $user = get_user_by('id', $user_id);

        if (! $user) {
            throw new DatabaseException(sprintf(
                'Unable to retrieve the newly-created user with ID %d',
                $user_id
            ));
        }

        return $user;
    }

    /**
     * {@inheritDoc}
     */
    public function expired()
    {
        return get_users([
            'blog_id'      => 0,
            'meta_key'     => static::USER_META_KEY,
            'meta_compare' => '<=',
            'meta_value'   => strval(time()),
            'fields'       => 'all',
        ]);
    }

    /**
     * Extend a support user's expiration time upon login.
     *
     * @param string  $user_login The user login.
     * @param WP_User $user       The WP_User object.
     *
     * @return void
     */
    public function extendSupportUserExpiration($user_login, WP_User $user)
    {
        if (empty(get_user_meta($user->ID, static::USER_META_KEY, true))) {
            return;
        }

        update_user_option($user->ID, static::USER_META_KEY, time() + static::USER_EXPIRES_IN, true);
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
    public function handleAuthentication($user, $username)
    {
        if (! $username) {
            return $user;
        }

        $user_object = get_user_by('login', $username);

        if (! $user_object instanceof WP_User) {
            return $user;
        }

        $expiration = get_user_meta($user_object->ID, static::USER_META_KEY, true);

        if (! empty($expiration) && $expiration <= time()) {
            $this->deleteUser($user_object->ID);

            return new WP_Error(
                'stellarwp-user-expired',
                __('This support user has expired and is no longer available.', 'stellarwp-framework'),
                [
                    'user_login' => $username,
                    'expired_at' => $expiration,
                ]
            );
        }

        return $user;
    }

    /**
     * {@inheritDoc}
     */
    public function removeExpiredUsers()
    {
        if (! function_exists('wp_delete_user')) {
            require_once ABSPATH . 'wp-admin/includes/user.php';
        }

        $removed = [];

        foreach ($this->expired() as $user) {
            if ($this->deleteUser($user->ID)) {
                $removed[ (int) $user->ID ] = (string) $user->user_login;
            }
        }

        return $removed;
    }

    /**
     * Filter the "author_link" to avoid sending traffic to an author archive for a support user.
     *
     * @param string $link    The author posts link.
     * @param int    $user_id The user ID.
     *
     * @return string $link The filtered posts link.
     */
    public function replaceAuthorLink($link, $user_id)
    {
        if (empty(get_user_meta($user_id, static::USER_META_KEY, true))) {
            return $link;
        }

        $user = get_user_by('id', $user_id);

        return $user instanceof WP_User && ! empty($user->user_url)
            ? $user->user_url
            : $link;
    }

    /**
     * Retrieve default arguments for creating new support users.
     *
     * @return Array<string,scalar>
     */
    protected function getDefaultUserAttributes()
    {
        $uniqid = uniqid();

        return [
            // phpcs:ignore Generic.Files.LineLength.TooLong
            'description'   => 'This is a temporary user created by StellarWP support, and will automatically be removed after 24 hours of inactivity.',
            'display_name'  => 'StellarWP Support',
            'first_name'    => 'StellarWP',
            'last_name'     => 'Support',
            'nickname'      => 'StellarWP Support',
            'use_ssl'       => true,
            'user_email'    => sprintf('devnull+%s@nexcess.net', $uniqid),
            'user_login'    => 'stellarwp_support_' . $uniqid,
            'user_url'      => $this->settings->support_url,
        ];
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
    protected function deleteUser($user_id)
    {
        if (is_multisite()) {
            if (! function_exists('wpmu_delete_user')) {
                require_once ABSPATH . 'wp-admin/includes/ms.php';
            }

            revoke_super_admin($user_id);
            return wpmu_delete_user($user_id);
        }

        if (! function_exists('wp_delete_user')) {
            require_once ABSPATH . 'wp-admin/includes/user.php';
        }

        return wp_delete_user($user_id);
    }
}
