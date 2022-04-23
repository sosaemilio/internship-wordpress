<?php

namespace Nexcess\MAPPS\Integrations\PerformanceMonitor\Generator\InsightGenerator;

/**
 * The `NoDocumentWrite` generator generates insights when the `document.write`
 * method is used on a page to alter its contents.
 */
class NoDocumentWrite extends BaseInsightTypeGenerator {

	/**
	 * Generate a post meta array corresponding with `Insights` objects.
	 *
	 * @return Array<Array>
	 */
	public function generate() {
		$values = $this->getMetricsFromLighthouseReports(
			$this->lighthouseReports,
			'audits/no-document-write/score',
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

		$qualifier = $this->isRepeated()
			? _x(
				'detected on',
				'Substituted as \'qualifier\' into this sentence: Problematic script method <%- qualifier %> <%- where %>',
				'nexcess-mapps'
			)
			: _x(
				'added to',
				'Substituted as \'qualifier\' into this sentence: Problematic script method <%- qualifier %> <%- where %>',
				'nexcess-mapps'
			);

		$extra_variables = [
			[
				'variable' => 'qualifier',
				'value'    => $qualifier,
			],
		];
		$insights_meta   = [];
		$insights_meta[] = $this->pagesIntoInsight( $pages_that_fail, $extra_variables );

		return array_values( array_filter( $insights_meta ) );
	}

	/**
	 * Returns a path to a key in the Lighthouse report JSON that contains
	 * a list of items (assets, files) that impacted the current metric.
	 *
	 * @return string
	 */
	protected function getLighthouseItemsKeyPath() {
		return 'audits/no-document-write/details/items';
	}

	/**
	 * Returns an insight type, i.e. an "insight ID string".
	 *
	 * @return string
	 */
	protected static function getInsightType() {
		return 'no-document-write';
	}

	/**
	 * Returns a text that provides more context around the insight.
	 *
	 * @return string
	 */
	protected static function getCategory() {
		return __(
			'No Document Write',
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
			'document.write can delay page display by tens of seconds, and is blocked by Chrome in many cases.',
			'nexcess-mapps'
		);
	}

	/**
	 * Returns a contextual "more info" URL displayed with the insight.
	 *
	 * @return string
	 */
	protected static function getDescriptionURL() {
		return 'https://web.dev/no-document-write/';
	}

	/**
	 * Returns a template string to be interpolated by variables.
	 *
	 * @return string
	 */
	protected static function getTemplate() {
		return __(
			'Problematic script method <%- qualifier %> <%- where %>',
			'nexcess-mapps'
		);
	}
}
