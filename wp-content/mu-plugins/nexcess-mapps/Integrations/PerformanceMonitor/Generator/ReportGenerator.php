<?php

namespace Nexcess\MAPPS\Integrations\PerformanceMonitor\Generator;

use Nexcess\MAPPS\Integrations\PerformanceMonitor\LighthouseReport;
use Nexcess\MAPPS\Integrations\PerformanceMonitor\Model\Report;
use Nexcess\MAPPS\Support\Helpers;

/**
 * The `ReportGenerator` is responsible for generating post meta for a `Report`
 * instance once all Lighthouse reports are available for a given day.
 */
class ReportGenerator extends BaseGenerator {

	/**
	 * @var LighthouseReport[]
	 */
	protected $lighthouseReports;

	/**
	 * @var Report|null
	 */
	protected $previousReport;

	/**
	 * Constructor.
	 *
	 * @param LighthouseReport[] $lighthouse_reports Array of all `LighthouseReport` objects for a given day.
	 * @param Report             $previous_report    Report object to compare today's results to.
	 */
	public function __construct(
		array $lighthouse_reports,
		Report $previous_report = null
	) {
		$this->lighthouseReports = $lighthouse_reports;
		$this->previousReport    = $previous_report;
	}

	/**
	 * Generate a post meta array corresponding with a `Report` object.
	 *
	 * @return Array<mixed>
	 */
	public function generate() {
		$scores = [];

		foreach ( $this->lighthouseReports as $report ) {
			$summary  = $report->getSummary();
			$scores[] = $summary['score'];
		}

		$post_meta = [
			'average_score'      => Helpers::calculateIntegerAverage( $scores ),
			'average_score_diff' => 0,
			'changes'            => 0,
			'insights'           => 0,
			'wp_environment'     => $this->getWpEnvironmentInfo(),
		];

		if ( $this->previousReport instanceof Report ) {
			$previous_average_score = $this->previousReport->getMeta( 'average_score' );
			if ( is_int( $previous_average_score ) ) {
				$post_meta['average_score_diff'] = $post_meta['average_score'] - $previous_average_score;
			}
		}

		return $post_meta;
	}

	/**
	 * @return Array<mixed> Information about the current WP Environment:
	 *                      themes, plugins, core.
	 */
	protected function getWpEnvironmentInfo() {
		$environment = [
			'core_version'   => get_bloginfo( 'version' ),
			'active_plugins' => [],
		];

		/**
		 * Plugins.
		 */
		$all_plugins    = get_plugins();
		$active_plugins = get_option( 'active_plugins', [] );

		foreach ( $all_plugins as $plugin_file_path => $plugin_meta ) {
			if ( in_array( $plugin_file_path, $active_plugins, true ) ) {
				$environment['active_plugins'][] = [
					'name'    => $plugin_meta['Name'],
					'version' => isset( $plugin_meta['Version'] ) ? $plugin_meta['Version'] : '',
				];
			}
		}

		/**
		 * Themes.
		 */
		$parent_theme = get_template();
		$child_theme  = get_stylesheet();

		if ( $parent_theme !== $child_theme ) {
			$environment['parent_theme'] = $this->getThemeInfo( $parent_theme );
		}
		$environment['active_theme'] = $this->getThemeInfo( $child_theme );

		return $environment;
	}

	/**
	 * @param string $theme_dir_name Name of the theme to retieve information for.
	 *
	 * @return array{name: string, version: string} Theme information.
	 */
	protected function getThemeInfo( $theme_dir_name ) {
		$theme         = wp_get_theme( $theme_dir_name );
		$theme_name    = '';
		$theme_version = '';

		if ( $theme->exists() ) {
			$theme_name = is_string( $theme->get( 'Name' ) )
				? $theme->get( 'Name' )
				: '<Unnamed theme>';

			$theme_version = is_string( $theme->get( 'Version' ) )
				? $theme->get( 'Version' )
				: '';
		}

		return [
			'name'    => $theme_name,
			'version' => $theme_version,
		];
	}
}
