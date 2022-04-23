<?php

namespace Nexcess\MAPPS\Integrations\PerformanceMonitor\Generator\InsightGenerator;

/**
 * The `TTI` generator generates insights when the time-to-interactive
 * metric value is over 5000 miliseconds on a page.
 */
class TTI extends BaseInsightTypeGenerator {

	/**
	 * A TTI threshold beyond which a page is considered slow to load.
	 *
	 * @var int
	 */
	const TTI_THRESHOLD = 5000;

	/**
	 * Generate a post meta array corresponding with `Insights` objects.
	 *
	 * @return Array<Array>
	 */
	public function generate() {
		$values = $this->getMetricsFromPages(
			$this->currentPages,
			'load_time',
			0
		);

		$pages_that_fail = array_reduce(
			$values,
			function( $pages, $data_item ) {
				if ( self::TTI_THRESHOLD < intval( $data_item['value'] ) ) {
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
		return 'time-to-interactive-low';
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
			'Google advises that pages should be interactive on average mobile hardware in less than 5 seconds.',
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
			'<%- where %> taking too long to load',
			'nexcess-mapps'
		);
	}
}
