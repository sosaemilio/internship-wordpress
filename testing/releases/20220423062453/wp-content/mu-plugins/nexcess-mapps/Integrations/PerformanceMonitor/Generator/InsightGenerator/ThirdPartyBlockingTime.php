<?php

namespace Nexcess\MAPPS\Integrations\PerformanceMonitor\Generator\InsightGenerator;

/**
 * The `ThirdPartyBlockingTime` generator generates insights when third-party
 * scripts block page rendering for more than 250 ms.
 */
class ThirdPartyBlockingTime extends BaseInsightTypeGenerator {

	/**
	 * Generate a post meta array corresponding with `Insights` objects.
	 *
	 * @return Array<Array>
	 */
	public function generate() {
		$values = $this->getMetricsFromLighthouseReports(
			$this->lighthouseReports,
			'audits/third-party-summary/score',
			1,
			$this->currentPages
		);

		$pages_that_fail = array_reduce(
			$values,
			function( $pages, $data_item ) {
				if ( 0 === intval( $data_item['value'] ) ) {
					$pages[] = $data_item['page'];
				}
				return $pages;
			},
			[]
		);

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
		return 'third-party-blocking-time';
	}

	/**
	 * Returns a text that provides more context around the insight.
	 *
	 * @return string
	 */
	protected static function getCategory() {
		return __(
			'Third Party Summary',
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
			'Calls to third-party servers should not delay page load by more than a quarter of a second.',
			'nexcess-mapps'
		);
	}

	/**
	 * Returns a contextual "more info" URL displayed with the insight.
	 *
	 * @return string
	 */
	protected static function getDescriptionURL() {
		return 'https://web.dev/third-party-summary/';
	}

	/**
	 * Returns a template string to be interpolated by variables.
	 *
	 * @return string
	 */
	protected static function getTemplate() {
		return __(
			'Requests for external scripts are slowing down <%- where %>',
			'nexcess-mapps'
		);
	}
}
