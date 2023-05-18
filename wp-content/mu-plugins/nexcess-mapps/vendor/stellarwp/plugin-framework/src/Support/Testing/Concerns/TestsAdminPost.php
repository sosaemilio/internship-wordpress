<?php

namespace StellarWP\PluginFramework\Support\Testing\Concerns;

use AssertWell\PHPUnitGlobalState\GlobalVariables;
use WP_User;

/**
 * Helpers for testing code that uses admin-post.php.
 */
trait TestsAdminPost
{
    use GlobalVariables;

    /**
     * Emulate a POST request to WP-Admin.
     *
     * @param string              $action The action name.
     * @param Array<string,mixed> $body   Optional. The $_POST body. Default is empty.
     * @param WP_User             $user   Optional. A WP_User instance to set as the current user.
     *                                    Default is null (no user).
     */
    protected function postAdminAction($action, array $body = [], WP_User $user = null)
    {
        $this->setGlobalVariable('_POST', array_merge($_POST, $body, [
            'action' => $action,
        ]));
        $this->setGlobalVariable('_REQUEST', $_POST);

        if (! get_current_screen()) {
            set_current_screen('index');
        }

        if ($user) {
            wp_set_current_user($user);
        }

        do_action('admin_action_' . $action);
    }

    /**
     * Emulate a POST request to admin-post.php.
     *
     * @param string              $action The action name.
     * @param Array<string,mixed> $body   Optional. The $_POST body. Default is empty.
     * @param WP_User             $user   Optional. A WP_User instance to set as the current user.
     *                                    Default is null (no user).
     */
    protected function postAdminPost($action, array $body = [], WP_User $user = null)
    {
        $this->setGlobalVariable('_POST', array_merge($_POST, $body, [
            'action' => $action,
        ]));
        $this->setGlobalVariable('_REQUEST', $_POST);

        if ($user) {
            wp_set_current_user($user);
        }

        do_action('admin_post_' . $action);
    }
}
