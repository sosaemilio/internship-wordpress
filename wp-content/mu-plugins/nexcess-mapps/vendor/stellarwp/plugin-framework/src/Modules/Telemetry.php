<?php

/**
 * Telemetry data collected for Managed Application services.
 */

namespace StellarWP\PluginFramework\Modules;

use StellarWP\PluginFramework\Contracts\ProvidesSettings;
use StellarWP\PluginFramework\Services\Managers\CronEventManager;
use StellarWP\PluginFramework\Services\Nexcess\Telemetry as TelemetryService;
use StellarWP\PluginFramework\Support\CronEvent;

class Telemetry extends Module
{
    /**
     * @var CronEventManager
     */
    protected $cron;

    /**
     * @var ProvidesSettings
     */
    protected $settings;

    /**
     * @var TelemetryService
     */
    protected $service;

    /**
     * The action used for the related cron event.
     */
    const REPORT_CRON_ACTION = 'stellarwp_hosting_usage_tracking';

    /**
     * The filter used for collecting telemetry data.
     */
    const REPORT_DATA_FILTER = 'stellarwp_hosting_telemetry_report';

    /**
     * @param ProvidesSettings $settings
     * @param CronEventManager $cron
     * @param TelemetryService $service
     */
    public function __construct(ProvidesSettings $settings, CronEventManager $cron, TelemetryService $service)
    {
        $this->cron     = $cron;
        $this->settings = $settings;
        $this->service  = $service;
    }

    /**
     * Perform any necessary setup for the integration.
     *
     * This method is automatically called as part of Plugin::loadIntegration(), and is the
     * entry-point for all integrations.
     */
    public function setup()
    {
        $this->cron->register(self::REPORT_CRON_ACTION, CronEvent::DAILY, current_datetime());

        add_action(self::REPORT_CRON_ACTION, [ $this, 'sendTelemetryData' ]);
    }

    /**
     * Collect and send telemetry data.
     *
     * @return void
     */
    public function sendTelemetryData()
    {
        $this->service->sendReport($this->collectTelemetryData());
    }

    /**
     * Collect telemetry data about the current site.
     *
     * @global $wp_version
     * @global $wpdb
     *
     * @return array<mixed>
     */
    public function collectTelemetryData()
    {
        global $wp_version, $wpdb;

        $report = [
            'admin_email' => get_option('admin_email'),
            'domain'      => get_home_url(),
            'ip'          => gethostbyname(php_uname('n')),
            'php_version' => phpversion(),
            'plugins'     => $this->getPluginData(),
            'theme'       => get_stylesheet(),
            'server_name' => gethostname(),
            'wp_version'  => $wp_version,
            'lw_info'     => [
                'account_id'      => $this->settings->get('account_id'),
                'client_id'       => $this->settings->get('client_id'),
                'plan_name'       => $this->settings->get('plan_name') ?: 'StellarWP Cloud',
                'service_id'      => $this->settings->get('service_id'),
                'temp_domain'     => $this->settings->temp_domain,
            ],
            'php_info'    => [
                'memory_limit'        => ini_get('memory_limit'),
                'upload_max_filesize' => ini_get('upload_max_filesize'),
            ],
            'server_info' => [
                'php_version'   => PHP_VERSION,
                'mysql_version' => $wpdb->get_var('SELECT VERSION()'),
                'web_server'    => isset($_SERVER['SERVER_SOFTWARE']) ? wp_unslash(filter_input(INPUT_SERVER, 'SERVER_SOFTWARE')) : '', // phpcs:ignore Generic.Files.LineLength.TooLong
            ],
            'wp_info'     => [
                'version'             => get_bloginfo('version'),
                'language'            => get_locale(),
                'permalink_structure' => get_option('permalink_structure') ?: 'Default',
                'abspath'             => constant('ABSPATH'),
                'wp_debug'            => defined('WP_DEBUG') && WP_DEBUG,
                'wp_memory_limit'     => constant('WP_MEMORY_LIMIT'),
                'multisite'           => is_multisite(),
            ],
        ];

        /**
         * Filter the data collected by the plugin reporter.
         *
         * @param array $report The gathered report data.
         */
        return apply_filters(self::REPORT_DATA_FILTER, $report);
    }

    /**
     * Collect details about currently-installed plugins.
     *
     * @return array<mixed>
     */
    protected function getPluginData()
    {
        if (! function_exists('get_plugins')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        // Standard plugins.
        $plugins = get_plugins();
        array_walk($plugins, function (&$plugin, $path) {
            $plugin['active'] = is_plugin_active($path);
        });

        // Must-use plugins.
        foreach (get_mu_plugins() as $file => $plugin) {
            $plugin['active'] = true;

            // Append it to the $plugins array.
            $plugins[ 'mu-plugins/' . $file ] = $plugin;
        }

        return $plugins;
    }
}
