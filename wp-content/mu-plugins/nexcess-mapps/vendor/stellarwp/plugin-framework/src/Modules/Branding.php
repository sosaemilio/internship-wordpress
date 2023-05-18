<?php

/**
 * Functionality related to Branding.
 */

namespace StellarWP\PluginFramework\Modules;

use StellarWP\PluginFramework\Contracts\ProvidesSettings;

class Branding extends Module
{
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
        add_filter('stellarwp_branding_company_name', [ $this, 'brandingCompanyName' ]);
        add_filter('stellarwp_branding_company_platform_name', [ $this, 'brandingPlatformName' ]);
        add_filter('stellarwp_branding_company_image', [ $this, 'brandingCompanyImage' ]);
        add_filter('stellarwp_branding_support_url', [ $this, 'brandingSupportUrl' ]);
        add_filter('stellarwp_branding_dns_help_url', [ $this, 'brandingDnsHelpUrl' ]);
    }

    /**
     * Override the company name used throughout the platform.
     *
     * @return string
     */
    public function brandingCompanyName()
    {
        return $this->settings->company_name;
    }

    /**
     * Override the company platform name used throughout the platform's branding.
     *
     * @return string
     */
    public function brandingPlatformName()
    {
        return $this->settings->platform_name;
    }

    /**
     * Override the company logo image file for the dashboard.
     *
     * @return string
     */
    public function brandingCompanyImage()
    {
        return $this->settings->logo_path;
    }

    /**
     * Override the URL for support.
     *
     * @return string
     */
    public function brandingSupportUrl()
    {
        return $this->settings->support_url;
    }

    /**
     * Override the URL for DNS help.
     *
     * @return string
     */
    public function brandingDnsHelpUrl()
    {
        return $this->settings->dns_help_url;
    }
}
