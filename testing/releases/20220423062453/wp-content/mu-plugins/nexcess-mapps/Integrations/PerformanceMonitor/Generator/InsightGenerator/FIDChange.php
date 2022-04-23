<?php

namespace Nexcess\MAPPS\Integrations\PerformanceMonitor\Generator\InsightGenerator;

/**
 * The `FIDChange` generates insights for when the FID metric
 * gets worse on a page.
 */
class FIDChange extends BaseInsightTypeGenerator {

	/**
	 * Insight will be generated only when the LCP metric rises by at least
	 * this amount of miliseconds.
	 */
	const SENSITIVITY = 30;

	/**
	 * Generate a post meta array corresponding with `Insights` objects.
	 *
	 * @return Array<Array>
	 */
	public function generate() {
		$score_diffs = $this->getMetricDiffsFromPages(
			$this->currentPages,
			$this->previousPages,
			'max_fid'
		);

		$increase_label = _x(
			'increase',
			'Substituted as \'qualifier\' into this sentence: Significant <%- qualifier %> in responsiveness on <%- where %>',
			'nexcess-mapps'
		);

		$decrease_label = _x(
			'decrease',
			'Substituted as \'qualifier\' into this sentence: Significant <%- qualifier %> in responsiveness on <%- where %>',
			'nexcess-mapps'
		);

		$bucketed_values = [
			$increase_label => [],
			$decrease_label => [],
		];

		foreach ( $score_diffs as $data ) {
			if ( $data['diff'] > self::SENSITIVITY ) {
				$bucketed_values[ $decrease_label ][] = $data['page'];
			}
			if ( $data['diff'] < -self::SENSITIVITY ) {
				$bucketed_values[ $increase_label ][] = $data['page'];
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
		return 'fid-change';
	}

	/**
	 * Returns a text that provides more context around the insight.
	 *
	 * @return string
	 */
	protected static function getCategory() {
		return __(
			'Max Potential FID',
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
			'Developers should seek to minimize any delay between a user\'s action and the browser beginning its response.',
			'nexcess-mapps'
		);
	}

	/**
	 * Returns a contextual "more info" URL displayed with the insight.
	 *
	 * @return string
	 */
	protected static function getDescriptionURL() {
		return 'https://web.dev/fid/';
	}

	/**
	 * Returns a template string to be interpolated by variables.
	 *
	 * @return string
	 */
	protected static function getTemplate() {
		return __(
			'Significant <%- qualifier %> in responsiveness on <%- where %>',
			'nexcess-mapps'
		);
	}
}
