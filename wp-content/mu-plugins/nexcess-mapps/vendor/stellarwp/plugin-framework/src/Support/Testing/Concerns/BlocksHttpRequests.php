<?php

namespace StellarWP\PluginFramework\Support\Testing\Concerns;

use StellarWP\PluginFramework\Exceptions\RequestException;

/**
 * Explicitly block all outbound HTTP requests via the WP HTTP API.
 */
trait BlocksHttpRequests
{
    /**
     * @before
     */
    public function blockOutboundHttpRequests()
    {
        add_filter('pre_http_request', function ($preempt, $args, $uri) {
            // This is our last chance to preempt the request, so throw an exception.
            if (false === $preempt) {
                throw new RequestException(
                    sprintf('Blocking outbound HTTP request to %s', $uri)
                );
            }

            return $preempt;
        }, PHP_INT_MAX, 3);
    }
}
