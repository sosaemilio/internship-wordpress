<?php

namespace Nexcess\MAPPS\Integrations\PerformanceMonitor\Generator\InsightGenerator;

/**
 * The `Redirects` generator generates insights when multiple redirects
 * happen on a page.
 */
class Redirects extends BaseInsightTypeGenerator {

	/**
	 * Generate a post meta array corresponding with `Insights` objects.
	 *
	 * @return Array<Array>
	 */
	public function generate() {
		$values = $this->getMetricsFromLighthouseReports(
			$this->lighthouseReports,
			'audits/redirects/score',
			1,
			$this->currentPages
		);

		$pages_that_fail = array_reduce(
			$values,
			function( $pages, $data_item ) {
				if ( 0 === intval( $data_item['value'] ) ) {
					$pages[] = $data_item['page'];
				}
				return $pages;
			},
			[]
		);

		$insights_meta   = [];
		$insights_meta[] = $this->pagesIntoInsight( $pages_that_fail );

		return array_values( array_filter( $insights_meta ) );
	}

	/**
	 * Returns an insight type, i.e. an "insight ID string".
	 *
	 * @return string
	 */
	protected static function getInsightType() {
		return 'redirects';
	}

	/**
	 * Returns a text that provides more context around the insight.
	 *
	 * @return string
	 */
	protected static function getCategory() {
		return __(
			'Redirects',
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
			'Unnecessary additional network requests can cause significant delays in loading times.',
			'nexcess-mapps'
		);
	}

	/**
	 * Returns a contextual "more info" URL displayed with the insight.
	 *
	 * @return string
	 */
	protected static function getDescriptionURL() {
		return 'https://web.dev/redirects/';
	}

	/**
	 * Returns a template string to be interpolated by variables.
	 *
	 * @return string
	 */
	protected static function getTemplate() {
		return __(
			'Multiple redirects detected on <%- where %>',
			'nexcess-mapps'
		);
	}
}
