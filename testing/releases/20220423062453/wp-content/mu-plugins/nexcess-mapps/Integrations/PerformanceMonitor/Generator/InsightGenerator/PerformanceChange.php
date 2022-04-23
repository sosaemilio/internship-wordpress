<?php

namespace Nexcess\MAPPS\Integrations\PerformanceMonitor\Generator\InsightGenerator;

/**
 * The `PerformanceDrop` generates insights for when the overall performance
 * of a page or multiple pages drops.
 */
class PerformanceChange extends BaseInsightTypeGenerator {

	/**
	 * Only score changes larger than this number are considered
	 * significant performance changes.
	 */
	const SENSITIVITY = 5;

	/**
	 * Generate a post meta array corresponding with `Insights` objects.
	 *
	 * @return Array<Array>
	 */
	public function generate() {
		$score_diffs = $this->getMetricDiffsFromPages(
			$this->currentPages,
			$this->previousPages,
			'score'
		);

		$up_label = _x(
			'up',
			'Substituted as \'qualifier\' into this sentence: Performance is <%- qualifier %> significantly on <%- where %>',
			'nexcess-mapps'
		);

		$down_label = _x(
			'down',
			'Substituted as \'qualifier\' into this sentence: Performance is <%- qualifier %> significantly on <%- where %>',
			'nexcess-mapps'
		);

		$bucketed_values = [
			$up_label   => [],
			$down_label => [],
		];

		foreach ( $score_diffs as $data ) {
			if ( $data['diff'] > self::SENSITIVITY ) {
				$bucketed_values[ $up_label ][] = $data['page'];
			}
			if ( $data['diff'] < -self::SENSITIVITY ) {
				$bucketed_values[ $down_label ][] = $data['page'];
			}
		}

		return $this->bucketedValuesIntoInsights( $bucketed_values );
	}

	/**
	 * Returns an insight type, i.e. an "insight ID string".
	 *
	 * @return string
	 */
	protected static function getInsightType() {
		return 'performance-change';
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
			'This may be a result of code or content changes you have made; or external factors outside your control.',
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
			'Performance is <%- qualifier %> significantly on <%- where %>',
			'nexcess-mapps'
		);
	}
}
