<?php

namespace Nexcess\MAPPS\Integrations\PerformanceMonitor\Generator\InsightGenerator;

use Nexcess\MAPPS\Integrations\PerformanceMonitor\FileSource;
use Nexcess\MAPPS\Integrations\PerformanceMonitor\LighthouseReport;
use Nexcess\MAPPS\Integrations\PerformanceMonitor\Model\Insight;
use Nexcess\MAPPS\Integrations\PerformanceMonitor\Model\Page;
use Nexcess\MAPPS\Integrations\PerformanceMonitor\Model\SiteChange;

/**
 * The `BaseInsightGenerator` defines a structure of all the individual `InsightGenerator`s.
 */
abstract class BaseInsightTypeGenerator {

	/**
	 * @var LighthouseReport[]
	 */
	protected $lighthouseReports;

	/**
	 * @var Page[]
	 */
	protected $currentPages;

	/**
	 * @var Page[]
	 */
	protected $previousPages;

	/**
	 * @var Insight[]
	 */
	protected $previousInsights;

	/**
	 * @var SiteChange[]
	 */
	protected $siteChanges;

	/**
	 * @var string[]
	 */
	protected $mutedInsightTypes;

	/**
	 * Constructor.
	 *
	 * @param LighthouseReport[] $lighthouse_reports  `LighthouseReport` objects for a given day.
	 * @param Page[]             $current_pages       `Page` objects generated today.
	 * @param Page[]             $previous_pages      `Page` objects to compare today's results to.
	 * @param Insight[]          $previous_insights   `Insight` objects generated in the last week.
	 * @param SiteChange[]       $site_changes        `SiteChange` objects generated today.
	 * @param string[]           $muted_insight_types Array of insight types that are muted.
	 */
	public function __construct(
		array $lighthouse_reports,
		array $current_pages,
		array $previous_pages = [],
		array $previous_insights = [],
		array $site_changes = [],
		array $muted_insight_types = []
	) {
		$this->lighthouseReports = $lighthouse_reports;
		$this->currentPages      = $current_pages;
		$this->previousPages     = $previous_pages;
		$this->previousInsights  = $previous_insights;
		$this->siteChanges       = $site_changes;
		$this->mutedInsightTypes = $muted_insight_types;
	}

	/**
	 * Generate a post meta array corresponding with `Insights` objects.
	 *
	 * @return Array<Array>
	 */
	abstract public function generate();

	/**
	 * Note: The following static methods returning empty strings
	 *       only exist because in PHP 5.6 strict mode it is not possible
	 *       to declare an abstract static method.
	 */

	/**
	 * Returns the insight category.
	 *
	 * @return string
	 */
	protected static function getCategory() {
		return '';
	}

	/**
	 * Returns the insight title.
	 *
	 * E.g. "This may be a result of code or content changes you have made;
	 *       or external factors outside your control." (for Overall Performance)
	 *
	 * @return string
	 */
	protected static function getDescriptionText() {
		return '';
	}

	/**
	 * Returns a contextual "more info" URL displayed with the insight.
	 *
	 * @return string
	 */
	protected static function getDescriptionURL() {
		return '';
	}

	/**
	 * Returns a template string to be interpolated by variables.
	 *
	 * @return string
	 */
	protected static function getTemplate() {
		return '';
	}

	/**
	 * Returns an insight type, i.e. an "insight ID string".
	 *
	 * @return string
	 */
	protected static function getInsightType() {
		return '';
	}

	/**
	 * Returns an array of metric values for given pages.
	 *
	 * @param LighthouseReport[] $lighthouse_reports `LighthouseReport` objects.
	 * @param string             $metric             Numeric metric name.
	 * @param mixed              $default_value      Default value to use when metric value can't be retrieved.
	 * @param Page[]|null        $pages              Pages to select a matching `Page` from.
	 *
	 * @return Array[]
	 */
	protected function getMetricsFromLighthouseReports(
		array $lighthouse_reports,
		$metric,
		$default_value = 0,
		$pages = null
	) {
		$pages            = ( null === $pages ) ? $this->currentPages : $pages;
		$metric_extractor = function ( LighthouseReport $lighthouse_report ) use (
			$metric,
			$default_value,
			$pages
		) {
			$page = $this->getPageForLighthouseReport( $lighthouse_report, $pages );
			if ( $page ) {
				$value = floatval( $lighthouse_report->getValue( $metric, $default_value ) );

				return [
					'page'  => $page,
					'value' => $value,
				];
			}
		};

		$metrics = array_map( $metric_extractor, $lighthouse_reports );
		$metrics = array_values( array_filter( $metrics ) );

		return $metrics;
	}

