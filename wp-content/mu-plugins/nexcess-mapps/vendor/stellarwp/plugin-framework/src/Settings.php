<?php

namespace StellarWP\PluginFramework;

use StellarWP\PluginFramework\Console\Command;
use StellarWP\PluginFramework\Console\Concerns\HasShellDependencies;
use StellarWP\PluginFramework\Contracts\ProvidesSettings;
use StellarWP\PluginFramework\Exceptions\ConfigurationException;
use StellarWP\PluginFramework\Exceptions\ConsoleException;
use StellarWP\PluginFramework\Support\Url;

/**
 * A global settings object that can retrieve details about a site from its environment.
 *
 * @property-read int     $account_id                  The cloud account (site) ID.
 * @property-read int     $client_id                   The customer's client ID.
 * @property-read string  $dns_help_url                A support URL with details for configuring DNS.
 * @property-read string  $feature_flags_url           The endpoint to retrieve feature flags details.
 * @property-read string  $framework_path              The absolute system path to the root of the framework directory.
 * @property-read string  $framework_url               The URL (with trailing slash) to the framework codebase.
 * @property-read bool    $has_custom_domain           True if the site is using a domain other than the temp_domain or
 *                                                     vanity_domain assigned to it by the server.
 * @property-read string  $mapps_api_token             The site's unique API token for the Nexcess MAPPS API.
 * @property-read string  $mapps_api_url               The base URL for the Nexcess MAPPS API.
 * @property-read string  $partner_gateway_endpoint    The StellarWP Partner Gateway API endpoint.
 * @property-read string  $partner_gateway_id          The UUID of this site within the StellarWP Partner Gateway.
 * @property-read string  $partner_gateway_public_key  The public signing key for the StellarWP Partner Gateway.
 * @property-read int     $plan_id                     The site plan's ID.
 * @property-read string  $plan_name                   The plan code, based on the $package_label.
 * @property-read string  $platform_prefix             The prefix for the settings.
 * @property-read string  $plugin_version              The current plugin version.
 * @property-read string  $php_version                 The current PHP version, in the form of "<major>.<minor>".
 * @property-read ?string $redis_host                  The Redis hostname for this site.
 * @property-read ?int    $redis_port                  The port number used by Redis for this site.
 * @property-read int     $service_id                  The site's service ID.
 * @property-read string  $telemetry_key               API key for the plugin reporter (telemetry).
 * @property-read string  $telemetry_reporter_endpoint Endpoint used to report telemetry data.
 * @property-read string  $temp_domain                 The (internal) temporary domain assigned to this site.
 * @property-read string  $vanity_domain               The more user-friendly, temporary "vanity" domain assigned to
 *                                                     this site.
 */
class Settings implements ProvidesSettings
{
    use HasShellDependencies;

    /**
     * Overrides to settings.
     *
     * @var Array<string,mixed>
     */
    protected $overrides;

    /**
     * A cache of all registered settings.
     *
     * @var Array<string,mixed>
     */
    protected $settings;

    /**
     * The transient key for SiteWorx's cache.
     */
    const SITEWORX_CACHE_KEY = '_stellarwp_siteworx_cache';

    const PREFIX_ENV_VAR = 'siteworx_';

    /**
     * Construct a new Settings instance.
     *
     * @param Array<string,mixed> $overrides Optional. Overrides to the default settings. Default is empty.
     */
    public function __construct(array $overrides = [])
    {
        $this->overrides = $overrides;
    }

    /**
     * Enable settings to be accessed as properties.
     *
     * @param string $setting The setting being requested.
     *
     * @return mixed The value of the given setting.
     */
    public function __get($setting)
    {
        return $this->get($setting);
    }

    /**
     * Enable functions like isset() and empty() to work with dynamic properties.
     *
     * @param string $setting The property name.
     *
     * @return bool True if the setting exists on $this->settings, false otherwise.
     */
    public function __isset($setting)
    {
        return isset($this->load()->settings[$setting]);
    }

