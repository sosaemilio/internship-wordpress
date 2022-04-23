<?php

/**
 * Custom handling during domain changes.
 */

namespace Nexcess\MAPPS\Integrations;

use Nexcess\MAPPS\Concerns\HasAdminPages;
use Nexcess\MAPPS\Concerns\HasAssets;
use Nexcess\MAPPS\Concerns\HasCronEvents;
use Nexcess\MAPPS\Concerns\InvokesCli;
use Nexcess\MAPPS\Concerns\MakesHttpRequests;
use Nexcess\MAPPS\Concerns\QueriesMAPPS;
use Nexcess\MAPPS\Settings;
use Nexcess\MAPPS\Support\Branding;
use Nexcess\MAPPS\Support\DNS;
use Nexcess\MAPPS\Support\Helpers;
use WP_Error;

class DomainChanges extends Integration {
	use HasAdminPages;
	use HasAssets;
	use HasCronEvents;
	use InvokesCli;
	use MakesHttpRequests;
	use QueriesMAPPS;

	/**
	 * @var \Nexcess\MAPPS\Support\DNS $dns
	 */
	protected $dns;

	/**
	 * @var \Nexcess\MAPPS\Settings $settings
	 */
	protected $settings;

	/**
	 * The hook used for domain change cron events.
	 */
	const DOMAIN_CHANGE_CRON_EVENT = 'nexcess_mapps_domain_changed';

	/**
	 * Construct the integration.
	 *
	 * @param \Nexcess\MAPPS\Settings    $settings
	 * @param \Nexcess\MAPPS\Support\DNS $dns
	 */
	public function __construct( Settings $settings, DNS $dns ) {
		$this->settings = $settings;
		$this->dns      = $dns;
	}

	/**
	 * Retrieve all actions for the integration.
	 *
	 * @return array[]
	 */
	protected function getActions() {
		// phpcs:disable WordPress.Arrays
		return [
			[ 'load-options.php',            [ $this, 'handleSearchReplaceRequests' ] ],
			[ 'load-index.php',              [ $this, 'enqueueScripts'              ] ],
			[ 'load-options-general.php',    [ $this, 'enqueueScripts'              ] ],
			[ 'load-site-health.php',        [ $this, 'enqueueScripts'              ] ],
			[ 'wp_ajax_mapps-change-domain', [ $this, 'handleDomainChangeRequests'  ] ],
			[ 'wp_dashboard_setup',          [ $this, 'registerDashboardWidget'     ] ],

			// Callback for the cron event.
			[ self::DOMAIN_CHANGE_CRON_EVENT, [ $this, 'searchReplace' ], 10, 2 ],
		];
		// phpcs:enable WordPress.Arrays
	}

	/**
	 * Enqueue JavaScript for the opt-in UI.
	 */
	public function enqueueScripts() {
		add_action( 'admin_enqueue_scripts', function () {
			$this->enqueueScript( 'nexcess-mapps-domain-changes', 'domain-changes.js', [ 'nexcess-mapps-admin', 'wp-element' ] );

			$this->injectScriptData( 'nexcess-mapps-domain-changes', 'domainChange', [
				'currentDomain' => wp_parse_url( site_url(), PHP_URL_HOST ),
				'dnsHelpUrl'    => Branding::getDNSHelpUrl(),
				'nonce'         => wp_create_nonce( 'mapps-change-domain' ),
				'portalUrl'     => Helpers::getPortalUrl( $this->settings->plan_id, $this->settings->account_id, 'domain-options' ),
			] );

			$this->injectScriptData( 'nexcess-mapps-domain-changes', 'httpsUpdateUrl', [
				'default' => esc_url( add_query_arg( 'action', 'update_https', wp_nonce_url( admin_url( 'site-health.php' ), 'wp_update_https' ) ) ),
				'updated' => esc_url( admin_url( 'options-general.php' ) . '#siteurl' ),
			] );
		} );
	}

	/**
	 * Form handler for changing the current site's domain.
	 */
	public function handleDomainChangeRequests() {
		if ( empty( $_POST['_mapps_nonce'] ) || ! wp_verify_nonce( $_POST['_mapps_nonce'], 'mapps-change-domain' ) ) {
			return wp_send_json_error( new WP_Error(
				'mapps-nonce-failure',
				__( 'The security nonce has expired or is invalid. Please refresh the page and try again.', 'nexcess-mapps' )
			), 400 );
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			return wp_send_json_error( new WP_Error(
				'mapps-capabilities-failure',
				__( 'You do not have permission to perform this action. Please contact a site administrator or log into the Nexcess portal to change the site domain.', 'nexcess-mapps' )
			), 403 );
		}

		// Verify the domain structure and its DNS records.
		$domain = ! empty( $_POST['domain'] )
			? Helpers::parseDomain( $_POST['domain'] )
			: null;

		/*
		 * Validate the domain structure.
		 *
		 * Note that FILTER_VALIDATE_DOMAIN wasn't added until PHP 7.0, so we'll skip to the
		 * (almost-certainly failing) DNS check.
		 */
		if ( version_compare( '7.0', $this->settings->php_version, '<=' ) && $domain ) {
			// phpcs:ignore PHPCompatibility.Constants.NewConstants.filter_validate_domainFound
			$domain = filter_var( idn_to_ascii( $domain, 0, INTL_IDNA_VARIANT_UTS46 ), FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME | FILTER_NULL_ON_FAILURE );
		}

		if ( empty( $domain ) ) {
			return wp_send_json_error( new WP_Error(
				'mapps-invalid-domain',
				sprintf(
					/* Translators: %1$s is the provided domain name. */
					__( '"%s" is not a valid domain name. Please check your spelling and try again.', 'nexcess-mapps' ),
					sanitize_text_field( $_POST['domain'] )
				)
			), 422 );
		}

		if ( ( ! isset( $_POST['skipDnsVerification'] ) || ! $_POST['skipDnsVerification'] ) && ! $this->dnsRecordsExistForDomain( $domain ) ) {
			return wp_send_json_error( new WP_Error(
				'mapps-missing-dns',
				sprintf(
					/* Translators: %1$s is the provided domain name. */
					__( 'Domain "%s" does not contain any DNS records for this site. Please add the appropriate records and try again.', 'nexcess-mapps' ),
					$domain
				)
			), 422 );
		}

		// Finally, send the request to the MAPPS API.
		$response = $this->mappsApi( 'v1/site/rename', [
			'method' => 'POST',
			'body'   => [
				'domain' => $domain,
			],
		] );

		if ( is_wp_error( $response ) ) {
			return wp_send_json_error( new WP_Error(
				'mapps-change-domain-failure',
				sprintf(
					/* Translators: %1$s is the branded company name, %2$s is the API error message. */
					__( 'The %1$s API returned an error: %2$s', 'nexcess-mapps' ),
					Branding::getCompanyName(),
					$response->get_error_message()
				)
			) );
		}

		return wp_send_json_success( null, 202 );
	}