	/**
	 * Returns an array of metric values for given pages.
	 *
	 * @param Page[] $pages         Page objects.
	 * @param string $metric        Numeric metric name.
	 * @param int    $default_value Default value to use when metric value can't be retrieved.
	 *
	 * @return Array[]
	 */
	protected function getMetricsFromPages( array $pages, $metric, $default_value = 0 ) {
		$metrics = [];

		foreach ( $pages as $page ) {
			$metrics[ $page->getMeta( 'url' ) ] = [
				'page'  => $page,
				'value' => $page->getMeta( $metric, $default_value ),
			];
		}
		return $metrics;
	}

	/**
	 * Produces differences between metric values given two sets of pages.
	 *
	 * @param Page[] $current_pages  Page objects.
	 * @param Page[] $previous_pages Page objects.
	 * @param string $metric         Numeric metric name.
	 * @param int    $default_value  Default value to use when metric value can't be retrieved.
	 *
	 * @return Array[]
	 */
	protected function getMetricDiffsFromPages(
		array $current_pages,
		array $previous_pages,
		$metric,
		$default_value = 0
	) {
		/**
		 * @var mixed[]
		 */
		$diffs = [];

		foreach ( $current_pages as $page ) {
			$diffs[ $page->getMeta( 'url' ) ] = [
				'page'    => $page,
				'current' => $page->getMeta( $metric, $default_value ),
			];
		}

		foreach ( $previous_pages as $page ) {
			$url = $page->getMeta( 'url' );

			if ( isset( $diffs[ $url ] ) ) {
				$diffs[ $url ]['previous'] = $page->getMeta( $metric, $default_value );
				$diffs[ $url ]['diff']     = $diffs[ $url ]['current'] - $diffs[ $url ]['previous'];
			}
		}

		$diffs = array_filter( $diffs, function( $data ) {
			return isset( $data['diff'] );
		} );

		return $diffs;
	}

	/**
	 * Return the `where` and `tooltip` variables from a list
	 * of pages. The variable value are used on the front end
	 * in the insight there.
	 *
	 * Example: Performance is down significantly on (where).
	 *
	 * @param Page[] $pages Page objects.
	 *
	 * @return Array<int,Array<string,string>> List of `where` and `tooltip` variables.
	 */
	protected function getWhereVariables( array $pages ) {
		$where      = __( 'some pages', 'nexcess-mapps' );
		$page_names = array_map( function( Page $page ) {
			return $page->getMeta( 'name', '' );
		}, $pages );
		$tooltip    = join( ', ', $page_names );

		if ( 1 === count( $pages ) ) {
			$tooltip = '';
			$page    = current( $pages );
			$where   = $page->getMeta( 'name', '' );
		} elseif ( count( $pages ) === count( $this->currentPages ) ) {
			$where = __( 'all pages', 'nexcess-mapps' );
		} elseif ( count( $pages ) / count( $this->currentPages ) > 0.66 ) {
			$where = __( 'most pages', 'nexcess-mapps' );
		}

		return [
			[
				'variable' => 'where',
				'value'    => (string) $where,
			],
			[
				'variable' => 'tooltip',
				'value'    => (string) $tooltip,
			],
		];
	}

	/**
	 * Return the `type`, `description`, `category`, `url` and `template`
	 * variables filled with relevant values.
	 *
	 * @return Array<string, string> List of `type`, `description`, `category`, `url` and `template` variables.
	 */
	public static function getTemplateData() {
		return [
			'type'        => static::getInsightType(),
			'category'    => static::getCategory(),
			'description' => static::getDescriptionText(),
			'url'         => static::getDescriptionURL(),
			'template'    => static::getTemplate(),
		];
	}

	/**
	 * From an array of `Page` objects, return one that corresponds with the
	 * provided `LighthouseReport` object.
	 *
	 * @param LighthouseReport $lighthouse_report Lighthouse report.
	 * @param Page[]           $pages             Pages to select from.
	 *
	 * @return Page|null Page object or null if no page was found.
	 */
	protected function getPageForLighthouseReport( LighthouseReport $lighthouse_report, array $pages ) {
		foreach ( $pages as $page ) {
			if ( $page->getMeta( 'url' ) === $lighthouse_report->getUrl() ) {
				return $page;
			}
		}
		return null;
	}

