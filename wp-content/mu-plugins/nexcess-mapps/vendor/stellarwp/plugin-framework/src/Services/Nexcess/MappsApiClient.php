<?php

namespace StellarWP\PluginFramework\Services\Nexcess;

use Psr\Log\LoggerInterface;
use StellarWP\PluginFramework\Console\Command;
use StellarWP\PluginFramework\Exceptions\InstallationException;
use StellarWP\PluginFramework\Exceptions\LicensingException;
use StellarWP\PluginFramework\Exceptions\NotFoundException;
use StellarWP\PluginFramework\Exceptions\RequestException;
use StellarWP\PluginFramework\Exceptions\WPErrorException;

class MappsApiClient
{
    /**
     * A cached array of Extensions, keyed by their slugs.
     *
     * @var Array<string,Extension>
     */
    protected $availableExtensions = [];

    /**
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * The site's MAPPS API token.
     *
     * @var string
     */
    protected $token;

    /**
     * The base URI for MAPPS API requests.
     *
     * @var string
     */
    protected $uri;

    /**
     * The cache key used for retrieving available extensions.
     */
    const AVAILABLE_EXTENSIONS_CACHE_KEY = 'stellarwp-installer-extensions';

    /**
     * Construct the API client instance.
     *
     * @param string          $uri    The base URI for the MAPPS plugin API.
     * @param string          $token  The MAPPS API token for this site.
     * @param LoggerInterface $logger The logger instance.
     */
    public function __construct($uri, $token, LoggerInterface $logger)
    {
        $this->uri    = $uri;
        $this->token  = $token;
        $this->logger = $logger;
    }

    /**
     * Retrieve a list of installable plugins and themes.
     *
     * @throws RequestException If an unexpected value was returned from the MAPPS API.
     *
     * @return Array<string,Extension> An array of installable extensions (e.g. plugins and themes),
     *                                 keyed by their slugs.
     */
    public function getAvailableExtensions()
    {
        if (empty($this->availableExtensions)) {
            try {
                /** @var string $body */
                $body = remember_transient(self::AVAILABLE_EXTENSIONS_CACHE_KEY, function () {
                    $response = $this->request('v1/app-plugin');
                    $status   = (int) wp_remote_retrieve_response_code($response);

                    if (200 !== $status) {
                        throw new RequestException(
                            sprintf('Received an unexpected HTTP status code of %d', $status)
                        );
                    }

                    return wp_remote_retrieve_body($response);
                }, 5 * MINUTE_IN_SECONDS);
            } catch (\Exception $e) {
                throw new RequestException($e->getMessage(), $e->getCode(), $e);
            }

            foreach ((array) json_decode($body, true) as $response) {
                $extension = Extension::fromApiResponse((array) $response);

                $this->availableExtensions[ $extension->slug ] = $extension;
            }
        }

        return $this->availableExtensions;
    }

    /**
     * Retrieve a single Extension object by slug.
     *
     * @param string $slug The extension's slug within the MAPPS API.
     *
     * @return Extension|false Either an Extension object representing the extension or false if
     *                         the given slug does not exist among available extensions.
     */
    public function getExtension($slug)
    {
        $extensions = $this->getAvailableExtensions();

        return isset($extensions[$slug]) ? $extensions[$slug] : false;
    }

    /**
     * Retrieve the installation instructions for an extension.
     *
     * @param string $slug The extension slug.
     *
     * @throws NotFoundException         If the given extension could not be found.
     * @throws RequestException          If an unexpected value is returned from the MAPPS API.
     * @throws \InvalidArgumentException If unprocessable data is returned from the MAPPS API.
     *
     * @return Array<Command> An array commands to execute in order to install the extension.
     */
    public function getInstallationSteps($slug)
    {
        $extension = $this->getExtension($slug);

        if (! $extension) {
            throw new NotFoundException(sprintf('Extension "%s" not found', $slug));
        }

        $commands = [];
        $stages   = [
            'pre_install_script',
            'install',
            'post_install_script',
        ];

        try {
            $response = $this->request(sprintf('v1/app-plugin/%d/install', $extension->id));
            $status   = (int) wp_remote_retrieve_response_code($response);

            if (404 === $status) {
                throw new NotFoundException(
                    sprintf('Installation instructions for "%s" could not be found', $extension->name)
                );
            }

            if (200 !== $status) {
                throw new RequestException(
                    sprintf('Received an unexpected HTTP status code of %d', $status)
                );
            }

            $body = (array) json_decode(wp_remote_retrieve_body($response), true);

            // Ignoring these lines w/ PHPStan as isset() is allowed to check nested keys.
            /** @var Array<string,Array<string,mixed>> $steps */
            $steps = isset($body['install_script']['plugin']) // @phpstan-ignore-line
                ? $body['install_script']['plugin']           // @phpstan-ignore-line
                : [];

            // Loop through each stage in order, collecting any commands to be run.
            foreach ($stages as $stage) {
                if (empty($steps[ $stage ])) {
                    continue;
                }

                foreach ($steps[ $stage ] as $action => $value) {
                    if (! is_scalar($value)) {
                        throw new \InvalidArgumentException(sprintf(
                            'Unexepected %s for stage "%s/%s"',
                            gettype($value),
                            $stage,
                            $action
                        ));
                    }

                    switch ($action) {
                        case 'wp_package':
                        case 'wp-package':
                        case 'source':
                            $commands[] = new Command('wp plugin install', [
                                $value,
                                '--activate' => true
                            ]);
                            break;
                        case 'wp_theme':
                            $commands[] = new Command('wp theme install', [
                                $value,
                                '--activate' => true
                            ]);
                            break;
                    }
                }
            }
        } catch (RequestException $e) {
            throw $e;
        } catch (\Exception $e) {
            throw new RequestException($e->getMessage(), $e->getCode(), $e);
        }

        return $commands;
    }

