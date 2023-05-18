<?php

/**
 * Performance Monitor API UI and insights engine.
 */

namespace Nexcess\MAPPS\Integrations;

use DateInterval;
use DateTime;
use Nexcess\MAPPS\Concerns\HasAdminPages;
use Nexcess\MAPPS\Concerns\HasAssets;
use Nexcess\MAPPS\Concerns\HasCronEvents;
use Nexcess\MAPPS\Concerns\ManagesGroupedOptions;
use Nexcess\MAPPS\Integrations\PerformanceMonitor\Api;
use Nexcess\MAPPS\Integrations\PerformanceMonitor\CustomPostTypes;
use Nexcess\MAPPS\Integrations\PerformanceMonitor\DashboardWidget;
use Nexcess\MAPPS\Integrations\PerformanceMonitor\Factory\InsightFactory;
use Nexcess\MAPPS\Integrations\PerformanceMonitor\Factory\PageFactory;
use Nexcess\MAPPS\Integrations\PerformanceMonitor\Factory\ReportFactory;
use Nexcess\MAPPS\Integrations\PerformanceMonitor\Factory\SiteChangeFactory;
use Nexcess\MAPPS\Integrations\PerformanceMonitor\Generator\InsightGenerator;
use Nexcess\MAPPS\Integrations\PerformanceMonitor\Generator\PageGenerator;
use Nexcess\MAPPS\Integrations\PerformanceMonitor\Generator\ReportGenerator;
use Nexcess\MAPPS\Integrations\PerformanceMonitor\Generator\SiteChangeGenerator;
use Nexcess\MAPPS\Integrations\PerformanceMonitor\LighthouseReport;
use Nexcess\MAPPS\Integrations\PerformanceMonitor\Query\InsightQuery;
use Nexcess\MAPPS\Integrations\PerformanceMonitor\Query\PageQuery;
use Nexcess\MAPPS\Integrations\PerformanceMonitor\Query\ReportQuery;
use Nexcess\MAPPS\Integrations\PerformanceMonitor\Query\SiteChangeQuery;
use Nexcess\MAPPS\Integrations\PerformanceMonitor\UIData;
use Nexcess\MAPPS\Modules\Telemetry;
use Nexcess\MAPPS\Modules\VisualComparison;
use Nexcess\MAPPS\Routes\PerformanceMonitorAuthRoute;
use Nexcess\MAPPS\Routes\PerformanceMonitorDataRoute;
use Nexcess\MAPPS\Routes\PerformanceMonitorMuteRoute;
use Nexcess\MAPPS\Routes\PerformanceMonitorReportRoute;
use Nexcess\MAPPS\Services\Logger;
use Nexcess\MAPPS\Services\Managers\RouteManager;
use Nexcess\MAPPS\Services\Options;
use Nexcess\MAPPS\Settings;
use Nexcess\MAPPS\Support\Helpers;
use StellarWP\PluginFramework\Services\FeatureFlags;

class PerformanceMonitor extends Integration {
	use HasAdminPages;
	use HasAssets;
	use HasCronEvents;
	use ManagesGroupedOptions;

	/**
	 * Prefix used to "namespace" all data items stored in the database, i.e.
	 * custom post types, meta items, options, ...
	 */
	const DATA_PREFIX = 'pm_';

	/**
	 * Overview data contain average performance scores, load times
	 * and the number of site changes.
	 */
	const OVERVIEW_OPTION_KEY = self::DATA_PREFIX . 'overview';

	/**
	 * The integration caches a hash of modified times of files that
	 * affect permalinks, i.e. files that register routes and CPTs.
	 *
	 * @var string
	 */
	const REWRITES_TRANSIENT_KEY = self::DATA_PREFIX . 'rewrites_hash';

	/**
	 * Action called daily by Cron.
	 *
	 * @var string
	 */
	const CRON_HOOK = self::DATA_PREFIX . 'request_lighthouse_reports';

	/**
	 * Action called when the API was unreachable on a first try.
	 *
	 * @var string
	 */
	const CRON_RETRY_HOOK = self::DATA_PREFIX . 'retry_request_lighthouse_reports';

	/**
	 * Action called when the request for Lighthouse reports times out.
	 *
	 * @var string
	 */
	const CRON_CANCEL_HOOK = self::DATA_PREFIX . 'cancel_request_lighthouse_reports';

	/**
	 * The option for disabling the performance monitor.
	 */
	const OPTION_NAME = 'nexcess_mapps_performance_monitor';

