<?php

namespace Nexcess\MAPPS\Integrations\PerformanceMonitor\Generator\InsightGenerator;

/**
 * The `TTIChange` generates insights for when the overall performance
 * of a page or multiple pages drops.
 */
class TTIChange extends BaseInsightTypeGenerator {

	/**
	 * Only TTI changes larger than this number are considered
	 * significant TTI changes.
	 */
	const SENSITIVITY = 1000;

	/**
	 * Generate a post meta array corresponding with `Insights` objects.
	 *
	 * @return Array<Array>
	 */
	public function generate() {
		$score_diffs = $this->getMetricDiffsFromPages(
			$this->currentPages,
			$this->previousPages,
			'load_time'
		);

		$increase_label = _x(
			'increase',
			'Substituted as \'qualifier\' into this sentence: Significant <%- qualifier %> in load time on <%- where %>',
			'nexcess-mapps'
		);

		$decrease_label = _x(
			'decrease',
			'Substituted as \'qualifier\' into this sentence: Significant <%- qualifier %> in load time on <%- where %>',
			'nexcess-mapps'
		);

		$bucketed_values = [
			$increase_label => [],
			$decrease_label => [],
		];

		foreach ( $score_diffs as $data ) {
			if ( $data['diff'] > self::SENSITIVITY ) {
				$bucketed_values[ $increase_label ][] = $data['page'];
			}
			if ( $data['diff'] < -self::SENSITIVITY ) {
				$bucketed_values[ $decrease_label ][] = $data['page'];
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
		return 'time-to-interactive-change';
	}

	/**
	 * Returns a text that provides more context around the insight.
	 *
	 * @return string
	 */
	protected static function getCategory() {
		return __(
			'Time To Interactive',
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
			'It\'s essential for good user experience that a page is quick to become both visible and usable.',
			'nexcess-mapps'
		);
	}

	/**
	 * Returns a contextual "more info" URL displayed with the insight.
	 *
	 * @return string
	 */
	protected static function getDescriptionURL() {
		return 'https://web.dev/tti/';
	}

	/**
	 * Returns a template string to be interpolated by variables.
	 *
	 * @return string
	 */
	protected static function getTemplate() {
		return __(
			'Significant <%- qualifier %> in load time on <%- where %>',
			'nexcess-mapps'
		);
	}
}
