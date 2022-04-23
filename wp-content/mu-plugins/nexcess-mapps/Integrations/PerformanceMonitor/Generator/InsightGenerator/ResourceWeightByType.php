<?php

namespace Nexcess\MAPPS\Integrations\PerformanceMonitor\Generator\InsightGenerator;

/**
 * The `ResourceWeightByType` generator generates insights related to the
 * increase of the size of assets of a certain type.
 */
class ResourceWeightByType extends BaseInsightTypeGenerator {

	/**
	 * Only create insights when the resource type total weight
	 * increase is over its sensitivity value.
	 *
	 * Note: The numbers hover around ~10 % threshold for an average
	 *       weight of that resource type, but are also manually adjusted.
	 *
	 * @link https://httparchive.org/reports/page-weight
	 *
	 * @var Array<string, int>
	 */
	const SENSITIVITY_BY_RESOURCE_TYPE = [
		'document'    => 5000,
		'script'      => 40000,
		'stylesheet'  => 10000,
		'image'       => 100000,
		'media'       => 150000,
		'third-party' => 40000,
	];

	/**
	 * Generate a post meta array corresponding with `Insights` objects.
	 *
	 * @return Array<Array>
	 */
	public function generate() {
		$insights_meta          = [];
		$resource_types_plurals = [
			'document'    => _x(
				'document',
				'Substituted as \'resource_type\' into this sentence: Significant increase in weight of <%- resource_type %> on <%- where %>',
				'nexcess-mapps'
			),
			'script'      => _x(
				'scripts',
				'Substituted as \'resource_type\' into this sentence: Significant increase in weight of <%- resource_type %> on <%- where %>',
				'nexcess-mapps'
			),
			'stylesheet'  => _x(
				'stylesheets',
				'Substituted as \'resource_type\' into this sentence: Significant increase in weight of <%- resource_type %> on <%- where %>',
				'nexcess-mapps'
			),
			'image'       => _x(
				'images',
				'Substituted as \'resource_type\' into this sentence: Significant increase in weight of <%- resource_type %> on <%- where %>',
				'nexcess-mapps'
			),
			'media'       => _x(
				'media files',
				'Substituted as \'resource_type\' into this sentence: Significant increase in weight of <%- resource_type %> on <%- where %>',
				'nexcess-mapps'
			),
			'third-party' => _x(
				'third party assets',
				'Substituted as \'resource_type\' into this sentence: Significant increase in weight of <%- resource_type %> on <%- where %>',
				'nexcess-mapps'
			),
		];

		foreach ( array_keys( self::SENSITIVITY_BY_RESOURCE_TYPE ) as $resource_type ) {
			$metric_name = sprintf( 'weight_%s_diff', $resource_type );

			$values = $this->getMetricsFromPages(
				$this->currentPages,
				$metric_name,
				0
			);

			$pages_to_report = array_reduce(
				$values,
				function( $pages, $data_item ) use ( $resource_type ) {
					if ( self::SENSITIVITY_BY_RESOURCE_TYPE[ $resource_type ] < intval( $data_item['value'] ) ) {
						$pages[] = $data_item['page'];
					}
					return $pages;
				},
				[]
			);

			$extra_variables = [
				[
					'variable' => 'resource_type',
					'value'    => $resource_types_plurals[ $resource_type ],
				],
			];
			$insights_meta[] = $this->pagesIntoInsight( $pages_to_report, $extra_variables );
		}
		return array_values( array_filter( $insights_meta ) );
	}

	/**
	 * Returns an insight type, i.e. an "insight ID string".
	 *
	 * @return string
	 */
	protected static function getInsightType() {
		return 'resource-type-weight';
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
			'Smaller loads mean faster pages. It\'s often possible to compress files without affecting quality or functionality.',
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
			'Significant increase in weight of <%- resource_type %> on <%- where %>',
			'nexcess-mapps'
		);
	}
}
