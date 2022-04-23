<?php

namespace Nexcess\MAPPS\Integrations\PerformanceMonitor\Generator\InsightGenerator;

/**
 * The `LCP` generator generates insights when the largest element
 * above the fold renders in 2500+ miliseconds.
 */
class LCP extends BaseInsightTypeGenerator {

	/**
	 * Generate a post meta array corresponding with `Insights` objects.
	 *
	 * @return Array<Array>
	 */
	public function generate() {
		$values = $this->getMetricsFromPages(
			$this->currentPages,
			'lcp_time',
			0
		);

		$bucketed_values = $this->valuesIntoBuckets( $values, [
			_x(
				'slow',
				'Substituted as \'qualifier\' into this sentence: Key element is <%- qualifier %> to display on <%- where %>',
				'nexcess-mapps'
			) => [ 2501, 4000 ],
			_x(
				'very slow',
				'Substituted as \'qualifier\' into this sentence: Key element is <%- qualifier %> to display on <%- where %>',
				'nexcess-mapps'
			) => [ 4001, PHP_INT_MAX ],
		] );

		return $this->bucketedValuesIntoInsights( $bucketed_values );
	}

	/**
	 * Returns an insight type, i.e. an "insight ID string".
	 *
	 * @return string
	 */
	protected static function getInsightType() {
		return 'lcp-slow';
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
			'This is one of Google\'s Core Web Vital metrics: poor performance will affect your ranking in search results.',
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
			'Key element is <%- qualifier %> to display on <%- where %>',
			'nexcess-mapps'
		);
	}
}