	/**
	 * Sorts the values assigned to individual page results
	 * into buckets using the prescription from `$buckets`.
	 *
	 * @param Array<mixed[]>               $values  Values to sort.
	 * @param Array<string, int[]|float[]> $buckets Buckets to sort into.
	 *
	 * @return Array<Page[]> Sorted pages.
	 */
	protected function valuesIntoBuckets( array $values, array $buckets ) {
		$results = [];

		foreach ( array_keys( $buckets ) as $bucket_label ) {
			$results[ $bucket_label ] = [];

			foreach ( $values as $data ) {
				$bucket_interval = $buckets[ $bucket_label ];
				if ( $data['value'] >= $bucket_interval[0] && $data['value'] <= $bucket_interval[1] ) {
					$results[ $bucket_label ][] = $data['page'];
				}
			}
		}

		return $results;
	}

	/**
	 * Generate insights meta given multiple sets of pages that fall
	 * into one of the buckets we want to generate insights for.
	 *
	 * @param Array<Page[]> $bucketed_values Buckets of pages.
	 *
	 * @return Array<mixed[]> List of insights meta.
	 */
	protected function bucketedValuesIntoInsights( array $bucketed_values ) {
		$insights = [];

		foreach ( $bucketed_values as $bucket_label => $pages ) {
			if ( ! $pages ) {
				continue;
			}

			$extra_variables = [
				[
					'variable' => 'qualifier',
					'value'    => $bucket_label,
				],
			];

			$insights[] = $this->pagesIntoInsight( $pages, $extra_variables );
		}

		return array_values( array_filter( $insights ) );
	}

	/**
	 * Returns insight metadata that also references pages that
	 * should be mentioned by the insight.
	 *
	 * @param Page[]       $pages           Pages that should be mentioned by the insight.
	 * @param Array<Array> $extra_variables Extra variables to be added to the insight.
	 *
	 * @return mixed[]|null Insight metadata or null if no pages were provided.
	 */
	protected function pagesIntoInsight( array $pages, $extra_variables = [] ) {
		if ( ! $pages ) {
			return null;
		}

		$sources   = [];
		$items_key = $this->getLighthouseItemsKeyPath();

		if ( $items_key ) {
			$page_urls = array_map(
				function ( $page ) {
					/**
					 * @var Page $page
					 */
					return $page->getMeta( 'url' );
				},
				$pages
			);

			$lighthouse_reports = array_filter(
				$this->lighthouseReports,
				function ( $lighthouse_report ) use ( $page_urls ) {
					/**
					 * @var LighthouseReport $lighthouse_report
					 */
					return in_array( $lighthouse_report->getUrl(), $page_urls, true );
				}
			);

			$file_urls = [];
			foreach ( $lighthouse_reports as $lighthouse_report ) {
				$items = $lighthouse_report->getValue( $items_key, [] );
				if ( is_array( $items ) ) {
					foreach ( $items as $item ) {
						if ( isset( $item->url ) && 0 === strpos( $item->url, 'http' ) ) {
							$file_urls[] = $item->url;
						}
					}
				}
			}

			$file_source = new FileSource();
			$sources     = array_map( [ $file_source, 'getSource' ], $file_urls );
			$sources     = array_filter( $sources );
			$sources     = array_unique( $sources, SORT_REGULAR );

			usort(
				$sources,
				function( $source_1, $source_2 ) {
					return $source_1['type'] < $source_2['type'] ? -1 : 1;
				}
			);
		}

		return [
			'type'      => static::getInsightType(),
			'variables' => array_merge(
				$extra_variables,
				$this->getWhereVariables( $pages )
			),
			'sources'   => $sources,
		];
	}

	/**
	 * Returns `true` if the same insight type has been also
	 * generated yesterday.
	 *
	 * @return bool
	 */
	protected function isRepeated() {
		foreach ( $this->previousInsights as $insight ) {
			$is_yesterdays_insight = 1 === $insight->createdDaysAgo();
			$is_the_same_insight   = static::getInsightType() === $insight->getMeta( 'type' );

			if ( $is_yesterdays_insight && $is_the_same_insight ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Returns a path to a key in the Lighthouse report JSON that contains
	 * a list of items (assets, files) that impacted the current metric.
	 *
	 * Overridden by individual generators where applicable.
	 *
	 * @return string Path to the key in the Lighthouse report JSON.
	 */
	protected function getLighthouseItemsKeyPath() {
		return '';
	}

	/**
	 * Returns whether the current insight type is muted.
	 *
	 * @return bool Whether the current insight type is muted.
	 */
	public function isMuted() {
		return in_array( self::getInsightType(), $this->mutedInsightTypes, true );
	}
}
