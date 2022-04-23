<?php

namespace Nexcess\MAPPS\Integrations\PerformanceMonitor\Generator\InsightGenerator;

/**
 * The `FID` generator generates insights when the First Input Delay
 * metric on a page exceeds 100 ms.
 */
class FID extends BaseInsightTypeGenerator {

	/**
	 * Generate a post meta array corresponding with `Insights` objects.
	 *
	 * @return Array<Array>
	 */
	public function generate() {
		$values = $this->getMetricsFromPages(
			$this->currentPages,
			'max_fid',
			0
		);

		$bucketed_values = $this->valuesIntoBuckets( $values, [
			_x(
				'slow',
				'Substituted as \'qualifier\' into this sentence: <%- where %> may be <%- qualifier %> to become interactive',
				'nexcess-mapps'
			) => [ 101, 300 ],
			_x(
				'very slow',
				'Substituted as \'qualifier\' into this sentence: <%- where %> may be <%- qualifier %> to become interactive',
				'nexcess-mapps'
			) => [ 301, PHP_INT_MAX ],
		] );

		return $this->bucketedValuesIntoInsights( $bucketed_values );
	}

	/**
	 * Returns an insight type, i.e. an "insight ID string".
	 *
	 * @return string
	 */
	protected static function getInsightType() {
		return 'fid-slow';
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
			'Large or inefficient script files can take time to process, making your site feel unresponsive.',
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
			'<%- where %> may be <%- qualifier %> to become interactive',
			'nexcess-mapps'
		);
	}
}
