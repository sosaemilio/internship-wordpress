<?php

namespace Nexcess\MAPPS\Integrations\PerformanceMonitor\Generator\InsightGenerator;

/**
 * The `TBTChange` generator generates insights when the Total Blocking Time
 * metric on a page exceeds 300 ms.
 */
class TBTChange extends BaseInsightTypeGenerator {

	/**
	 * Only TBT changes larger than this number are considered
	 * significant TBT changes.
	 *
	 * @var int
	 */
	const SENSITIVITY = 50;

	/**
	 * Generate a post meta array corresponding with `Insights` objects.
	 *
	 * @return Array<Array>
	 */
	public function generate() {
		$score_diffs = $this->getMetricDiffsFromPages(
			$this->currentPages,
			$this->previousPages,
			'total_blocking_time'
		);

		$improvement_label = _x(
			'improvement',
			'Substituted as \'qualifier\' into this sentence: Significant <%- qualifier %> in responsiveness of <%- where %>',
			'nexcess-mapps'
		);

		$deterioration_label = _x(
			'deterioration',
			'Substituted as \'qualifier\' into this sentence: Significant <%- qualifier %> in responsiveness of <%- where %>',
			'nexcess-mapps'
		);

		$bucketed_values = [
			$deterioration_label => [],
			$improvement_label   => [],
		];

		foreach ( $score_diffs as $data ) {
			if ( $data['diff'] > self::SENSITIVITY ) {
				$bucketed_values[ $deterioration_label ][] = $data['page'];
			}
			if ( $data['diff'] < -1 * self::SENSITIVITY ) {
				$bucketed_values[ $improvement_label ][] = $data['page'];
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
		return 'total-blocking-time-change';
	}

	/**
	 * Returns a text that provides more context around the insight.
	 *
	 * @return string
	 */
	protected static function getCategory() {
		return __(
			'Total Blocking Time',
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
			'Developers should minimize thread-blocking scripts; as little as a third of a second\'s delay can harm user experience.',
			'nexcess-mapps'
		);
	}

	/**
	 * Returns a contextual "more info" URL displayed with the insight.
	 *
	 * @return string
	 */
	protected static function getDescriptionURL() {
		return 'https://web.dev/tbt/';
	}

	/**
	 * Returns a template string to be interpolated by variables.
	 *
	 * @return string
	 */
	protected static function getTemplate() {
		return __(
			'Significant <%- qualifier %> in responsiveness of <%- where %>',
			'nexcess-mapps'
		);
	}
}
