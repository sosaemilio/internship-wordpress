<?php

namespace Nexcess\MAPPS\Integrations\PerformanceMonitor\Generator\InsightGenerator;

/**
 * The `ResourceTotalWeightChange` generator generates insights related to the
 * increase of the total size of the page and its assets.
 */
class ResourceTotalWeightChange extends BaseInsightTypeGenerator {

	/**
	 * Only report on increase in size that is larger than 300 kB.
	 *
	 * @var int
	 */
	const SENSITIVITY = 300000;

	/**
	 * Generate a post meta array corresponding with `Insights` objects.
	 *
	 * @return Array<Array>
	 */
	public function generate() {
		$values = $this->getMetricsFromPages(
			$this->currentPages,
			'weight_diff',
			0
		);

		$pages_to_report = array_reduce(
			$values,
			function( $pages, $data_item ) {
				if ( self::SENSITIVITY < intval( $data_item['value'] ) ) {
					$pages[] = $data_item['page'];
				}
				return $pages;
			},
			[]
		);

		$insights_meta   = [];
		$insights_meta[] = $this->pagesIntoInsight( $pages_to_report );

		return array_values( array_filter( $insights_meta ) );
	}

	/**
	 * Returns an insight type, i.e. an "insight ID string".
	 *
	 * @return string
	 */
	protected static function getInsightType() {
		return 'resource-total-weight';
	}

	/**
	 * Returns a text that provides more context around the insight.
	 *
	 * @return string
	 */
	protected static function getCategory() {
		return __(
			'Resource Summary',
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
			'Be sure that you\'re getting sufficient benefit from the extra load time resulting from your changes.',
			'nexcess-mapps'
		);
	}

	/**
	 * Returns a contextual "more info" URL displayed with the insight.
	 *
	 * @return string
	 */
	protected static function getDescriptionURL() {
		return 'https://web.dev/resource-summary/';
	}

	/**
	 * Returns a template string to be interpolated by variables.
	 *
	 * @return string
	 */
	protected static function getTemplate() {
		return __(
			'Significant increase in total weight of <%- where %>',
			'nexcess-mapps'
		);
	}
}
