<?php

namespace Nexcess\MAPPS\Integrations\PerformanceMonitor\Generator;

use Nexcess\MAPPS\Integrations\PerformanceMonitor;
use Nexcess\MAPPS\Integrations\PerformanceMonitor\Generator\InsightGenerator\BaseInsightTypeGenerator;
use Nexcess\MAPPS\Integrations\PerformanceMonitor\LighthouseReport;
use Nexcess\MAPPS\Integrations\PerformanceMonitor\Model\Insight;
use Nexcess\MAPPS\Integrations\PerformanceMonitor\Model\Page;
use Nexcess\MAPPS\Integrations\PerformanceMonitor\Model\SiteChange;

/**
 * The `InsightGenerator` is responsible for generating post meta for a `Insight`
 * instance once all Lighthouse reports are available for a given day.
 */
class InsightGenerator extends BaseGenerator {

	/**
	 * Insights can be muted. We store a list insight type IDs in WP options
	 * along with a timestamp when they were muted.
	 *
	 * @var string
	 */
	const MUTED_INSIGHTS_OPTION_KEY = PerformanceMonitor::DATA_PREFIX . 'muted_insights';

	/**
	 * Duration for how long insights should remain muted.
	 *
	 * @var int
	 */
	const MUTED_INSIGHTS_DURATION = WEEK_IN_SECONDS;

	/**
	 * The individual generators responsible for generating
	 * different types of insights.
	 *
	 * @var Array<class-string, int>
	 */
	public static $generators = [
		InsightGenerator\ExtrapolationRevenue::class      => 2,
		InsightGenerator\ExtrapolationVisitors::class     => 2,
		InsightGenerator\Performance::class               => 3,
		InsightGenerator\PerformanceChange::class         => 3,
		InsightGenerator\CLS::class                       => 4,
		InsightGenerator\FID::class                       => 4,
		InsightGenerator\FIDChange::class                 => 4,
		InsightGenerator\LCP::class                       => 4,
		InsightGenerator\LCPChange::class                 => 4,
		InsightGenerator\TTI::class                       => 4,
		InsightGenerator\TTIChange::class                 => 4,
		InsightGenerator\ResourceCountByTypeChange::class => 5,
		InsightGenerator\ResourceTotalWeightChange::class => 5,
		InsightGenerator\ResourceWeightByType::class      => 5,
		InsightGenerator\BootupTime::class                => 6,
		InsightGenerator\RenderBlockingResources::class   => 6,
		InsightGenerator\FontDisplay::class               => 6,
		InsightGenerator\TBT::class                       => 6,
		InsightGenerator\TBTChange::class                 => 6,
		InsightGenerator\ThirdPartyBlockingTime::class    => 6,
		InsightGenerator\NoDocumentWrite::class           => 7,
		InsightGenerator\Redirects::class                 => 7,
	];

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
	 * Constructor.
	 *
	 * @param LighthouseReport[] $lighthouse_reports `LighthouseReport` objects for a given day.
	 * @param Page[]             $current_pages      `Page` objects generated today.
	 * @param Page[]             $previous_pages     `Page` objects to compare today's results to.
	 * @param Insight[]          $previous_insights  `Insight` objects generated in the last week.
	 * @param SiteChange[]       $site_changes       `SiteChange` objects generated today.
	 */
	public function __construct(
		array $lighthouse_reports,
		array $current_pages,
		array $previous_pages = [],
		array $previous_insights = [],
		array $site_changes = []
	) {
		$this->lighthouseReports = $lighthouse_reports;
		$this->currentPages      = $current_pages;
		$this->previousPages     = $previous_pages;
		$this->previousInsights  = $previous_insights;
		$this->siteChanges       = $site_changes;
	}

	/**
	 * Generate a post meta array corresponding with `Insights` objects.
	 *
	 * @return Array<Array>
	 */
	public function generate() {
		$insights            = [];
		$muted_insight_types = self::getMutedInsightTypes();

		foreach ( array_keys( self::$generators ) as $generator ) {
			/**
			 * @var InsightGenerator\BaseInsightTypeGenerator
			 */
			$generator_instance = new $generator(
				$this->lighthouseReports,
				$this->currentPages,
				$this->previousPages,
				$this->previousInsights,
				$this->siteChanges,
				$muted_insight_types
			);

			if ( ! $generator_instance->isMuted() ) {
				$insights = array_merge( $insights, $generator_instance->generate() );
			}
		}

		return $insights;
	}

	/**
	 * Returns the description of all the different insight types.
	 *
	 * @return Array<Array>
	 */
	public static function getInsightTypes() {
		$insight_types = [];

		/**
		 * @var BaseInsightTypeGenerator $insight_generator
		 * @var int                      $priority
		 */
		foreach ( self::$generators as $insight_generator => $priority ) {
			$insight_types[] = array_merge(
				$insight_generator::getTemplateData(),
				[ 'priority' => $priority ]
			);
		}

		/**
		 * Make sure the types are sorted by priority.
		 */
		usort(
			$insight_types,
			function( $type_1, $type_2 ) {
				return $type_1['priority'] > $type_2['priority'] ? 1 : -1;
			}
		);

		return $insight_types;
	}

	/**
	 * Returns the list of currently muted insight types.
	 *
	 * @return Array<Array>
	 */
	public static function getMutedInsightTypes() {
		$muted_insights             = get_option( self::MUTED_INSIGHTS_OPTION_KEY, [] );
		$non_expired_muted_insights = [];

		foreach ( $muted_insights as $type => $muted_time ) {
			if ( time() - $muted_time < self::MUTED_INSIGHTS_DURATION ) {
				$non_expired_muted_insights[] = $type;
			}
		}

		return $non_expired_muted_insights;
	}
}