    /**
     * Retrieve the licensing instructions for an extension.
     *
     * @param string $slug The extension slug.
     *
     * @throws NotFoundException If the given extension could not be found.
     * @throws RequestException  If an unexpected value is returned from the MAPPS API.
     *
     * @return Array<Command> An array commands to execute in order to license the extension.
     */
    public function getLicensingSteps($slug)
    {
        $extension = $this->getExtension($slug);

        if (! $extension) {
            throw new NotFoundException(sprintf('Extension "%s" not found', $slug));
        }

        $commands = [];
        $stages   = [
            'pre_licensing_script',
            'licensing_script',
            'post_licensing_script',
        ];

        try {
            $response = $this->request(sprintf('v1/app-plugin/%d/license', $extension->id), [
                'timeout' => 60, // Give a little extra time, as licensing often relies on outside services.
            ]);
            $status   = (int) wp_remote_retrieve_response_code($response);

            if (404 === $status) {
                throw new NotFoundException(
                    sprintf('Licensing instructions for "%s" could not be found', $extension->name)
                );
            }

            if (200 !== $status) {
                throw new RequestException(
                    sprintf('Received an unexpected HTTP status code of %d', $status)
                );
            }

            $body = (array) json_decode(wp_remote_retrieve_body($response), true);

            // Ignoring these lines w/ PHPStan as isset() is allowed to check nested keys.
            /** @var Array<string,Array<string,mixed>> $steps */
            $steps = isset($body['licensing_script']['plugin']) // @phpstan-ignore-line
                ? $body['licensing_script']['plugin']           // @phpstan-ignore-line
                : [];

            // Loop through each stage in order, collecting any commands to be run.
            foreach ($stages as $stage) {
                if (empty($steps[ $stage ])) {
                    continue;
                }

                foreach ($steps[ $stage ] as $action => $value) {
                    switch ($action) {
                        case 'wp_cli':
                            // This is sometimes an array of WP-CLI commands.
                            foreach ((array) $value as $command) {
                                if (! is_string($command)) {
                                    continue;
                                }

                                // Ensure the "wp " prefix is present.
                                if (0 !== mb_strpos(trim($command), 'wp ')) {
                                    $command = 'wp ' . $command;
                                }

                                $commands[] = new Command($command);
                            }
                            break;
                        case 'wp_option':
                            foreach ((array) $value as $key => $val) {
                                $commands[] = new Command('wp option set', [ $key, $val ]);
                            }
                            break;
                    }
                }
            }
        } catch (RequestException $e) {
            throw $e;
        } catch (\Exception $e) {
            throw new RequestException($e->getMessage(), $e->getCode(), $e);
        }

        return $commands;
    }

    /**
     * Install a single extension.
     *
     * @param string $slug The slug of the extension to be installed.
     *
     * @throws InstallationException If the installation request fails.
     *
     * @return self
     */
    public function install($slug)
    {
        try {
            foreach ($this->getInstallationSteps($slug) as $command) {
                $response = $command->execute();
                $response->wasSuccessful(true);

                // Use echo to respect output buffering.
                echo esc_html($response->getOutput());
                fputs(STDERR, $response->getErrors());
            }
        } catch (\Exception $e) {
            throw new InstallationException(esc_html(sprintf(
                'Unable to install extension with slug "%1$s": %2$s',
                $slug,
                $e->getMessage()
            )), $e->getCode(), $e);
        }

        return $this;
    }

