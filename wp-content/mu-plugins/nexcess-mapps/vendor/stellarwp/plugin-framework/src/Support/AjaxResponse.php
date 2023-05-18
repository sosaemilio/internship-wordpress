<?php

namespace StellarWP\PluginFramework\Support;

use WP_Error;

/**
 * A wrapper around WP Ajax responses.
 */
class AjaxResponse
{
    /**
     * The response body.
     *
     * @var string
     */
    protected $body;

    /**
     * The HTTP status code.
     *
     * @var int
     */
    protected $code;

    /**
     * Construct a new AjaxResponse instance.
     *
     * @param int    $code The HTTP status code.
     * @param string $body The response body.
     */
    public function __construct($code, $body)
    {
        $this->code = $code;
        $this->body = $body;
    }

    /**
     * Retrieve the response body.
     *
     * @return string
     */
    public function getBody()
    {
        return $this->body;
    }

    /**
     * Retrieve the status code.
     *
     * @return int
     */
    public function getCode()
    {
        return $this->code;
    }

    /**
     * Reconstruct a WP_Error object.
     *
     * If {@see wp_send_json_error()} is given a WP_Error object, it will break out each of the
     * errors and return an array of arrays, in the form of:
     *
     *     [
     *         'data' => [
     *             [
     *                 'code'    => 'error_code',
     *                 'message' => 'The error message',
     *             ],
     *
     *             // More errors here.
     *         ]
     *     ]
     *
     * This method will look for this pattern and, if found, add these errors into a new WP_Error object.
     *
     * Be aware that while this method will always return a WP_Error object, that doesn't mean that
     * errors were detected! You may check for the presence of errors via {@see WP_Error::has_errors()}:
     *
     *     if ($response->getErrors()->has_errors()) {
     *         // Errors were returned in the response.
     *     }
     *
     * @return WP_Error A WP_Error object containing any errors that were found or an empty WP_Error
     *                  object if no errors were detected.
     */
    public function getErrors()
    {
        $errors = new WP_Error();
        $json   = $this->getJson();
        $data   = is_object($json) && isset($json->data) ? $json->data : [];

        foreach ((array) $data as $error) {
            if (! isset($error->code, $error->message)) {
                continue;
            }

            $errors->add($error->code, $error->message);
        }

        return $errors;
    }

    /**
     * Retrieve the JSON-decoded response body.
     *
     * @param bool $assoc Optional. Decode to an associative array instead of an object?
     *                    Default is false.
     *
     * @return mixed
     */
    public function getJson($assoc = false)
    {
        return json_decode($this->body, $assoc);
    }
}