	/**
	 * The key used in the telemetry report which contains the relevant integration info.
	 */
	const TELEMETRY_FEATURE_KEY = 'performance_monitor';

	/**
	 * @var \Nexcess\MAPPS\Integrations\PerformanceMonitor\Api
	 */
	protected $api;

	/**
	 * @var \StellarWP\PluginFramework\Services\FeatureFlags
	 */
	protected $featureFlags;

	/**
	 * @var \Nexcess\MAPPS\Services\Logger
	 */
	protected $logger;

	/**
	 * @var \Nexcess\MAPPS\Services\Options
	 */
	protected $options;

	/**
	 * @var \Nexcess\MAPPS\Services\Managers\RouteManager
	 */
	protected $routeManager;

	/**
	 * @var \Nexcess\MAPPS\Settings
	 */
	protected $settings;

	/**
	 * @var \Nexcess\MAPPS\Integrations\PerformanceMonitor\UIData
	 */
	protected $uiData;

	/**
	 * @var \Nexcess\MAPPS\Modules\VisualComparison
	 */
	protected $visualComparison;

	/**
	 * @param \Nexcess\MAPPS\Settings                          $settings
	 * @param \Nexcess\MAPPS\Modules\VisualComparison          $visual_comparison
	 * @param \Nexcess\MAPPS\Services\Managers\RouteManager    $route_manager
	 * @param \Nexcess\MAPPS\Services\Logger                   $logger
	 * @param \StellarWP\PluginFramework\Services\FeatureFlags $feature_flags
	 * @param \Nexcess\MAPPS\Services\Options                  $options
	 */
	public function __construct(
		Settings $settings,
		VisualComparison $visual_comparison,
		RouteManager $route_manager,
		Logger $logger,
		FeatureFlags $feature_flags,
		Options $options
	) {
		$this->settings         = $settings;
		$this->visualComparison = $visual_comparison;
		$this->routeManager     = $route_manager;
		$this->logger           = $logger;
		$this->featureFlags     = $feature_flags;
		$this->options          = $options;
	}

	/**
	 * Determine whether or not this integration should be loaded.
	 *
	 * @return bool Whether or not this integration be loaded in this environment.
	 */
	public function shouldLoadIntegration() {
		return $this->settings->is_production_site
				&& (bool) $this->settings->performance_monitor_endpoint
				&& ! $this->settings->is_temp_domain;
	}

	/**
	 * Set up the performance monitor integration.
	 */
	public function setup() {
		if ( $this->getPerformanceMonitorSetting() ) {
			$this->addHooks();
		}

		// Manually adding our action outstside of the addHooks() method, as we
		// need it to always be hooked in, no matter what the setting, so we can
		// trigger things when the setting is changed.
		add_action( 'Nexcess\MAPPS\Options\Update', [ $this, 'maybeClearCronEvents' ], 10, 3 );

		$this->registerOption();
	}

	/**
	 * Retrieve all filters for the integration.
	 *
	 * @return array[]
	 */
	protected function getFilters() {
		return [
			[ Telemetry::REPORT_DATA_FILTER, [ $this, 'addFeatureToTelemetry' ] ],
		];
	}

	/**
	 * Retrieve all actions for the integration.
	 *
	 * @return array[]
	 */
	protected function getActions() {
		return [
			[
				'init',
				[ $this, 'initializeIntegration' ],
				11,
			],
			[
				self::CRON_HOOK,
				[ $this, 'requestLighthouseReports' ],
				10,
			],
			[
				self::CRON_RETRY_HOOK,
				[ $this, 'requestLighthouseReports' ],
				10,
				1,
			],
			[
				self::CRON_CANCEL_HOOK,
				[ $this, 'cancelLighthouseReportsRequest' ],
				10,
			],
			[
				'wp_dashboard_setup',
				[ $this, 'registerDashboardWidget' ],
				10,
			],
		];
	}

	/**
	 * Enable performance monitoring.
	 */
	public function enablePerformanceMonitor() {
		$this->getOption()->set( 'performance_monitor_is_enabled', true )->save();
	}

	/**
	 * Disable performance monitoring.
	 */
	public function disablePerformanceMonitor() {
		$this->getOption()->set( 'performance_monitor_is_enabled', false )->save();
		$this->clearCronEvents();
	}

	/**
	 * Clears scheduled hook for performance monitor.
	 */
	public function clearCronEvents() {
		wp_clear_scheduled_hook( self::CRON_HOOK );
	}

