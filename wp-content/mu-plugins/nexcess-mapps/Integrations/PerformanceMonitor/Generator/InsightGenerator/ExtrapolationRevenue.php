<?php

namespace Nexcess\MAPPS\Integrations\PerformanceMonitor\Generator\InsightGenerator;

use Nexcess\MAPPS\Integrations\PerformanceMonitor\Model\Page;
use Nexcess\MAPPS\Support\Helpers;

/**
 * The `ExtrapolationRevenue` generator produces an insight exposing
 * the potential revenue impact of the current performance loss.
 */
class ExtrapolationRevenue extends BaseInsightTypeGenerator {
	/**
	 * A threshold in miliseconds. If average load time change exceeds it,
	 * an insight should be generated.
	 *
	 * The value of 464 ms is not arbitrary. When a page load is slowed
	 * by that amount, the formula for revenue lost yields 5 % – which
	 * we consider to be a loss that is "significant enough".
	 *
	 * Mathematically: log_0.989(0.95) = 4.63733...
	 *
	 * @var int
	 */
	const SENSITIVITY = 464;

	/**
	 * Per every 100 ms of a slowdown, the revenue falls to 98.9% of the previous value.
	 *
	 * @var float
	 */
	const REVENUE_DROP_PER_100_MS = 0.989;

	/**
	 * Generate a post meta array corresponding with `Insights` objects.
	 *
	 * @return Array<Array>
	 */
	public function generate() {
		$current_load_times = array_map(
			function( $page ) {
				/**
				 * @var Page $page
				 */
				return intval( $page->getMeta( 'load_time', 0 ) );
			},
			$this->currentPages
		);

		$previous_load_times = array_map(
			function( $page ) {
				/**
				 * @var Page $page
				 */
				return intval( $page->getMeta( 'load_time', 0 ) );
			},
			$this->previousPages
		);

		$average_current_load_time  = Helpers::calculateIntegerAverage( $current_load_times );
		$average_previous_load_time = Helpers::calculateIntegerAverage( $previous_load_times );
		$diff_average_load_time     = $average_current_load_time - $average_previous_load_time;

		if ( $diff_average_load_time < self::SENSITIVITY ) {
			return [];
		}

		$net_sales = $this->getYesterdaysNetSales();

		/**
		 * We don't want to generate the insight if sales can't be
		 * retrieved (= `null`) or when there were no sales (= 0.00).
		 */
		if ( empty( $net_sales ) ) {
			return [];
		}

		$exponent     = $diff_average_load_time / 100;
		$revenue_lost = ( $net_sales / pow( self::REVENUE_DROP_PER_100_MS, $exponent ) ) - $net_sales;

		$revenue_lost_w_currency = html_entity_decode(
			wp_strip_all_tags(
				wc_price( $revenue_lost ),
				true
			)
		);

		return [
			[
				'type'      => self::getInsightType(),
				'variables' => [
					[
						'variable' => 'revenue_lost',
						'value'    => $revenue_lost_w_currency,
					],
				],
			],
		];
	}

	/**
	 * Returns the net sales value for yesterday or `null`
	 * if WooCommerce is not enabled.
	 *
	 * @return float|null
	 */
	protected function getYesterdaysNetSales() {
		$net_sales_filtered = apply_filters( 'pm_pre_woocommerce_net_sales', null );

		if ( $net_sales_filtered ) {
			return $net_sales_filtered;
		}

		if ( ! class_exists( 'woocommerce' ) ) {
			return null;
		}

		$required_files = [
			WP_PLUGIN_DIR . '/woocommerce/includes/admin/reports/class-wc-admin-report.php',
			WP_PLUGIN_DIR . '/woocommerce/includes/admin/reports/class-wc-report-sales-by-date.php',
		];

		foreach ( $required_files as $required_file ) {
			if ( ! file_exists( $required_file ) ) {
				return null;
			}
			require_once $required_file;
		}

		if ( ! class_exists( '\WC_Report_Sales_By_Date' ) ) {
			return null;
		}

		$yesterday_timestamp       = ( new \DateTime( 'yesterday', wp_timezone() ) )->getTimestamp();
		$wc_report                 = new \WC_Report_Sales_By_Date();
		$wc_report->start_date     = $yesterday_timestamp;
		$wc_report->end_date       = $yesterday_timestamp; // WooCommerce adds +1 DAY.
		$wc_report->group_by_query = 'DAY(posts.post_date)';
		$report_data               = $wc_report->get_report_data();

		if ( isset( $report_data->net_sales ) && is_numeric( $report_data->net_sales ) ) {
			return floatval( $report_data->net_sales );
		}
		return null;
	}

	/**
	 * Returns an insight type, i.e. an "insight ID string".
	 *
	 * @return string
	 */
	protected static function getInsightType() {
		return 'extrapolation-revenue';
	}

	/**
	 * Returns a text that provides more context around the insight.
	 *
	 * @return string
	 */
	protected static function getCategory() {
		return __(
			'Extrapolation: revenue',
			'nexcess-mapps'
		);
	}

	/**
	 * Returns a text that provides more context around the insight.
	 *
	 * @return string
	 */
	protected static function getDescriptionText() {
		return __(
			'Studies suggest that visitors to e-commerce sites are 1.1% less likely to make a purchase for every 0.1s increase in page load time.',
			'nexcess-mapps'
		);
	}

	/**
	 * Returns a contextual "more info" URL displayed with the insight.
	 *
	 * @return string
	 */
	protected static function getDescriptionURL() {
		return 'https://web.dev/why-speed-matters/';
	}

	/**
	 * Returns a template string to be interpolated by variables.
	 *
	 * @return string
	 */
	protected static function getTemplate() {
		return __(
			'Reduced performance may have cost you <%- revenue_lost %> in lost sales today',
			'nexcess-mapps'
		);
	}
}
