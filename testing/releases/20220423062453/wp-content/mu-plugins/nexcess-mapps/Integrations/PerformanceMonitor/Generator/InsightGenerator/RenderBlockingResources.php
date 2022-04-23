<?php

namespace Nexcess\MAPPS\Integrations\PerformanceMonitor\Generator\InsightGenerator;

/**
 * The `RenderBlockingResources` generator generates insights when there are
 * render blocking resources on the page.
 */
class RenderBlockingResources extends BaseInsightTypeGenerator {

	/**
	 * Generate a post meta array corresponding with `Insights` objects.
	 *
	 * @return Array<Array>
	 */
	public function generate() {
		$values = $this->getMetricsFromPages(
			$this->currentPages,
			'render_blocking_time',
			0
		);

		$pages_that_fail = array_reduce(
			$values,
			function( $pages, $data_item ) {
				if ( 0 < intval( $data_item['value'] ) ) {
					$pages[] = $data_item['page'];
				}
				return $pages;
			},
			[]
		);

		$qualifier = $this->isRepeated()
			? _x(
				'detected on',
				'Substituted as \'qualifier\' into this sentence: Render-blocking resource calls <%- qualifier %> <%- where %>',
				'nexcess-mapps'
			)
			: _x(
				'added to',
				'Substituted as \'qualifier\' into this sentence: Render-blocking resource calls <%- qualifier %> <%- where %>',
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
		return 'audits/render-blocking-resources/details/items';
	}

	/**
	 * Returns an insight type, i.e. an "insight ID string".
	 *
	 * @return string
	 */
	protected static function getInsightType() {
		return 'render-blocking-resources';
	}

	/**
	 * Returns a text that provides more context around the insight.
	 *
	 * @return string
	 */
	protected static function getCategory() {
		return __(
			'Render Blocking Resources',
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
			'Pages will not appear until requests for these scripts or stylesheets have been completed.',
			'nexcess-mapps'
		);
	}

	/**
	 * Returns a contextual "more info" URL displayed with the insight.
	 *
	 * @return string
	 */
	protected static function getDescriptionURL() {
		return 'https://web.dev/render-blocking-resources/';
	}

	/**
	 * Returns a template string to be interpolated by variables.
	 *
	 * @return string
	 */
	protected static function getTemplate() {
		return __(
			'Render-blocking resource calls <%- qualifier %> <%- where %>',
			'nexcess-mapps'
		);
	}
}
