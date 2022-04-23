<?php

namespace Nexcess\MAPPS\Integrations\PerformanceMonitor\Generator\InsightGenerator;

/**
 * The `ObservationTheme` generator produces a notice in a situation
 * when a theme change happens.
 */
class ObservationTheme extends BaseInsightTypeGenerator {

	/**
	 * Generate a post meta array corresponding with `Insights` objects.
	 *
	 * @return Array<Array>
	 */
	public function generate() {
		$target_actions      = [ 'update', 'change' ];
		$target_object_types = [ 'theme', 'parent_theme', 'child_theme' ];

		foreach ( $this->siteChanges as $change ) {
			$action      = $change->getMeta( 'action' );
			$object_type = $change->getMeta( 'object_type' );

			if ( in_array( $action, $target_actions, true ) && in_array( $object_type, $target_object_types, true ) ) {
				$parent_variable      = 'parent_theme' === $object_type ? 'parent ' : '';
				$change_type_variable = 'change' === $action
					? _x(
						'changed',
						'Substituted as \'change_type\' into this sentence: Your site\'s<%- parent %> theme was <%- change_type %>',
						'nexcess-mapps'
					)
					: _x(
						'updated',
						'Substituted as \'change_type\' into this sentence: Your site\'s<%- parent %> theme was <%- change_type %>',
						'nexcess-mapps'
					);

				return [
					[
						'type'      => self::getInsightType(),
						'variables' => [
							[
								'variable' => 'parent',
								'value'    => $parent_variable,
							],
							[
								'variable' => 'change_type',
								'value'    => $change_type_variable,
							],
						],
					],
				];
			}
		}

		return [];
	}

	/**
	 * Returns an insight type, i.e. an "insight ID string".
	 *
	 * @return string
	 */
	protected static function getInsightType() {
		return 'observation-theme';
	}

	/**
	 * Returns a text that provides more context around the insight.
	 *
	 * @return string
	 */
	protected static function getCategory() {
		return __(
			'Observation: theme',
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
			'This can affect not only the appearance, but also the performance of all pages on your site. Keep an eye on it!',
			'nexcess-mapps'
		);
	}

	/**
	 * Returns a contextual "more info" URL displayed with the insight.
	 *
	 * @return string
	 */
	protected static function getDescriptionURL() {
		return 'https://developer.wordpress.org/themes/getting-started/what-is-a-theme/';
	}

	/**
	 * Returns a template string to be interpolated by variables.
	 *
	 * @return string
	 */
	protected static function getTemplate() {
		return __(
			'Your site\'s<%- parent %> theme was <%- change_type %>',
			'nexcess-mapps'
		);
	}
}