    /**
     * Enable settings to be accessed as properties.
     *
     * @param string $property The property being set.
     * @param mixed  $value    The value being assigned to the property.
     *
     * @throws ConfigurationException As all settings should be treated as immutable.
     *
     * @return void
     */
    public function __set($property, $value)
    {
        throw new ConfigurationException(
            sprintf(
                /* Translators: %1$s is the property name. */
                esc_html('Setting %1$s may not be modified'),
                $property
            )
        );
    }

    /**
     * Retrieve all settings.
     *
     * @return Array<string,mixed> All registered settings.
     */
    public function all()
    {
        return $this->load()->settings;
    }

    /**
     * Retrieve a setting by its key.
     *
     * @param string $key     The setting key to look for.
     * @param mixed  $default Optional. The default value to return if the key is not defined in the
     *                        $settings array. Default is null.
     *
     * @return mixed The value of the setting, or the value of $default if not provided.
     */
    public function get($key, $default = null)
    {
        if (! array_key_exists($key, $this->load()->settings)) {
            return is_callable($default) ? $default() : $default;
        }

        $value = $this->settings[$key];

        if (is_callable($value)) {
            $value = $value();

            // Cache the resolved version.
            $this->settings[$key] = $value;
        }

        return $value;
    }

    /**
     * Load all settings.
     *
     * @return self
     */
    public function load()
    {
        if (empty($this->settings)) {
            /*
             * If the user has specified an environment type, we should respect that.
             *
             * The environment type may be set in two ways:
             * 1. Via the WP_ENVIRONMENT_TYPE environment variable.
             * 2. By defining the WP_ENVIRONMENT_TYPE constant.
             */
            $environment_type = ! empty(getenv('WP_ENVIRONMENT_TYPE')) || defined('WP_ENVIRONMENT_TYPE')
                ? wp_get_environment_type()
                : $this->getSiteWorxSetting('app_environment', 'production');

            $defaults = [
                // phpcs:ignore Generic.Files.LineLength.TooLong
                'account_id'                     => intval($this->getSiteWorxSetting('account_id')),
                'admin_menu_slug'                => 'stellarwp',
                'client_id'                      => intval($this->getSiteWorxSetting('client_id')),
                'company_name'                   => 'StellarWP',
                'core_updates_enabled'           => (bool) $this->getSiteWorxSetting('app_updates_core', false),
                // phpcs:ignore Generic.Files.LineLength.TooLong
                'dns_help_url'                 => 'https://help.nexcess.net/74095-wordpress/how-to-edit-or-add-an-a-host-dns-record-to-go-live-with-your-site',
                'feature_flags_url'            => $this->getSiteWorxSetting(
                    'feature_flags_url',
                    'https://feature-flags.nexcess-services.com'
                ),
                'framework_path'               => dirname(__DIR__),
                'framework_url'                => '/',
                'mapps_api_token'              => $this->getSiteWorxSetting('mapp_token'),
                'mapps_api_url'                => $this->getSiteWorxSetting('mapp_endpoint'),
                'partner_gateway_endpoint'     => $this->getSiteWorxSetting('quickstart_endpoint'),
                'partner_gateway_id'           => $this->getSiteWorxSetting('quickstart_uuid'),
                'partner_gateway_public_key'   => function () {
                    $key = $this->getSiteWorxSetting('quickstart_public_id');

                    // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode
                    return is_scalar($key) ? base64_decode((string) $key) : '';
                },
                'php_version'                  => PHP_MAJOR_VERSION . '.' . PHP_MINOR_VERSION,
                'plan_id'                      => intval($this->getSiteWorxSetting('service_id')),
                'plan_name'                    => $this->getSiteWorxSetting('package_name', false),
                'platform_prefix'              => 'stellarwp',
                'plugin_version'               => time(),
                'redis_host'                   => $this->getSiteWorxSetting('redis_host', null),
                'redis_port'                   => function () {
                    $port = $this->getSiteWorxSetting('redis_port', false);

                    return is_numeric($port) ? (int) $port : null;
                },
                'service_id'                     => intval($this->getSiteWorxSetting('service_id')),
                // phpcs:ignore Generic.Files.LineLength.TooLong
                'support_url'                    => $this->getSiteWorxSetting('support_url', ''),
                // phpcs:ignore Generic.Files.LineLength.TooLong
                'telemetry_key'                  => $this->getSiteWorxSetting('telemetry_key', 'ZTuhNKgzgmAAtZNNjRyqVuzQbv9NyWNJMf7'),
                // phpcs:ignore Generic.Files.LineLength.TooLong
                'telemetry_reporter_endpoint'    => $this->getSiteWorxSetting('telemetry_reporter_endpoint', 'https://plugin-api.liquidweb.com'),
                'temp_domain'                    => $this->getSiteWorxSetting('temp_domain'),
                'vanity_domain'                  => $this->getSiteWorxSetting('vanity_domain'),
            ];

            // Calculated values.
            $calculated = [
                'has_custom_domain' => ! Url::domainMatches(
                    site_url(),
                    [$defaults['temp_domain'], $defaults['vanity_domain']]
                )
            ];

            $this->settings = array_merge(
                $defaults,
                $calculated,
                $this->loadSettings($defaults),
                (array) $this->overrides
            );
        }

        return $this;
    }

