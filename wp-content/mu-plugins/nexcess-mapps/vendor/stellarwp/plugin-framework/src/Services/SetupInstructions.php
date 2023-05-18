<?php

namespace StellarWP\PluginFramework\Services;

use Psr\Log\LoggerInterface;
use StellarWP\PluginFramework\Console\Command;
use StellarWP\PluginFramework\Exceptions\RequestException;
use StellarWP\PluginFramework\Exceptions\SignatureVerificationFailedException;
use StellarWP\PluginFramework\Exceptions\WPErrorException;
use StellarWP\PluginFramework\Settings;
use StellarWP\PluginFramework\Support\InstructionSet;
use StellarWP\PluginFramework\Support\Url;

/**
 * Handles retrieving and executing setup instructions from the StellarWP Partner Gateway.
 */
class SetupInstructions
{
    /**
     * The Logger instance.
     *
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * The settings object.
     *
     * @var Settings
     */
    protected $settings;

    /**
     * The cache key used for storing instructions.
     */
    const CACHE_KEY = '_stellarwp_setup_instructions';

    /**
     * Construct a new instance of the SetupInstructions service.
     *
     * @param Settings        $settings The settings object.
     * @param LoggerInterface $logger   A logger implementation.
     */
    public function __construct(Settings $settings, LoggerInterface $logger)
    {
        $this->settings = $settings;
        $this->logger   = $logger;
    }

    /**
     * Retrieve instructions for building the site.
     *
     * @return InstructionSet Instructions for setting up the site.
     */
    public function getInstructions()
    {
        $instructions = new InstructionSet();
        $response     = $this->fetchInstructionsFromGateway();
        $tmpFiles     = [];
        $tmpPrefix    = sprintf('%s/%s-', sys_get_temp_dir(), uniqid('stellarwp-'));

        foreach ($response['temp_files'] as $file) {
            if (! is_scalar($file)) {
                $this->logger->warning('Unexpected temp file format, skipping', [
                    'temp_file' => $file,
                ]);

                continue;
            }

            $filename = $tmpPrefix . basename((string) $file);

            $instructions->addCommand(new Command('curl', [
                $file,
                '--output' => $filename,
            ]));
            $tmpFiles[] = $filename;
        }

        foreach ($response['wp_cli'] as $command) {
            if (! is_scalar($command)) {
                $this->logger->warning('Unexpected setup command format, skipping', [
                    'command' => $command,
                ]);

                continue;
            }

            $command = (string) $command;

            // Since these are all WP-CLI commands, they should *all* begin with "wp".
            if (0 !== mb_strpos($command, 'wp ')) {
                $command = 'wp ' . $command;
            }

            // Expand placeholders.
            $command = str_replace('%temp_prefix%', $tmpPrefix, $command);

            $instructions->addCommand(new Command($command));
        }

        if (! empty($tmpFiles)) {
            $instructions->addCommand(new Command('rm', $tmpFiles));
        }

        return $instructions;
    }

    /**
     * Retrieve setup instructions from the Partner Gateway.
     *
     * @return array{temp_files: Array<mixed>, wp_cli: Array<mixed>} The body of the setup API response.
     */
    protected function fetchInstructionsFromGateway()
    {
        try {
            /** @var Array<string,Array<string,mixed>> $setup */
            $setup = wp_cache_remember(self::CACHE_KEY, function () {
                if (empty($this->settings->partner_gateway_id)) {
                    throw new RequestException(sprintf(
                        'Unable to call the Partner Gateway, as no ID is set.'
                    ));
                }

                $url      = sprintf(
                    '%s/v1/site/%s/setup',
                    untrailingslashit($this->settings->partner_gateway_endpoint),
                    $this->settings->partner_gateway_id
                );
                $response = wp_remote_get($url);

                if (is_wp_error($response)) {
                    throw new WPErrorException($response);
                }

                // First, make sure we have an appropriate response with a body.
                if (200 !== $code = (int) wp_remote_retrieve_response_code($response)) {
                    throw new RequestException(sprintf(
                        'Received an unexpected error code (%d) from the Partner Gateway (URL: %s)',
                        $code,
                        $url
                    ));
                }

                $body = wp_remote_retrieve_body($response);

                if (empty($body)) {
                    throw new RequestException('Received an empty response body');
                }

                // Now that we have a non-empty body, verify the signature.
                if (empty($this->settings->partner_gateway_public_key)) {
                    throw new SignatureVerificationFailedException(
                        'No Partner Gateway public API key has been set, unable to verify signature.'
                    );
                }

                if (! function_exists('sodium_crypto_sign_verify_detached')) {
                    throw new SignatureVerificationFailedException(
                        'Function sodium_crypto_sign_verify_detached() does not exist.'
                    );
                }

                if (! $signature = current((array) wp_remote_retrieve_header($response, 'Signature'))) {
                    throw new SignatureVerificationFailedException(
                        'Unable to find a valid Signature header on the Partner Gateway response.'
                    );
                }

                /** @phpstan-assert non-empty-string $signature */
                if (! $signature = Url::base64Urldecode($signature)) {
                    throw new SignatureVerificationFailedException(
                        'Unable to find a valid Signature header on the Partner Gateway response.'
                    );
                }

                $verified = sodium_crypto_sign_verify_detached(
                    $signature,
                    $body,
                    $this->settings->partner_gateway_public_key
                );

                if (! $verified) {
                    throw new SignatureVerificationFailedException(
                        'The site details did not match the response signature.'
                    );
                }

                // Once verified, decode the JSON and return it.
                $decoded = json_decode($body, true);

                if (! is_array($decoded) || ! isset($decoded['data'])) {
                    throw new RequestException('Received a malformed response body');
                }

                return $decoded['data'];
            }, 'stellarwp', HOUR_IN_SECONDS);
        } catch (\Exception $e) {
            $this->logger->error('Unable to retrieve setup instructions from the StellarWP Partner Gateway', [
                'exception' => $e,
            ]);
        }

        $instructions = [
            'temp_files' => [],
            'wp_cli'     => [],
        ];

        foreach ($instructions as $key => $value) {
            if (isset($setup['setup'][$key])) {
                $instructions[$key] = array_merge($value, (array) $setup['setup'][$key]);
            }
        }

        return $instructions;
    }
}