    /**
     * License a single extension.
     *
     * @param string $slug The slug of the extension to license.
     *
     * @throws LicensingException If the licensing request fails.
     *
     * @return self
     */
    public function license($slug)
    {
        try {
            foreach ($this->getLicensingSteps($slug) as $command) {
                $response = $command->execute();
                $response->wasSuccessful(true);

                // Use echo to respect output buffering.
                echo esc_html($response->getOutput());
                fputs(STDERR, $response->getErrors());
            }
        } catch (\Exception $e) {
            throw new LicensingException(esc_html(sprintf(
                'Unable to license extension with slug "%1$d": %2$s',
                $slug,
                $e->getMessage()
            )), $e->getCode(), $e);
        }

        return $this;
    }

    /**
     * Purge Nexcess-controlled caches (nginx micro-cache, CDN) for a site.
     *
     * @throws RequestException If the request fails.
     *
     * @return self
     */
    public function purgeCaches()
    {
        try {
            $response = $this->request('v1/site/purge', [
                'method' => 'POST',
            ]);
            $status   = (int) wp_remote_retrieve_response_code($response);

            if (300 <= $status) {
                throw new RequestException(sprintf(
                    'Received unexpected response code (%d) while attempting to purge site caches',
                    $status
                ));
            }
        } catch (RequestException $e) {
            throw $e;
        } catch (\Exception $e) {
            throw new RequestException($e->getMessage(), $e->getCode(), $e);
        }

        return $this;
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
     * Set the domain for a site.
     *
     * Note that this method does *not* do any sort of validation on domains or DNS records!
     *
     * @param string $domain The domain to apply for this site.
     *
     * @throws RequestException If the request fails.
     *
     * @return self
     */
    public function setDomain($domain)
    {
        try {
            $response = $this->request('v1/site/rename', [
                'method' => 'POST',
                'body'   => [
                    'domain' => filter_var($domain, FILTER_SANITIZE_URL),
                ],
            ]);
            $status   = (int) wp_remote_retrieve_response_code($response);

            if (300 <= $status) {
                throw new RequestException(sprintf(
                    'Received unexpected response code (%d) while attempting to change the site domain',
                    $status
                ));
            }
        } catch (RequestException $e) {
            throw $e;
        } catch (\Exception $e) {
            throw new RequestException($e->getMessage(), $e->getCode(), $e);
        }

        return $this;
    }

    /**
     * Validate an automatic login request with the MAPPS API, returning the username if successful.
     *
     * @param string $token The automatic login token generated by NocWorx.
     *
     * @return string The username returned by NocWorx, or an empty string if unsuccessful.
     *
     * @throws RequestException Thrown if the API request fails with a status other than 404 or 422.
     */
    public function validateAutoLogin($token)
    {
        try {
            $response = $this->request('v1/site/sso-verify', [
                'method' => 'POST',
                'body'   => [
                    'token' => esc_js($token), // since this becomes JSON, esc_js works fairly nicely.
                ],
            ]);
            $status   = (int) wp_remote_retrieve_response_code($response);

            if (404 === $status || 422 === $status) {
                return '';
            }
            if (300 <= $status) {
                throw new RequestException(sprintf(
                    'Received unexpected response code (%d) while attempting to validate auto login',
                    $status
                ));
            }

            $body = (array) json_decode(wp_remote_retrieve_body($response), true);
            return isset($body['admin_username']) && is_string($body['admin_username']) ? $body['admin_username'] : '';
        } catch (RequestException $e) {
            throw $e;
        } catch (\Exception $e) {
            throw new RequestException($e->getMessage(), $e->getCode(), $e);
        }
    }

    /**
     * Send a request to the MAPPS API.
     *
     * @param string  $endpoint The API endpoint.
     * @param mixed[] $args     Optional. WP HTTP API arguments, which will be merged with defaults.
     *                          {@link https://developer.wordpress.org/reference/classes/WP_Http/request/#parameters}.
     *
     * @throws WPErrorException If an error occurs making the request.
     *
     * @return Array<string,mixed> An array containing the following keys: 'headers', 'body', 'response', 'cookies',
     *                             and 'filename'. This is the same as {@see \WP_HTTP::request()}
     */
    protected function request($endpoint, $args = [])
    {
        $response = wp_remote_request(
            $this->route($endpoint),
            array_replace_recursive([
                'user-agent' => 'StellarWP/PluginFramework',
                'timeout'    => 30,
                'headers'    => [
                    'Accept'        => 'application/json',
                    'X-MAAPI-TOKEN' => $this->token,
                ],
            ], $args)
        );

        if (is_wp_error($response)) {
            throw new WPErrorException($response);
        }

        return $response;
    }
}
