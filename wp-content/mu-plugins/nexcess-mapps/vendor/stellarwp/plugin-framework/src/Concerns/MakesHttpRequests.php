<?php

namespace StellarWP\PluginFramework\Concerns;

use StellarWP\PluginFramework\Exceptions\WPErrorException;
use WP_Error;

trait MakesHttpRequests
{
    /**
     * Validate a response.
     *
     * Rather than filling every request method with a bunch of is_wp_error() checks, pass a
     * request response and perform all the checks and, assuming everything's cool, return the body.
     *
     * @param mixed[]|WP_Error $response The return value from wp_remote_request().
     * @param int              $code     Optional. The expected/required response code. Default is 200.
     *
     * @throws \StellarWP\PluginFramework\Exceptions\WPErrorException If a WP_Error object is encountered.
     *
     * @return string The response body, as long as all of the checks pass.
     */
    protected function validateHttpResponse($response, $code = 200)
    {
        if (is_wp_error($response)) {
            throw new WPErrorException($response);
        }

        $response_code = (int) wp_remote_retrieve_response_code($response);

        if ((int) $code !== $response_code) {
            throw new WPErrorException(new WP_Error(
                $response_code,
                sprintf('Expected response code of %1$d, received %2$d.', $code, $response_code)
            ));
        }

        return wp_remote_retrieve_body($response);
    }
}