	/**
	 * Clear the cron events when our option changes from enabled to disabled.
	 * Hooked into Nexcess\MAPPS\Options\Update.
	 *
	 * @param array $key   The key of the option that was updated.
	 * @param mixed $value The old value of the option.
	 * @param mixed $prev  The new value of the option.
	 */
	public function maybeClearCronEvents( $key, $value, $prev ) {
		if (
			is_array( $key )
			&& isset( $key[0] )
			&& 'performance_monitor_is_enabled' === $key[1]
			&& false === $value
			&& $value !== $prev
		) {
			$this->clearCronEvents();
		}
	}

	/**
	 * Get current setting for performance monitoring.
	 *
	 * @return bool
	 */
	public function getPerformanceMonitorSetting() {
		return $this->getOption()->performance_monitor_is_enabled;
	}

	/**
	 * Add a toggle to the settings page.
	 */
	public function registerOption() {
		$this->options->addOption(
			[ self::OPTION_NAME, 'performance_monitor_is_enabled' ],
			'checkbox',
			__( 'Enable Performance Monitoring', 'nexcess-mapps' ),
			[
				'description' => __( 'The Nexcess Plugin Performance Monitor watches your site every day so you don’t just see the problem - you know how to fix it.', 'nexcess-mapps' ),
				'default'     => true,
			]
		);
	}

	/**
	 * Initialize the whole integration.
	 */
	public function initializeIntegration() {
		/**
		 * Always register routes that might be called by the Lighthouse API.
		 */
		$this->initializeApiRoutes();

		if ( current_user_can( 'manage_options' ) || wp_doing_cron() ) {
			CustomPostTypes::registerPostTypes();

			$this->initializeCronTask();
			$this->initializeUIRoutes();
			$this->registerReportsPage();
			$this->maybeFlushPermalinks();
		}
	}

	/**
	 * Registers a cron task to retrieve performance reports
	 * from the API daily.
	 */
	protected function initializeCronTask() {
		if ( $this->getPerformanceMonitorSetting() ) {
			$this->registerCronEvent(
				self::CRON_HOOK,
				'daily',
				$this->getCronTime()
			)->scheduleEvents();
		}
	}

	/**
	 * Requests Lighthouse reports from the API.
	 *
	 * @param bool $is_retry Whether this is a retry.
	 */
	public function requestLighthouseReports( $is_retry = false ) {
		/**
		 * It's possible that due to network conditions the API is not going
		 * to provide all the requested Lighthouse Reports.
		 *
		 * In that case we want to stop waiting and process all the data
		 * that did arrive.
		 */
		if ( ! $this->getPerformanceMonitorSetting() ) {
			return;
		}

		$this->registerCronEvent(
			self::CRON_CANCEL_HOOK,
			null, // No interval = One time event.
			new \DateTime( '+ 30 minutes', wp_timezone() ) // Cancel in 30 minutes.
		)->scheduleEvents();

		try {
			$this->api->subscribe( $this->visualComparison->getUrls() );
		} catch ( \Exception $e ) {
			if ( ! $is_retry ) {
				$message = sprintf(
					'%s. Will re-attempt to make the API request in 5 minutes.',
					$e->getMessage()
				);
				$this->logger->warning( $message );

				$this->registerCronEvent(
					self::CRON_RETRY_HOOK,
					null, // No interval = One time event.
					new \DateTime( '+ 5 minutes', wp_timezone() ),
					[ true ] // Indicates that this is a retry.
				)->scheduleEvents();
			} else {
				$this->logger->error( $e->getMessage() );
				return;
			}
		}
	}

	/**
	 * Stops waiting for any additional responses from the API and
	 * processes the data that has arrived so far.
	 */
	public function cancelLighthouseReportsRequest() {
		$this->api->done();
	}

	/**
	 * Get a random time during the night when the daily cron event
	 * should be executed.
	 *
	 * Assigns the time based on the current site's hostname.
	 *
	 * @return \DateTimeInterface
	 */
	protected function getCronTime() {
		$site_url      = get_home_url();
		$site_hostname = wp_parse_url( $site_url, PHP_URL_HOST );
		$site_hash     = md5( $site_hostname );

		/**
		 * We are converting the last 4 digits of a MD5 hash string to
		 * a decimal number, e.g. af01 -> 44801.
		 *
		 * We then divide this number by 5, which yields a number
		 * between 0 and 13107. Now, 13107 seconds ~= 3.5 hours,
		 * which gives us a cron start time between 1am and 4.30am.
		 */
		$seconds_delta_hex    = substr( $site_hash, -4 );
		$seconds_delta        = base_convert( $seconds_delta_hex, 16, 10 );
		$seconds_delta_scaled = round( $seconds_delta / 5 );

		$cron_datetime = new \DateTime( 'tomorrow 1am', wp_timezone() );
		$cron_datetime->modify( sprintf( '+ %d seconds', $seconds_delta_scaled ) );

		return $cron_datetime;
	}

