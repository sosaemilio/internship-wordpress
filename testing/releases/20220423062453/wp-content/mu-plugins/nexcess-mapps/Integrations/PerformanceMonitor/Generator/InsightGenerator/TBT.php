<?php

namespace Nexcess\MAPPS\Integrations\PerformanceMonitor\Generator\InsightGenerator;

/**
 * The `TBT` generator generates insights when the Total Blocking Time
 * metric on a page exceeds 300 ms.
 */
class TBT extends BaseInsightTypeGenerator {

	/**
	 * A TBT threshold beyond which a page is considered slow to load.
	 *
	 * @var int
	 */
	const TBT_THRESHOLD = 300;

	/**
	 * Generate a post meta array corresponding with `Insights` objects.
	 *
	 * @return Array<Array>
	 */
	public function generate() {
		$values = $this->getMetricsFromPages(
			$this->currentPages,
			'total_blocking_time',
			0
		);

		$pages_that_fail = array_reduce(
			$values,
			function( $pages, $data_item ) {
				if ( self::TBT_THRESHOLD < intval( $data_item['value'] ) ) {
					$pages[] = $data_item['page'];
				}
				return $pages;
			},
			[]
		);

		$insights_meta   = [];
		$insights_meta[] = $this->pagesIntoInsight( $pages_that_fail );

		return array_filter( $insights_meta );
	}

	/**
	 * Returns an insight type, i.e. an "insight ID string".
	 *
	 * @return string
	 */
	protected static function getInsightType() {
		return 'total-blocking-time';
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
			'Long or slow scripts can result in frustrating delays between a user action and the browser\'s response.',
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
			'<%- where %> may feel unresponsive',
			'nexcess-mapps'
		);
	}
}
