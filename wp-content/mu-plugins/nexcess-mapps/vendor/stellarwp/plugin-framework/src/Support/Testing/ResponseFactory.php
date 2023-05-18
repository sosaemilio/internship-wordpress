<?php

namespace StellarWP\PluginFramework\Support\Testing;

/**
 * Factory methods for constructing WP HTTP API response arrays.
 *
 * These arrays conform to those returned by {@see \WP_HTTP::request()}
 */
class ResponseFactory
{
    /**
     * Create a WP HTTP response array.
     *
     * @param int                  $code     The HTTP response code.
     * @param string               $body     The HTTP response body.
     * @param Array<string,string> $headers  Response headers.
     * @param Array<string,string> $cookies  Response cookies.
     * @param string               $filename The response filename.
     *
     * @return Array<string,mixed> A response array compatible with the WP HTTP API.
     */
    public static function make($code, $body = '', array $headers = [], array $cookies = [], $filename = '')
    {
        return [
            'response' => [
                'code' => $code,
                'message' => get_status_header_desc($code),
            ],
            'body'     => $body,
            'headers'  => $headers,
            'cookies'  => $cookies,
            'filename' => $filename,
        ];
    }

    /**
     * Create a WP HTTP JSON response array.
     *
     * This behaves the same as self::make(), but JSON-encodes the body.
     *
     * @param int                  $code     The HTTP response code.
     * @param Array<mixed>         $body     The HTTP response body.
     * @param Array<string,string> $headers  Response headers.
     * @param Array<string,string> $cookies  Response cookies.
     * @param string               $filename The response filename.
     *
     * @return Array<string,mixed> A response array compatible with the WP HTTP API.
     */
    public static function makeJson($code, array $body = [], array $headers = [], array $cookies = [], $filename = '')
    {
        $headers = array_merge([
            'Content-Type' => 'application/json',
        ], $headers);

        return self::make($code, (string) wp_json_encode($body), $headers, $cookies, $filename);
    }
}