	/**
	 * Flush permalinks once when any of the files that define custom
	 * endpoints change.
	 */
	protected function maybeFlushPermalinks() {
		$observed_files = [
			__FILE__,
			dirname( __FILE__ ) . '/PerformanceMonitor/CustomPostTypes.php',
		];

		$file_mtimes = [];
		foreach ( $observed_files as $filename ) {
			if ( ! file_exists( $filename ) ) {
				continue;
			}
			$file_mtimes[] = filemtime( $filename );
		}

		$previous_hash = (string) get_transient( self::REWRITES_TRANSIENT_KEY );
		$current_hash  = md5( join( '-', $file_mtimes ) );

		if ( $previous_hash !== $current_hash ) {
			set_transient( self::REWRITES_TRANSIENT_KEY, $current_hash );
			flush_rewrite_rules();
		}
	}

	/**
	 * Registers custom routes used by the UI app.
	 */
	protected function initializeUIRoutes() {
		$this->uiData = new UIData(
			$this,
			$this->routeManager,
			new ReportQuery(),
			new PageQuery(),
			new SiteChangeQuery(),
			new InsightQuery()
		);

		$routes = [
			new PerformanceMonitorDataRoute( $this->uiData ),
			new PerformanceMonitorMuteRoute(),
		];

		foreach ( $routes as $route ) {
			$this->routeManager->addRoute( $route );
		}
	}

	/**
	 * Registers custom routes that provide data to and receive
	 * data from the Lighthouse API.
	 */
	protected function initializeApiRoutes() {
		$this->api = new Api(
			$this,
			$this->routeManager,
			$this->settings->performance_monitor_endpoint
		);

		$routes = [
			new PerformanceMonitorAuthRoute( $this->api ),
			new PerformanceMonitorReportRoute( $this->api ),
		];

		foreach ( $routes as $route ) {
			$this->routeManager->addRoute( $route );
		}
	}

	/**
	 * Adds the Performance Monitor page to the submenu of Nexcess.
	 */
	public function registerReportsPage() {
		add_action( 'admin_menu', function () {
			add_submenu_page(
				Dashboard::ADMIN_MENU_SLUG,
				_x( 'Performance Monitor', 'page title', 'nexcess-mapps' ),
				_x( 'Performance Monitor', 'menu item title', 'nexcess-mapps' ),
				'manage_options',
				'mapps-performance-monitor',
				[ $this, 'renderMenuPage' ]
			);
		} );
	}

	/**
	 * Renders the Performance Monitor template that contains
	 * the root element for React application that displays
	 * performance reports data.
	 */
	public function renderMenuPage() {
		// Load Nexcess admin script and the Performance Monitor React app.
		wp_enqueue_script( 'nexcess-mapps-admin' );

		$this->enqueueScript( 'nexcess-mapps-performance-monitor', 'performance-monitor.js', [ 'wp-element', 'underscore' ] );
		$this->enqueueStyle( 'nexcess-mapps-performance-monitor', 'performance-monitor.css', [], 'screen' );

		$this->injectScriptData(
			'nexcess-mapps-performance-monitor',
			'performanceMonitor',
			$this->uiData->getAll()
		);

		$this->renderTemplate( 'performancemonitor' );
	}

