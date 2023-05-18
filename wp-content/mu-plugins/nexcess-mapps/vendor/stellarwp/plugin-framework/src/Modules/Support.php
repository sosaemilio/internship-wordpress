<?php

/**
 * Functionality related to support.
 */

namespace StellarWP\PluginFramework\Modules;

use StellarWP\PluginFramework\Concerns\HasAdminPages;
use StellarWP\PluginFramework\Contracts\LoadsConditionally;
use StellarWP\PluginFramework\Contracts\ProvidesSettings;
use StellarWP\PluginFramework\Support\Helpers;

class Support extends Module implements LoadsConditionally
{
    use HasAdminPages;

    /**
     * @var ProvidesSettings $settings
     */
    protected $settings;

    /**
     * @param \StellarWP\PluginFramework\Contracts\ProvidesSettings $settings
     */
    public function __construct(ProvidesSettings $settings)
    {
        $this->settings = $settings;
    }

    /**
     * {@inheritDoc}
     */
    public function setup()
    {
        add_action('admin_init', [$this, 'registerSupportSection'], 1000);
    }

    /**
     * Determine whether or not this integration should be loaded.
     *
     * @return bool Whether or not this integration be loaded in this environment.
     */
    public function shouldLoad()
    {
        /**
         * Determine whether the "Support" section of the Dashboard should be available.
         *
         * @param bool $enabled True if the section should be present, false otherwise.
         */
        return (bool) apply_filters('stellarwp_branding_enable_support_template', true);
    }

    /**
     * Register the "Support" settings section.
     *
     * @return void
     */
    public function registerSupportSection()
    {
        add_settings_section(
            'support',
            _x('Support', 'settings section', 'stellarwp-framework'),
            function () {
                $this->renderTemplate('support', [
                    'details'  => $this->getSupportDetails(),
                    'settings' => $this->settings,
                ]);
            },
            'nexcess-mapps'
        );
    }

    /**
     * Retrieve an array of support details.
     *
     * @return mixed[] Details that should be provided in the support details section of the
     *                 Nexcess MAPPS dashboard.
     */
    protected function getSupportDetails()
    {
        $details = [
            'Account ID'       => $this->settings->account_id,
            'Package'          => $this->settings->package_label,
            'Plan Name'        => $this->settings->plan_name,
            'Plan Type'        => $this->settings->plan_type,
            'PHP Version'      => $this->settings->php_version,
            'WP_DEBUG enabled' => Helpers::getEnabled(defined('WP_DEBUG') && WP_DEBUG),
        ];

        /**
         * Filters an array of details, keyed by their label.
         */
        return apply_filters('Nexcess\\MAPPS\\support_details', $details);
    }
}
