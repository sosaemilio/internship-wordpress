<?php

namespace StellarWP\PluginFramework\Modules;

use StellarWP\PluginFramework\Contracts\LoadsConditionally;
use StellarWP\PluginFramework\Contracts\ProvidesSettings;
use StellarWP\PluginFramework\Exceptions\RequestException;
use StellarWP\PluginFramework\Exceptions\ValidationException;
use StellarWP\PluginFramework\Services\Domain;
use StellarWP\PluginFramework\Services\Managers\MetaBoxManager;
use StellarWP\PluginFramework\Support\Attributes;
use StellarWP\PluginFramework\Support\MetaBox;
use WP_Error;

class GoLiveWidget extends Module implements LoadsConditionally
{
    /**
     * The Domain instance.
     *
     * @var Domain
     */
    protected $domain;

    /**
     * The MetaBoxManager instance.
     *
     * @var MetaBoxManager
     */
    protected $manager;

    /**
     * The Settings instance.
     *
     * @var ProvidesSettings
     */
    protected $settings;

    /**
     * The Ajax action.
     */
    const AJAX_ACTION = 'stellarwp-change-domain';

    /**
     * Construct the "Go Live!" widget.
     *
     * @param ProvidesSettings $settings
     * @param MetaBoxManager   $manager
     * @param Domain           $domain
     */
    public function __construct(ProvidesSettings $settings, MetaBoxManager $manager, Domain $domain)
    {
        $this->settings = $settings;
        $this->manager  = $manager;
        $this->domain   = $domain;
    }

    /**
     * Perform any necessary setup for the module.
     *
     * This method is automatically called as part of Plugin::load_modules(), and is the
     * entry-point for all modules.
     *
     * @return void
     */
    public function setup()
    {
        $this->manager->register(new MetaBox([
            'id'       => 'stellarwp-go-live',
            'title'    => _x('Go Live!', 'widget-title', 'stellarwp-framework'),
            'body'     => [$this, 'renderWidget'],
            'page'     => 'dashboard',
            'context'  => MetaBox::CONTEXT_NORMAL,
            'priority' => MetaBox::PRIORITY_CORE,
        ]));

        add_action('wp_ajax_' . static::AJAX_ACTION, [$this, 'handleDomainChangeRequests']);
        add_action('admin_enqueue_scripts', function () {
            wp_enqueue_style('stellarwp-forms');
            wp_enqueue_script(
                'stellarwp-go-live',
                $this->settings->framework_url . 'dist/js/go-live-widget.js',
                ['wp-element'],
                $this->settings->plugin_version,
                true
            );
        });
    }

    /**
     * Determine whether this extension should load.
     *
     * @return bool True if the extension should load, false otherwise.
     */
    public function shouldLoad()
    {
        return ! $this->settings->has_custom_domain;
    }

    /**
     * Handle requests to change the domain.
     *
     * @return void
     */
    public function handleDomainChangeRequests()
    {
        // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
        if (empty($_POST['_wpnonce'])) {
            wp_send_json_error(new WP_Error(
                'stellarwp-nonce-missing',
                // phpcs:ignore Generic.Files.LineLength.TooLong
                __('The security nonce is missing. Please refresh the page and try again.', 'stellarwp-framework')
            ), 401);
        }

        // phpcs:ignore WordPress.Security.ValidatedSanitizedInput,WordPress.Security.ValidatedSanitizedInput.MissingUnslash
        if (! wp_verify_nonce(strval($_POST['_wpnonce']), self::AJAX_ACTION)) {
            wp_send_json_error(new WP_Error(
                'stellarwp-nonce-failure',
                // phpcs:ignore Generic.Files.LineLength.TooLong
                __('The security nonce has expired or is invalid. Please refresh the page and try again.', 'stellarwp-framework')
            ), 401);
        }

        if (! current_user_can('manage_options')) {
            wp_send_json_error(new WP_Error(
                'stellarwp-capabilities-failure',
                // phpcs:ignore Generic.Files.LineLength.TooLong
                __('You do not have permission to perform this action. Please contact a site administrator to change the site domain.', 'stellarwp-framework')
            ), 403);
        }

        if (empty($_POST['domain'])) {
            wp_send_json_error(new WP_Error(
                'stellarwp-validation-failure',
                __('Missing the required "domain" parameter.', 'stellarwp-framework')
            ), 422);
        }

        try {
            $this->domain->changeDomain(
                // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
                sanitize_text_field(strval(wp_unslash($_POST['domain']))),
                ! empty($_POST['skipDnsVerification'])
            );
        } catch (ValidationException $e) {
            wp_send_json_error(new WP_Error(
                'stellarwp-validation-failure',
                $e->getMessage()
            ), 422);
        } catch (RequestException $e) {
            wp_send_json_error(new WP_Error(
                'stellarwp-request-failure',
                $e->getMessage()
            ), 500);
        }

        wp_send_json_success(null, 202);
    }

    /**
     * Render the domain change form React component.
     *
     * This method will print the div#stellarwp-change-domain-form that React will look for as its root element.
     *
     * @return void
     */
    public function renderDomainChangeForm()
    {
        echo wp_kses_post(sprintf(
            '<div id="stellarwp-change-domain-form" %s></div>',
            Attributes::getDataAttributeString([
                'ajaxUrl'       => admin_url('admin-ajax.php'),
                'currentDomain' => wp_parse_url(site_url(), PHP_URL_HOST),
                'dnsHelpUrl'    => $this->settings->dns_help_url,
                'nonce'         => wp_create_nonce(static::AJAX_ACTION),
            ])
        ));
    }

    /**
     * Render the widget.
     *
     * @return void
     */
    public function renderWidget()
    {
        echo wp_kses_post(wpautop(sprintf(
            /* Translators: %1$s is the DNS help documentation URL. */
            __('Are you ready to take your site live? All you need to do is enter the domain name below (<a href="%1$s" target="_blank" rel="noopener">after making sure it\'s pointing to this site</a>), press "Connect", and we\'ll do all the work.', 'stellarwp-framework'), // phpcs:ignore Generic.Files.LineLength.TooLong
            esc_url($this->settings->dns_help_url)
        )));

        $this->renderDomainChangeForm();
    }
}
