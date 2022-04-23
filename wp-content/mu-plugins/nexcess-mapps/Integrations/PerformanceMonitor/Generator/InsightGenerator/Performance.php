<?php

namespace Nexcess\MAPPS\Integrations\PerformanceMonitor\Generator\InsightGenerator;

/**
 * The `Performance` generator generates insights when the
 * overall performance of a page or multiple pages is poor or in need of improvement.
 */
class Performance extends BaseInsightTypeGenerator {

	/**
	 * Generate a post meta array corresponding with `Insights` objects.
	 *
	 * @return Array<Array>
	 */
	public function generate() {
		$values = $this->getMetricsFromPages(
			$this->currentPages,
			'score',
			0
		);

		$bucketed_values = $this->valuesIntoBuckets( $values, [
			_x(
				'is poor',
				'Substituted as \'qualifier\' into this sentence: Performance on <%- where %> <%- qualifier %>',
				'nexcess-mapps'
			) => [ 0, 49 ],
			_x(
				'needs improvement',
				'Substituted as \'qualifier\' into this sentence: Performance on <%- where %> <%- qualifier %>',
				'nexcess-mapps'
			) => [ 50, 89 ],
		] );

		return $this->bucketedValuesIntoInsights( $bucketed_values );
	}

	/**
	 * Returns an insight type, i.e. an "insight ID string".
	 *
	 * @return string
	 */
	protected static function getInsightType() {
		return 'low-performance';
	}

	/**
	 * Returns a text that provides more context around the insight.
	 *
	 * @return string
	 */
	protected static function getCategory() {
		return __(
			'Performance',
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
			'This is likely to have a negative effect on user experience, and may also lead to a lower ranking in search results.',
			'nexcess-mapps'
		);
	}

	/**
	 * Returns a contextual "more info" URL displayed with the insight.
	 *
	 * @return string
	 */
	protected static function getDescriptionURL() {
		return 'https://web.dev/performance-scoring/';
	}

	/**
	 * Returns a template string to be interpolated by variables.
	 *
	 * @return string
	 */
	protected static function getTemplate() {
		return __(
			'Performance on <%- where %> <%- qualifier %>',
			'nexcess-mapps'
		);
	}
}
