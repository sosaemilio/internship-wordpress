<?php

/**
 * Telemetry data for Nexcess Managed Apps.
 */

namespace Nexcess\MAPPS\Integrations;

use Nexcess\MAPPS\Concerns\HasCronEvents;
use Nexcess\MAPPS\Settings;
use WC_Report_Sales_By_Date;

class Telemetry extends Integration {
	use HasCronEvents;

	/**
	 * @var \Nexcess\MAPPS\Settings
	 */
	protected $settings;

	/**
	 * The action used for the related cron event.
	 */
	const REPORT_CRON_ACTION = 'nexcess_mapps_usage_tracking';

	/**
	 * The API endpoint for reporting.
	 */
	const REPORTER_ENDPOINT = 'https://plugin-api.liquidweb.com/api/site_report';

	/**
	 * @param \Nexcess\MAPPS\Settings $settings
	 */
	public function __construct( Settings $settings ) {
		$this->settings = $settings;
	}

	/**
	 * Perform any necessary setup for the integration.
	 *
	 * This method is automatically called as part of Plugin::loadIntegration(), and is the
	 * entry-point for all integrations.
	 */
	public function setup() {
		$this->addHooks();

		$this->registerCronEvent( self::REPORT_CRON_ACTION, 'daily' );
	}

	/**
	 * Retrieve all actions for the integration.
	 *
	 * @return array[]
	 */
	protected function getActions() {
		return [
			// Hook into the cron event.
			[ self::REPORT_CRON_ACTION, [ $this, 'sendTelemetryData' ] ],
		];
	}

	/**
	 * Retrieve all filters for the integration.
	 *
	 * @return array[]
	 */
	protected function getFilters() {
		return [
			[ 'nexcess_mapps_telemetry_report', [ $this, 'collectWooCommerceMetrics' ] ],
		];
	}

	/**
	 * Send telemetry data to Nexcess.
	 */
	public function sendTelemetryData() {
		$report        = $this->collectTelemetryData();
		$report['key'] = $this->settings->telemetry_key;

		return wp_remote_post( self::REPORTER_ENDPOINT, [
			'headers'  => [
				'Accept'       => 'application/json',
				'Content-Type' => 'application/json',
			],
			'body'     => wp_json_encode( $report ),
			'blocking' => false,
			'timeout'  => 900,
		] );
	}

	/**
	 * Collect telemetry data about the current site.
	 *
	 * @global $wp_version
	 * @global $wpdb
	 *
	 * @return mixed[]
	 */
	protected function collectTelemetryData() {
		global $wp_version, $wpdb;

		$report = [
			'admin_email' => get_option( 'admin_email' ),
			'domain'      => get_home_url(),
			'ip'          => gethostbyname( php_uname( 'n' ) ),
			'php_version' => phpversion(),
			'plugins'     => $this->getPluginData(),
			'theme'       => get_stylesheet(),
			'server_name' => gethostname(),
			'wp_version'  => $wp_version,
			'lw_info'     => [
				'account_id'      => $this->settings->account_id,
				'client_id'       => $this->settings->client_id,
				'mwch_site'       => $this->settings->is_mwch_site,
				'plan_name'       => $this->settings->is_nexcess_site ? $this->settings->plan_name : 'None',
				'regression_site' => $this->settings->is_regression_site,
				'service_id'      => $this->settings->service_id,
				'staging_site'    => $this->settings->is_staging_site,
				'temp_domain'     => $this->settings->temp_domain,
				'quickstart_id'   => $this->settings->is_quickstart,
			],
			'php_info'    => [
				'memory_limit'        => ini_get( 'memory_limit' ),
				'upload_max_filesize' => ini_get( 'upload_max_filesize' ),
			],
			'server_info' => [
				'php_version'   => PHP_VERSION,
				'mysql_version' => $wpdb->get_var( 'SELECT VERSION()' ),
				'web_server'    => $_SERVER['SERVER_SOFTWARE'],
			],
			'wp_info'     => [
				'version'             => get_bloginfo( 'version' ),
				'language'            => get_locale(),
				'permalink_structure' => get_option( 'permalink_structure' ) ?: 'Default',
				'abspath'             => constant( 'ABSPATH' ),
				'wp_debug'            => defined( 'WP_DEBUG' ) && WP_DEBUG,
				'wp_memory_limit'     => constant( 'WP_MEMORY_LIMIT' ),
				'multisite'           => is_multisite(),
			],
		];

		/**
		 * Filter the data collected by the plugin reporter.
		 *
		 * @param array $report The gathered report data.
		 */
		return apply_filters( 'nexcess_mapps_telemetry_report', $report );
	}

	/**
	 * Collect details about currently-installed plugins.
	 *
	 * @return array[]
	 */
	protected function getPluginData() {
		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		// Standard plugins.
		$plugins = get_plugins();
		array_walk( $plugins, function ( &$plugin, $path ) {
			$plugin['active'] = is_plugin_active( $path );
		} );

		// Must-use plugins.
		foreach ( get_mu_plugins() as $file => $plugin ) {
			$plugin['active'] = true;

			// Append it to the $plugins array.
			$plugins[ 'mu-plugins/' . $file ] = $plugin;
		}

		return $plugins;
	}

	/**
	 * Collect high-level details about WooCommerce stores.
	 *
	 * @param mixed[] $report The gathered report data.
	 *
	 * @return mixed[] The $report array, with additional 'stats' and 'currency' keys for WooCommerce.
	 */
	public function collectWooCommerceMetrics( array $report ) {
		if (
			! isset( $report['plugins']['woocommerce/woocommerce.php'] )
			|| ! $report['plugins']['woocommerce/woocommerce.php']['active']
		) {
			return $report;
		}

		try {
			require_once WP_PLUGIN_DIR . '/woocommerce/includes/admin/reports/class-wc-admin-report.php';
			require_once WP_PLUGIN_DIR . '/woocommerce/includes/admin/reports/class-wc-report-sales-by-date.php';

			$stats = [];

			// Collect stats for the last four years.
			for ( $i = 0; $i < 4; $i++ ) {
				$year = (int) gmdate( 'Y' ) - $i;

				$stats[ $year ] = $this->getWooCommerceStatsForYear( $year );
			}

			// General metrics, not limited to a year.
			$stats['overall'] = [
				'products' => (int) wp_count_posts( 'product' )->publish,
			];

			$report['plugins']['woocommerce/woocommerce.php']['stats']    = $stats;
			$report['plugins']['woocommerce/woocommerce.php']['currency'] = get_woocommerce_currency();
		} catch ( \Throwable $e ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_trigger_error
			trigger_error( 'Unable to load WooCommerce reports: ' . esc_html( $e->getMessage() ), E_USER_WARNING );
		}

		return $report;
	}

	/**
	 * Collect WooCommerce metrics for the given year.
	 *
	 * @param int $year The year for which to retrieve stats.
	 *
	 * @return int[]
	 */
	protected function getWooCommerceStatsForYear( $year ) {
		$report                 = new WC_Report_Sales_By_Date();
		$report->start_date     = strtotime( $year . '-01-01' );
		$report->end_date       = strtotime( $year . '-12-31' );
		$report->group_by_query = 'YEAR(posts.post_date)';
		$report_data            = $report->get_report_data();
		$products               = wc_get_products( [
			'return'       => 'ids',
			'limit'        => -1,
			'date_created' => sprintf( '%d-01-01...%d-12-31', $year, $year ),
		] );

		return [
			'order_count' => $report_data->total_orders,
			'products'    => count( $products ),
			'revenue'     => $report_data->total_sales,
		];
	}
}
