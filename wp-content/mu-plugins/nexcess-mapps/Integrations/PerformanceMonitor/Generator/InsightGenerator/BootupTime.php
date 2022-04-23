<?php

namespace Nexcess\MAPPS\Integrations\PerformanceMonitor\Generator\InsightGenerator;

/**
 * The `BootupTime` generator generates insights when JavaScript
 * on a page takes long to execute.
 */
class BootupTime extends BaseInsightTypeGenerator {

	/**
	 * Generate a post meta array corresponding with `Insights` objects.
	 *
	 * @return Array<Array>
	 */
	public function generate() {
		$values = $this->getMetricsFromPages(
			$this->currentPages,
			'bootup_time',
			0
		);

		$bucketed_values = $this->valuesIntoBuckets( $values, [
			_x(
				'Slow',
				'Substituted as \'qualifier\' into this sentence: <%- qualifier %> script execution detected on <%- where %>',
				'nexcess-mapps'
			) => [ 2000, 3500 ],
			_x(
				'Very slow',
				'Substituted as \'qualifier\' into this sentence: <%- qualifier %> script execution detected on <%- where %>',
				'nexcess-mapps'
			) => [ 3501, PHP_INT_MAX ],
		] );

		return $this->bucketedValuesIntoInsights( $bucketed_values );
	}

	/**
	 * Returns a path to a key in the Lighthouse report JSON that contains
	 * a list of items (assets, files) that impacted the current metric.
	 *
	 * @return string
	 */
	protected function getLighthouseItemsKeyPath() {
		return 'audits/bootup-time/details/items';
	}

	/**
	 * Returns an insight type, i.e. an "insight ID string".
	 *
	 * @return string
	 */
	protected static function getInsightType() {
		return 'bootup-time';
	}

	/**
	 * Returns a text that provides more context around the insight.
	 *
	 * @return string
	 */
	protected static function getCategory() {
		return __(
			'Bootup time',
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
			'Large and inefficient script files can unnecessarily increase the time taken for a page to become interactive.',
			'nexcess-mapps'
		);
	}

	/**
	 * Returns a contextual "more info" URL displayed with the insight.
	 *
	 * @return string
	 */
	protected static function getDescriptionURL() {
		return 'https://web.dev/bootup-time/';
	}

	/**
	 * Returns a template string to be interpolated by variables.
	 *
	 * @return string
	 */
	protected static function getTemplate() {
		return __(
			'<%- qualifier %> script execution detected on <%- where %>',
			'nexcess-mapps'
		);
	}
}
