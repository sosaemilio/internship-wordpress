<?php

namespace StellarWP\PluginFramework\Services\Nexcess;

use Psr\Log\LoggerInterface;
use StellarWP\PluginFramework\Exceptions\RequestException;
use StellarWP\PluginFramework\Exceptions\WPErrorException;

class Telemetry
{
    /**
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * The Telemetry API token.
     *
     * @var string
     */
    protected $token;

    /**
     * The base URI for the Plugin API requests.
     *
     * @var string
     */
    protected $uri;

    /**
     * Construct the API client instance.
     *
     * @param string          $uri    The base URI for the Plugin Telemetry API.
     * @param string          $token  The Telemetry API token.
     * @param LoggerInterface $logger The logger instance.
     */
    public function __construct($uri, $token, LoggerInterface $logger)
    {
        $this->uri    = $uri;
        $this->token  = $token;
        $this->logger = $logger;
    }

    /**
     * Construct the full URI to an API route.
     *
     * @param string $route The API endpoint.
     *
     * @return string The absolute URI for this route.
     */
    public function route($route = '/')
    {
        // Strip leading slashes.
        if (0 === mb_strpos($route, '/')) {
            $route = mb_substr($route, 1);
        }

        return esc_url_raw(sprintf('%s/api/%2$s', $this->uri, $route));
    }

    /**
     * Send a Site Report to the Telemetry API.
     *
     * @param array<mixed> $report The gathered telemetry report data.
     *
     * @throws RequestException If the request fails.
     *
     * @return bool
     */
    public function sendReport($report)
    {
        // Add the key to the report in the method the Telemetry API is expecting.
        $report['key'] = $this->token;

        try {
            // We're not going to check for a response code because we are using
            // 'blocking' = false. This means the request is sent and we don't
            // wait for the response, so there will be nothing in it.
            $this->request('site_report', [
                'blocking' => false,
                'method'   => 'POST',
                'timeout'  => 900,
                'body'     => wp_json_encode($report),
            ]);
        } catch (RequestException $e) {
            throw $e;
        } catch (\Exception $e) {
            throw new RequestException($e->getMessage(), $e->getCode(), $e);
        }

        return true;
    }

    /**
     * Send a request to the Telemetry API.
     *
     * @param string              $endpoint The API endpoint.
     * @param Array<string,mixed> $args     Optional. WP HTTP API arguments, which will be merged with defaults.
     *                            {@link https://developer.wordpress.org/reference/classes/WP_Http/request/#parameters}.
     *
     * @throws WPErrorException    If an error occurs making the request.
     *
     * @return Array<string,mixed> An array containing the following keys: 'headers', 'body', 'response', 'cookies',
     *                             and 'filename'. This is the same as {@see \WP_HTTP::request()}
     */
    protected function request($endpoint, $args = [])
    {
        $response = wp_remote_request(
            $this->route($endpoint),
            array_replace_recursive([
                'headers'    => [
                    'Accept'       => 'application/json',
                    'Content-Type' => 'application/json',
                ],
                'user-agent' => 'StellarWP/PluginFramework',
            ], $args)
        );

        if (is_wp_error($response)) {
            throw new WPErrorException($response);
        }

        return $response;
    }
}
