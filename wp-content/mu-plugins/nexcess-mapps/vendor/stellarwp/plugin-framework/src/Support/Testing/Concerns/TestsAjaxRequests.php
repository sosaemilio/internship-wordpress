<?php

namespace StellarWP\PluginFramework\Support\Testing\Concerns;

use AssertWell\PHPUnitGlobalState\GlobalVariables;
use StellarWP\PluginFramework\Support\AjaxResponse;
use StellarWP\PluginFramework\Support\Buffer;
use Tests\Exceptions\AjaxDieHandlerException;
use WP_User;

/**
 * Help with the testing of WP AJAX.
 */
trait TestsAjaxRequests
{
    use GlobalVariables;

    /**
     * @before
     */
    protected function setAjaxDieHandler()
    {
        add_filter('wp_die_ajax_handler', [$this, 'ajaxReturnHook']);
        add_filter('wp_die_ajax_handler', [$this, 'ajaxRequestExceptionThrower']);
    }

    /**
     * @after
     */
    protected function removeAjaxDieHandler()
    {
        remove_filter('wp_doing_ajax', '__return_true');
        remove_filter('wp_die_ajax_handler', [$this, 'ajaxReturnHook']);
        remove_filter('wp_die_ajax_handler', [$this, 'ajaxRequestExceptionThrower']);
    }

    /**
     * @return callable
     */
    public function ajaxReturnHook()
    {
        return function ($message) {
            // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
            echo $message;
        };
    }

    /**
     * @return callable
     */
    public function ajaxRequestExceptionThrower()
    {
        return [$this, 'stopAjaxRequestHandling'];
    }

    /**
     * Stop AJAX requests by throwing an exception.
     *
     * @param string       $message Error message.
     * @param string       $title   Optional. Error title (unused). Default empty.
     * @param string|array $args    Optional. Arguments to control behavior. Default empty array.
     *
     * @throws AjaxDieHandlerException Force an exception which will stop execution and allow us to pick up any output.
     */
    public function stopAjaxRequestHandling($message, $title = '', $args = [])
    {
        throw new AjaxDieHandlerException();
    }

    /**
     * Emulate an HTTP POST request to admin-ajax.php.
     *
     * Note that this method will check for the currently logged-in user: if you need to test a
     * callback on wp_ajax_{$action}, you should use the $user argument or explicitly set the
     * current user yourself:
     *
     *     wp_set_current_user($this->factory->user->create( [
     *         'role' => 'administrator',
     *     ]));
     *
     * @param string       $action The action name.
     * @param Array<mixed> $body   The request body.
     * @param WP_User      $user   Optional. A WP_User instance to set as the current user.
     *                             Default is null (no user).
     *
     * @return AjaxResponse
     */
    protected function postAjax($action, array $body, WP_User $user = null)
    {
        $this->setGlobalVariable('_POST', $body);

        if ($user) {
            wp_set_current_user($user);
        }

        add_filter('wp_doing_ajax', '__return_true');

        /*
         * Capture the status header.
         *
         * Note that wp_send_json() will only attempt to set the HTTP status header if
         * headers_sent() is false, so this will often remain "0".
         */
        $code = 0;

        add_filter('status_header', function ($status) use (&$code) {
            return $code = $status; // phpcs:ignore Squiz.PHP.DisallowMultipleAssignments.Found
        }, PHP_INT_MAX);

        $buffer = (new Buffer(function () use ($action) {
            if (is_user_logged_in()) {
                do_action('wp_ajax_' . $action);
            } else {
                do_action('wp_ajax_nopriv_' . $action);
            }
        }))->run();

        return new AjaxResponse($code, $buffer->getOutput());
    }
}