	/**
	 * When processing the settings API, look for user opt-in to search/replace operations.
	 *
	 * If the "mapps-domain-search-replace" key is found in the $_POST body, apply the appropriate
	 * callback to the update_option_home hook.
	 */
	public function handleSearchReplaceRequests() {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		if ( ! empty( $_POST['mapps-domain-search-replace'] ) ) {
			add_action( 'update_option_home', [ $this, 'scheduleSearchReplace' ], 10, 2 );
		}
	}

	/**
	 * Register the "Go Live" (domain change) dashboard widget.
	 */
	public function registerDashboardWidget() {
		// For now, only register the dashboard widget for WP QuickStart sites.
		if ( ! ( $this->settings->is_quickstart || $this->settings->is_storebuilder ) ) {
			return;
		}

		// Only register for production environments with temporary domains.
		if ( ! $this->settings->is_production_site || ! $this->settings->is_temp_domain ) {
			return;
		}

		// Only show for users that can change the domain.
		if ( ! current_user_can( 'manage_options' ) || ! $this->canChangeDomain() ) {
			return;
		}

		wp_add_dashboard_widget(
			'mapps-change-domain',
			_x( 'Go Live!', 'widget title', 'nexcess-mapps' ),
			function () {
				$this->renderTemplate( 'widgets/change-domain', [
					'dns_help_url' => Branding::getDNSHelpUrl(),
				] );
			},
			null,
			null,
			'normal',
			'default'
		);
	}
	/**
	 * Schedule a cron event to run immediately that will perform a search-replace via WP-CLI.
	 *
	 * @param string $previous The old domain.
	 * @param string $current  The new domain.
	 */
	public function scheduleSearchReplace( $previous, $current ) {
		$this->registerCronEvent( self::DOMAIN_CHANGE_CRON_EVENT, null, current_datetime(), [
			$previous,
			$current,
		] )->scheduleEvents();

		// Spawn a cron process immediately.
		spawn_cron();
	}

	/**
	 * Update the domain in the database using WP-CLI's search-replace command.
	 *
	 * @param string $previous The old domain.
	 * @param string $current  The new domain.
	 */
	public function searchReplace( $previous, $current ) {
		$response = $this->makeCommand( 'wp search-replace', [
			$previous,
			$current,
			'--recurse-objects',
			'--skip-columns=user_email,guid',
			'--all-tables',
		] )->execute();

		if ( $response->wasSuccessful() ) {
			delete_option( 'https_migration_required' );
			wp_cache_flush();
		}

		return $response;
	}

	/**
	 * Determine whether or not the site is eligible to change its domain without going through
	 * the Nexcess portal.
	 *
	 * @return bool True if the domain may be changed, false otherwise.
	 */
	protected function canChangeDomain() {
		$can_change_domain = remember_site_transient( 'nexcess-mapps-can-change-domain', function () {
			$response = $this->mappsApi( 'v1/site', [
				'method' => 'GET',
			] );

			try {
				$body = json_decode( $this->validateHttpResponse( $response ) );

				return (bool) $body->can_modify_domain;
			} catch ( \Exception $e ) {
				/*
				 * If we can't get an answer, something may be wrong with the API.
				 *
				 * In that case, it's best to assume the site can't change its domain without going
				 * through the portal (at least for now).
				 */
				return false;
			}
		}, HOUR_IN_SECONDS );

		return apply_filters( 'Nexcess\MAPPS\DomainChanges\CanChangeDomain', $can_change_domain );
	}

	/**
	 * Determine whether or not there are DNS records for the given domain that reference this site.
	 *
	 * @param string $domain The domain to check.
	 *
	 * @return bool True if there are DNS records for $domain that point to this site, false otherwise.
	 */
	protected function dnsRecordsExistForDomain( $domain ) {
		$records = $this->dns->getRecords( $domain, DNS_A | DNS_CNAME );
		$ip_addr = $this->dns->getIpByHost( $this->settings->temp_domain );

		foreach ( $records as $record ) {
			if ( empty( $record['type'] ) ) {
				continue;
			}

			// A record pointing to this server.
			if (
				'A' === $record['type']
				&& ! empty( $record['ip'] )
				&& $ip_addr === $record['ip']
			) {
				return true;
			}

			// CNAME record.
			if (
				'CNAME' === $record['type']
				&& ! empty( $record['target'] )
				&& $this->settings->temp_domain === $record['target']
			) {
				return true;
			}
		}

		return false;
	}
}