	/**
	 * Receive an array of all daily reports (one per each observed page)
	 * and extract and save all relevant data from them.
	 *
	 * @param string[] $lighthouse_reports_raw_strings Array of strings.
	 */
	public function processLighthouseReports( array $lighthouse_reports_raw_strings ) {
		/**
		 * @var LighthouseReport[]
		 */
		$lighthouse_reports = [];

		/**
		 * @var Array<string, int>
		 */
		$overview_data = [];

		/**
		 * Generate `LighthouseReport` instances.
		 */
		foreach ( $lighthouse_reports_raw_strings as $report_json_string ) {
			$lighthouse_reports[] = new LighthouseReport( $report_json_string );
		}

		/**
		 * Generate current `Report` instance.
		 */
		$report_query   = new ReportQuery();
		$report_factory = new ReportFactory();

		$previous_report = $report_query->getMostRecent();

		$report_generator = new ReportGenerator(
			$lighthouse_reports,
			$previous_report
		);

		try {
			$report_meta = $report_generator->generate();
			$report      = $report_factory->create( $report_meta );
		} catch ( \Exception $e ) {
			/**
			 * When a `Report` post can't be created,
			 * we need to bail out and treat this as an
			 * error.
			 */
			$this->logger->error( $e->getMessage() );
			return;
		}

		/**
		 * Generate `Page` instances.
		 */
		$previous_pages_by_url = [];
		if ( $previous_report ) {
			$page_query     = new PageQuery();
			$previous_pages = $page_query->getByParent( $previous_report->getAssociatedPostId() );

			foreach ( $previous_pages as $previous_page ) {
				$previous_pages_by_url[ $previous_page->getMeta( 'url' ) ] = $previous_page;
			}
		}

		$current_pages  = [];
		$previous_pages = [];

		// Create a map of url => description for visual comparison URLs.
		$observed_urls = array_reduce(
			$this->visualComparison->getUrls(),
			function ( $urls, $url ) {
				$permalink_https          = preg_replace( '~^http\:~', 'https:', $url->getPermalink() );
				$urls[ $permalink_https ] = $url->getDescription();

				return $urls;
			},
			[]
		);

		foreach ( $lighthouse_reports as $lighthouse_report ) {
			$url           = $lighthouse_report->getUrl();
			$requested_url = $lighthouse_report->getRequestedUrl();
			$previous_page = null;

			if ( $url && array_key_exists( $url, $previous_pages_by_url ) ) {
				$previous_page    = $previous_pages_by_url[ $url ];
				$previous_pages[] = $previous_page;
			}

			/**
			 * Use URL as a default description, but try to retrieve a user provided name
			 * from the list of observed URLs.
			 */
			$requested_url_https = preg_replace( '~^http\:~', 'https:', $requested_url );
			$page_description    = ! empty( $observed_urls[ $requested_url_https ] )
				? $observed_urls[ $requested_url_https ]
				: esc_url( $url );

			$page_generator = new PageGenerator(
				$page_description,
				$lighthouse_report,
				$previous_page
			);
			$page_factory   = new PageFactory();

			$current_pages[] = $page_factory->create( $page_generator->generate(), $report );
		}

		/**
		 * Generate `SiteChange` instances.
		 */
		$site_changes = [];
		if ( $previous_report ) {
			$site_changes_generator = new SiteChangeGenerator(
				$previous_report,
				$report
			);

			$site_change_meta_sets = $site_changes_generator->generate();
			$number_of_changes     = count( $site_change_meta_sets );

			$site_change_factory = new SiteChangeFactory();
			foreach ( $site_change_meta_sets as $site_change_meta ) {
				try {
					$site_changes[] = $site_change_factory->create( $site_change_meta, $report );
				} catch ( \Exception $e ) {
					/**
					 * When a site change fails to create, that's not a critical
					 * issue. We just decrease the number of changes reported,
					 * log a warning message and otherwise act like nothing happened.
					 *
					 * In effect, we will fail to report on this site change
					 * in the Performance Monitor UI, which is unfortunate, but doesn't
					 * flat out break the experience.
					 */
					$number_of_changes--;
					$this->logger->warning( $e->getMessage() );
				}
			}

			$overview_data['changes'] = $number_of_changes;
			$report->setMeta( 'changes', $number_of_changes );
		}

		/**
		 * Generate `Insight` instances.
		 */
		$current_date   = new DateTime( 'today midnight', wp_timezone() );
		$seven_days_ago = $current_date->sub( DateInterval::createFromDateString( '7 days' ) );

		$insights_query    = new InsightQuery();
		$previous_insights = $insights_query->query( [
			'per_page' => 100,
			'after'    => $seven_days_ago->format( DateTime::ATOM ),
		] );

		$insights_generator = new InsightGenerator(
			$lighthouse_reports,
			$current_pages,
			$previous_pages,
			$previous_insights,
			$site_changes
		);

		$insights_factory   = new InsightFactory();
		$insight_meta_sets  = $insights_generator->generate();
		$number_of_insights = count( $insight_meta_sets );

		foreach ( $insight_meta_sets as $insight_meta ) {
			try {
				$insights_factory->create( $insight_meta, $report );
			} catch ( \Exception $e ) {
				/**
				 * When an insight fails to create, that's not a critical
				 * issue. We just decrease the number of insights reported,
				 * log a warning message and otherwise act like nothing happened.
				 *
				 * In effect, we will fail to report this insight
				 * in the Performance Monitor UI, which is unfortunate, but doesn't
				 * flat out break the experience.
				 */
				$number_of_insights--;
				$this->logger->warning( $e->getMessage() );
			}
		}

		$overview_data['insights'] = $number_of_insights;
		$report->setMeta( 'insights', $number_of_insights );

		/**
		 * Generate overview data.
		 */
		$performance_scores = [];
		$load_times         = [];

		foreach ( $lighthouse_reports as $lighthouse_report ) {
			$summary              = $lighthouse_report->getSummary();
			$performance_scores[] = $summary['score'];
			$load_times[]         = $summary['load_time'];
		}

		$overview_data['score']     = Helpers::calculateIntegerAverage( $performance_scores );
		$overview_data['load_time'] = Helpers::calculateIntegerAverage( $load_times );

		/**
		 * Saves the overview data in the site's options.
		 */
		$this->setOverviewData( $overview_data );

		/**
		 * At this point we have stored the number of site changes
		 * and insights into the `Report` meta.
		 *
		 * Calling `save()` saves the modified object to database.
		 */
		try {
			$report->save();
		} catch ( \Exception $e ) {
			/**
			 * When we're unable to save the report meta,
			 * we'll try to avoid displaying incorrect data
			 * by removing the `Report` post.
			 *
			 * We also log this event as an error, because this
			 * will lead to a current day data missing from
			 * the timeline.
			 */
			$report->delete();
			$this->logger->error( $e->getMessage() );
			return;
		}
	}

