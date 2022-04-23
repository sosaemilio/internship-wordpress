<?php

namespace Nexcess\MAPPS\Integrations\PerformanceMonitor\Generator\InsightGenerator;

/**
 * The `LCPChange` generates insights for when the LCP metric
 * of a page or multiple pages gets worse.
 */
class LCPChange extends BaseInsightTypeGenerator {

	/**
	 * Insight will be generated only when the LCP metric rises by at least
	 * this amount of miliseconds.
	 */
	const SENSITIVITY = 650;

	/**
	 * Issue an insight only when the current LCP time is at least
	 * this amount of miliseconds.
	 */
	const LCP_HEALTHY_THRESHOLD = 2500;

	/**
	 * Generate a post meta array corresponding with `Insights` objects.
	 *
	 * @return Array<Array>
	 */
	public function generate() {
		$score_diffs = $this->getMetricDiffsFromPages(
			$this->currentPages,
			$this->previousPages,
			'lcp_time'
		);

		$pages_that_fail = [];
		foreach ( $score_diffs as $data ) {
			$is_lcp_non_healty    = $data['current'] > self::LCP_HEALTHY_THRESHOLD;
			$is_lcp_diff_too_high = $data['diff'] > self::SENSITIVITY;

			if ( $is_lcp_non_healty && $is_lcp_diff_too_high ) {
				$pages_that_fail[] = $data['page'];
			}
		}

		$insights_meta   = [];
		$insights_meta[] = $this->pagesIntoInsight( $pages_that_fail );

		return array_values( array_filter( $insights_meta ) );
	}

	/**
	 * Returns an insight type, i.e. an "insight ID string".
	 *
	 * @return string
	 */
	protected static function getInsightType() {
		return 'lcp-change';
	}

	/**
	 * Returns a text that provides more context around the insight.
	 *
	 * @return string
	 */
	protected static function getCategory() {
		return __(
			'Largest Contentful Paint',
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
			'Research shows this is an important factor in how users perceive the overall speed of your site.',
			'nexcess-mapps'
		);
	}

	/**
	 * Returns a contextual "more info" URL displayed with the insight.
	 *
	 * @return string
	 */
	protected static function getDescriptionURL() {
		return 'https://web.dev/lcp/';
	}

	/**
	 * Returns a template string to be interpolated by variables.
	 *
	 * @return string
	 */
	protected static function getTemplate() {
		return __(
			'Key element is significantly slower to display on <%- where %>',
			'nexcess-mapps'
		);
	}
}
