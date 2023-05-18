<?php

namespace Nexcess\MAPPS\Modules;

use StellarWP\PluginFramework\Modules\Telemetry as TelemetryModule;
use WC_Report_Sales_By_Date;

class Telemetry extends TelemetryModule {

	/**
	 * Perform any necessary setup for the integration.
	 *
	 * This method is automatically called as part of Plugin::loadIntegration(), and is the
	 * entry-point for all integrations.
	 */
	public function setup() {
		parent::setup();

		add_filter( self::REPORT_DATA_FILTER, [ $this, 'collectWooCommerceMetrics' ] );
		add_filter( self::REPORT_DATA_FILTER, [ $this, 'filterTelemetryReport' ] );
	}

	/**
	 * Collect high-level details about WooCommerce stores.
	 *
	 * @param mixed[] $report The gathered report data.
	 */
	public function filterTelemetryReport( array $report ) {
		$report['lw_info'] = [
			'account_id'      => $this->settings->get( 'account_id' ),
			'client_id'       => $this->settings->get( 'client_id' ),
			'mwch_site'       => $this->settings->get( 'is_mwch_site' ),
			'plan_name'       => $this->settings->get( 'is_nexcess_site' ) ? $this->settings->get( 'plan_name' ) : 'None',
			'regression_site' => $this->settings->get( 'is_regression_site' ),
			'service_id'      => $this->settings->get( 'service_id' ),
			'staging_site'    => $this->settings->get( 'is_staging_site' ),
			'temp_domain'     => $this->settings->get( 'temp_domain' ),
		];

		return $report;

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