	/**
	 * Saves the overview data as site options.
	 *
	 * @param int[] $overview_data Overview data to be stored in options.
	 */
	protected function setOverviewData( array $overview_data ) {
		update_option( self::OVERVIEW_OPTION_KEY, $overview_data, false );
	}

	/**
	 * Returns the overview data displayed in the plugin dashboard.
	 *
	 * @return int[]
	 */
	public function getOverviewData() {
		$active_plugins = get_option( 'active_plugins', [] );
		$overview_data  = get_option( self::OVERVIEW_OPTION_KEY, [] );

		$overview_data['plugins'] = count( $active_plugins );

		return $overview_data;
	}

	/**
	 * Instantiates the dashboard widget.
	 */
	public function registerDashboardWidget() {
		if ( current_user_can( 'manage_options' ) ) {
			new DashboardWidget(
				$this,
				new ReportQuery(),
				new InsightQuery()
			);
		}
	}

	/**
	 * Get the host location.
	 *
	 * @return string Data center name & location.
	 */
	public function getHostLocation() {
		$hostname = gethostname();
		if ( ! $hostname ) {
			return 'unknown';
		}

		$hostname = explode( '.', $hostname );

		return isset( $hostname[1] ) ? $hostname[1] : 'unknown';
	}

	/**
	 * Get the data center name.
	 *
	 * @param string $location The location of the host, e.g. 'us-west-1'.
	 *
	 * @return string Host name.
	 */
	public function getDataCenterName( $location ) {
		$datacenter_names = [
			'us‐midwest‐1' => 'US Midwest Southfield, MI',
			'us‐west‐1'    => 'US West San Jose, CA',
			'us‐south‐1'   => 'US South Miami, FL',
			'uk‐south‐1'   => 'UK South Surrey, UK',
			'uk‐south‐2'   => 'UK South West Sussex, UK',
			'nl‐west‐1'    => 'The Netherlands West Amsterdam, NL',
			'au‐south‐1'   => 'Australia South Sydney, NSW',
		];

		return isset( $datacenter_names[ $location ] ) ? $datacenter_names[ $location ] : 'Unknown';
	}

	/**
	 * Get the data center name and location.
	 *
	 * @return array Data center name & location.
	 */
	public function getDataCenterNameAndLocation() {
		$location = $this->getHostLocation();
		$name     = $this->getDataCenterName( $location );

		return [
			'name'     => $name,
			'location' => $location,
		];
	}

	/**
	 * Adds feature integration information to the telemetry report.
	 *
	 * @param array[] $report The gathered report data.
	 *
	 * @return array[] The $report array.
	 */
	public function addFeatureToTelemetry( array $report ) {
		$report['features'][ self::TELEMETRY_FEATURE_KEY ] = $this->getPerformanceMonitorSetting();

		return $report;
	}
}