    /**
     * Refresh the settings cache.
     *
     * @param bool $flush_siteworx Optional. Whether to also flush the SiteWorx cache.
     *                             Default is false.
     *
     * @return self
     */
    public function refresh($flush_siteworx = false)
    {
        $this->settings = [];

        if ($flush_siteworx) {
            delete_site_transient(self::SITEWORX_CACHE_KEY);
        }

        return $this;
    }

    /**
     * Load all custom settings.
     *
     * This method gets called as part of $this->load(), and can override any of the default settings
     * (but can still be overridden via $this->overrides).
     *
     * @param Array<string,mixed> $settings Current settings. These are provided for reference and may
     *                                      be returned, but it's not required to do so.
     *
     * @return Array<string,mixed> Custom settings.
     */
    protected function loadSettings(array $settings)
    {
        return [
            // 'plugin_version' => PLUGIN_VERSION,
        ];
    }

    /**
     * Retrieve a setting from the SiteWorx environment.
     *
     * @param string $setting The SiteWorx setting name.
     * @param mixed  $default Optional. The value to return if the given setting does not exist.
     *                        Default is null.
     *
     * @return mixed The value of the setting, or $default if the setting cannot be found.
     */
    protected function getSiteWorxSetting($setting, $default = null)
    {
        $siteworx = $this->loadSiteWorx();

        if (isset($siteworx[$setting])) {
            return $siteworx[$setting];
        }

        $env_var = getenv(self::PREFIX_ENV_VAR . $setting);

        return false !== $env_var ? $env_var : $default;
    }

    /**
     * Load the SiteWorx environment for this site.
     *
     * @return Array<string,scalar> Environment configuration for the site.
     *
     * @codeCoverageIgnore
     */
    private function loadSiteWorx()
    {
        try {
            /** @var Array<string,scalar> $config */
            $config = remember_site_transient(self::SITEWORX_CACHE_KEY, function () {
                if (! $this->commandExists('siteworx')) {
                    return [];
                }

                $response = (new Command('siteworx -u -o json -n -c Overview -a listAccountConfig'))
                    ->setTimeout(10)
                    ->execute();

                if (! $response->wasSuccessful()) {
                    throw new ConsoleException(sprintf(
                        'Received non-zero exit code (%d): %s',
                        $response->getExitCode(),
                        $response->getErrors()
                    ), $response->getExitCode());
                }

                $output = json_decode($response->getOutput(), true);

                if (null === $output) {
                    throw new ConsoleException('Unable to decode SiteWorx response body');
                }

                return $output;
            }, HOUR_IN_SECONDS);
        } catch (ConsoleException $e) {
            return [];
        }

        return $config;
    }
}
