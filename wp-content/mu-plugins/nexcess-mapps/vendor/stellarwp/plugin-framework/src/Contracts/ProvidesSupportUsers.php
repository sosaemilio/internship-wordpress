<?php

namespace StellarWP\PluginFramework\Contracts;

use WP_User;

/**
 * The interface for a SupportUsers implementation.
 */
interface ProvidesSupportUsers
{
    /**
     * Collect an array of all current support users.
     *
     * @return Array<WP_User>
     */
    public function all();

    /**
     * Create a new support user on the current site.
     *
     * @param Array<string,mixed> $args       Optional. Arguments to pass to {@see wp_insert_user()}.
     *                                        Default is empty.
     * @param ?int                $expires_in Optional. The number of seconds from now until the
     *                                        support user should expire. Default the value of the
     *                                        static::USER_EXPIRES_IN class constant.
     *
     * @return WP_User
     */
    public function create(array $args = [], $expires_in = null);

    /**
     * Collect an array of all expired support users.
     *
     * @return Array<WP_User>
     */
    public function expired();

    /**
     * Remove all expired support users from the WordPress installation.
     *
     * @return Array<int,string> An array of user logins that have been removed, keyed by their ID.
     */
    public function removeExpiredUsers();
}
