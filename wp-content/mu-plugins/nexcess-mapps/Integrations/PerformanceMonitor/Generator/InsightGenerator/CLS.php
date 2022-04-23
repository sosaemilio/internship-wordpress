<?php

namespace Nexcess\MAPPS\Integrations\PerformanceMonitor\Generator\InsightGenerator;

/**
 * The `CLS` generator generates insights when the layout on a page
 * is unstable during page load phase.
 */
class CLS extends BaseInsightTypeGenerator {

	/**
	 * Generate a post meta array corresponding with `Insights` objects.
	 *
	 * @return Array<Array>
	 */
	public function generate() {
		$values = $this->getMetricsFromLighthouseReports(
			$this->lighthouseReports,
			'audits/cumulative-layout-shift/numericValue',
			0,
			$this->currentPages
		);

		$bucketed_values = $this->valuesIntoBuckets( $values, [
			_x(
				'unstable',
				'Substituted as \'qualifier\' into this sentence: <%- qualifier %> page layout on <%- where %>',
				'nexcess-mapps'
			) => [ 0.1, 0.25 ],
			_x(
				'very unstable',
				'Substituted as \'qualifier\' into this sentence: <%- qualifier %> page layout on <%- where %>',
				'nexcess-mapps'
			) => [ 0.25, PHP_INT_MAX ],
		] );

		return $this->bucketedValuesIntoInsights( $bucketed_values );
	}

	/**
	 * Returns an insight type, i.e. an "insight ID string".
	 *
	 * @return string
	 */
	protected static function getInsightType() {
		return 'cls';
	}

	/**
	 * Returns a text that provides more context around the insight.
	 *
	 * @return string
	 */
	protected static function getCategory() {
		return __(
			'Cumulative layout shift',
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
			'Elements may be moving around the page unexpectedly as they load. Google penalizes sites which rate poorly for this.',
			'nexcess-mapps'
		);
	}

	/**
	 * Returns a contextual "more info" URL displayed with the insight.
	 *
	 * @return string
	 */
	protected static function getDescriptionURL() {
		return 'https://web.dev/cls/';
	}

	/**
	 * Returns a template string to be interpolated by variables.
	 *
	 * @return string
	 */
	protected static function getTemplate() {
		return __(
			'<%- qualifier %> page layout on <%- where %>',
			'nexcess-mapps'
		);
	}
}
