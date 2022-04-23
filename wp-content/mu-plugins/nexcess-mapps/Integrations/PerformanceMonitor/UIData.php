<?php

namespace Nexcess\MAPPS\Integrations\PerformanceMonitor;

use Nexcess\MAPPS\Integrations\PerformanceMonitor;
use Nexcess\MAPPS\Integrations\PerformanceMonitor\Generator\InsightGenerator;
use Nexcess\MAPPS\Integrations\PerformanceMonitor\Generator\SiteChangeGenerator;
use Nexcess\MAPPS\Integrations\PerformanceMonitor\Model\Report;
use Nexcess\MAPPS\Integrations\PerformanceMonitor\Query\InsightQuery;
use Nexcess\MAPPS\Integrations\PerformanceMonitor\Query\PageQuery;
use Nexcess\MAPPS\Integrations\PerformanceMonitor\Query\ReportQuery;
use Nexcess\MAPPS\Integrations\PerformanceMonitor\Query\SiteChangeQuery;

use Nexcess\MAPPS\Routes\PerformanceMonitorDataRoute;
use Nexcess\MAPPS\Routes\PerformanceMonitorMuteRoute;
use Nexcess\MAPPS\Services\Managers\RouteManager;

/**
 * The `UIData` class is responsible for generating structured
 * data necessary to initialize the React application.
 */
class UIData {

	/**
	 * Number of reports displayed per page of timeline.
	 */
	const POSTS_PER_PAGE = 10;

	/**
	 * Performance Monitor instance.
	 *
	 * @var PerformanceMonitor
	 */
	protected $performanceMonitor;

	/**
	 * RouteManager instance.
	 *
	 * @var RouteManager
	 */
	protected $routeManager;

	/**
	 * ReportQuery instance.
	 *
	 * @var ReportQuery
	 */
	protected $reportQuery;

	/**
	 * PageQuery instance.
	 *
	 * @var PageQuery
	 */
	protected $pageQuery;

	/**
	 * SiteChangeQuery instance.
	 *
	 * @var SiteChangeQuery
	 */
	protected $siteChangeQuery;

	/**
	 * InsightQuery instance.
	 *
	 * @var InsightQuery
	 */
	protected $insightQuery;

	/**
	 * Constructor.
	 *
	 * @param PerformanceMonitor $performance_monitor
	 * @param RouteManager       $route_manager
	 * @param ReportQuery        $report_query
	 * @param PageQuery          $page_query
	 * @param SiteChangeQuery    $site_change_query
	 * @param InsightQuery       $insight_query
	 */
	public function __construct(
		PerformanceMonitor $performance_monitor,
		RouteManager $route_manager,
		ReportQuery $report_query,
		PageQuery $page_query,
		SiteChangeQuery $site_change_query,
		InsightQuery $insight_query
	) {
		$this->performanceMonitor = $performance_monitor;
		$this->routeManager       = $route_manager;
		$this->reportQuery        = $report_query;
		$this->pageQuery          = $page_query;
		$this->siteChangeQuery    = $site_change_query;
		$this->insightQuery       = $insight_query;
	}

	/**
	 * Gets all data necessary to initialize the React application.
	 *
	 * @param int $page The page number of results to return.
	 *
	 * @return Array<mixed>
	 */
	public function getAll( $page = 1 ) {
		$reports = $this->reportQuery->query( [
			'per_page' => self::POSTS_PER_PAGE,
			'page'     => $page,
		] );
		$headers = $this->reportQuery->getLastHeaders();

		return [
			'overview'   => $this->performanceMonitor->getOverviewData(),
			'reports'    => $this->getReportsData( $reports ),
			'pagination' => [
				'currentPage'  => $page,
				'postsPerPage' => self::POSTS_PER_PAGE,
				'totalPages'   => isset( $headers['X-WP-TotalPages'] ) ? intval( $headers['X-WP-TotalPages'] ) : 1,
			],
			'config'     => [
				'api'             => $this->getRestRouteInfo(),
				'siteChangeTypes' => SiteChangeGenerator::getSiteChangeTypes(),
				'insightTypes'    => InsightGenerator::getInsightTypes(),
				'muted'           => InsightGenerator::getMutedInsightTypes(),
				'host'            => $this->performanceMonitor->getDataCenterNameAndLocation(),
			],
		];
	}

	/**
	 * @param Array<Report> $reports Report instances.
	 *
	 * @return Array<mixed>
	 */
	protected function getReportsData( array $reports ) {
		$reports_data = [];
		foreach ( $reports as $report ) {
			$report_summary = $report->getMeta();

			/**
			 * Remove data that is not needed on the front end.
			 */
			if ( isset( $report_summary['wp_environment'] ) ) {
				unset( $report_summary['wp_environment'] );
			}

			$single_report_data = [
				'date'         => $report->getDate(),
				'alert'        => intval( $report_summary['insights'] ) > 0,
				'summary'      => $report_summary,
				'site_changes' => $this->getSiteChangesMeta( $report ),
				'insights'     => $this->getInsightsMeta( $report ),
				'pages'        => $this->getPagesMeta( $report ),
			];

			$reports_data[] = $single_report_data;
		}

		return $reports_data;
	}

	/**
	 * Returns meta information associated with `SiteChange` objects
	 * that belong under this `Report`.
	 *
	 * @param Report $report The parent `Report` instance.
	 *
	 * @return Array<mixed>
	 */
	protected function getSiteChangesMeta( $report ) {
		$site_changes = $this->siteChangeQuery->getByParent( $report->getAssociatedPostId() );

		return array_map( function( $site_change ) {
			return $site_change->getMeta();
		}, $site_changes );
	}

	/**
	 * Returns meta information associated with `Insight` objects
	 * that belong under this `Report`.
	 *
	 * @param Report $report The parent `Report` instance.
	 *
	 * @return Array<mixed>
	 */
	protected function getInsightsMeta( $report ) {
		$insights = $this->insightQuery->getByParent(
			$report->getAssociatedPostId(),
			[
				'per_page' => 100,
			]
		);

		return array_map( function( $insight ) {
			return $insight->getMeta();
		}, $insights );
	}

	/**
	 * Returns meta information associated with `Page` objects
	 * that belong under this `Report`.
	 *
	 * @param Report $report The parent `Report` instance.
	 *
	 * @return Array<mixed>
	 */
	protected function getPagesMeta( $report ) {
		$pages = $this->pageQuery->getByParent( $report->getAssociatedPostId() );

		return array_map( function( $page ) {
			return $page->getMeta();
		}, $pages );
	}

	/**
	 * Get the REST route information.
	 *
	 * @return mixed[] The REST route information.
	 */
	protected function getRestRouteInfo() {
		$routes = $this->routeManager->getRoutes();
		$info   = [
			'nonce' => wp_create_nonce( 'wp_rest' ),
		];

		foreach ( $routes as $route ) {
			if ( $route instanceof PerformanceMonitorDataRoute ) {
				$info['dataRouteFormat'] = $route->getRouteFormat();
			}
			if ( $route instanceof PerformanceMonitorMuteRoute ) {
				$info['muteRouteFormat'] = $route->getRouteFormat();
			}
		}

		return $info;
	}
}
