<?php

namespace Nexcess\MAPPS\Integrations\PerformanceMonitor;

use Nexcess\MAPPS\Concerns\HasCronEvents;
use Nexcess\MAPPS\Integrations\PerformanceMonitor;
use Nexcess\MAPPS\Routes\PerformanceMonitorAuthRoute;
use Nexcess\MAPPS\Routes\PerformanceMonitorReportRoute;
use Nexcess\MAPPS\Routes\RestRoute;
use Nexcess\MAPPS\Services\Managers\RouteManager;
use Nexcess\MAPPS\Support\VisualRegressionUrl;

class Api {
	use HasCronEvents;

	/**
	 * Action called when all Lighthouse reports are delivered.
	 */
	const CRON_DONE_HOOK = PerformanceMonitor::DATA_PREFIX . 'reports_received';

	/**
	 * Whenever a new batch of pages to sent to the API, we keep a list
	 * of pages in WordPress options and as Lighthouse Reports come back,
	 * we store them in this option until all are available or until
	 * the request times out.
	 *
	 * @var string
	 */
	const PENDING_REQUESTS_OPTION_KEY = PerformanceMonitor::DATA_PREFIX . 'pending_requests';

	/**
	 * A site token seed is a random hexadecimal string that is used to
	 * generate short lived tokens unique to the current site.
	 *
	 * When the API endpoint calls back with the generated Lighthouse report,
	 * it sends the token back to prove it can "write" to the current site.
	 *
	 * @var string
	 */
	const SITE_TOKEN_SEED_OPTION_KEY = PerformanceMonitor::DATA_PREFIX . 'site_token';

	/**
	 * The minimal token time-to-live. The maximum time is a double of this value.
	 *
	 * @var int
	 */
	const TOKEN_TTL_IN_MINUTES = 30 * MINUTE_IN_SECONDS;

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
	 * Endpoint URL for requesting Lighthouse reports to be generated.
	 *
	 * @var string
	 */
	protected $endpoint = '';

	/**
	 * Constructor.
	 *
	 * @param PerformanceMonitor $performance_monitor `PerformanceMonitor` instance.
	 * @param RouteManager       $route_manager       `RouteManager` instance.
	 * @param string             $api_endpoint        URL of the API endpoint used to request
	 *                                                Lighthouse reports.
	 */
	public function __construct(
		PerformanceMonitor $performance_monitor,
		RouteManager $route_manager,
		$api_endpoint
	) {
		$this->performanceMonitor = $performance_monitor;
		$this->routeManager       = $route_manager;
		$this->endpoint           = $api_endpoint . '/api/v1/lighthouse/queue';

		/**
		 * This is not an integration on its own, we need to call `addHooks` explicitly.
		 */
		$this->addHooks();
	}

	/**
	 * Retrieve all actions for the integration.
	 *
	 * @return array[]
	 */
	protected function getActions() {
		return [
			[
				self::CRON_DONE_HOOK,
				[ $this, 'done' ],
				10,
			],
		];
	}

	/**
	 * Adds a list of URLs to the queue of URLs to generate Lighthouse reports for.
	 *
	 * @param Array<VisualRegressionUrl> $urls List of URLs to generate Lighthouse reports for.
	 *
	 * @throws \Exception If the API endpoint is not reachable.
	 */
	public function subscribe( array $urls ) {
		$route_urls = $this->getRouteUrls();

		// 1. Create an entry in options to track pending requests.
		$pending = [];
		foreach ( $urls as $url ) {
			$pending[ $url->getPermalink() ] = [ 'report' => '' ];
		}
		update_option( self::PENDING_REQUESTS_OPTION_KEY, $pending, false );

		// 2. Make HTTP calls to request LH reports to be generated.
		foreach ( $urls as $url ) {
			$headers     = [
				'Content-Type' => 'application/json',
			];
			$body_params = array_merge(
				[
					'url'   => $url->getPermalink(),
					'token' => $this->getCurrentToken(),
				],
				$route_urls
			);

			$response = wp_remote_post(
				$this->endpoint,
				[
					'headers' => $headers,
					'body'    => wp_json_encode( $body_params ),
				]
			);

			if ( 200 !== wp_remote_retrieve_response_code( $response ) ) {
				throw new \Exception(
					sprintf(
						'Unable to subscribe a URL: %s',
						$url->getPermalink()
					)
				);
			}
		}
	}

	/**
	 * Saves a received report in WP options until all reports are in.
	 *
	 * @param string $received_url  The URL the report was generated for.
	 * @param string $report_string The raw Lighthouse report as string.
	 */
	public function saveReport( $received_url, $report_string ) {
		$all_done = true;
		$pending  = get_option( self::PENDING_REQUESTS_OPTION_KEY, [] );

		foreach ( array_keys( $pending ) as $url ) {
			if ( $url === $received_url ) {
				$pending[ $url ] = [ 'report' => $report_string ];
			}
			if ( empty( $pending[ $url ]['report'] ) ) {
				$all_done = false;
			}
		}

		update_option( self::PENDING_REQUESTS_OPTION_KEY, $pending, false );

		if ( $all_done ) {
			/**
			 * Run the `done` handler in a separate request.
			 *
			 * Any errors related to the process of creating the performance objects in the DB
			 * should not make the current API request fail.
			 *
			 * The PubSub API engine would then retry the API request over and over again,
			 * which may lead to duplicate entries in the timeline.
			 */
			$this->registerCronEvent(
				self::CRON_DONE_HOOK,
				null,
				new \DateTime()
			)->scheduleEvents();

			/**
			 * Spawn cron right away.
			 */
			spawn_cron();
		}
	}

	/**
	 * Removes the pending requests entry and sends all Lighthouse reports
	 * that have arrived for processing.
	 */
	public function done() {
		$pending        = get_option( self::PENDING_REQUESTS_OPTION_KEY, [] );
		$report_strings = [];

		foreach ( $pending as $data ) {
			if ( ! empty( $data['report'] ) ) {
				$report_strings[] = $data['report'];
			}
		}

		if ( $report_strings ) {
			$this->performanceMonitor->processLighthouseReports( $report_strings );
		}
		delete_option( self::PENDING_REQUESTS_OPTION_KEY );
	}

	/**
	 * Returns a token unique to the site that is valid for a certain time.
	 *
	 * Uses `wp_nonce_tick` internally to retrieve an index of a timeslot
	 * since the Unix epoch. Unlike nonces, however, it doesn't take the currently
	 * logged in user into account, only the token seed string unique to each site.
	 *
	 * @param bool     $previous     Whether to generate the previously valid token.
	 * @param int|null $time_ordinal Integer representing a time interval. In normal operation
	 *                               this value is generated by `wp_nonce_tick`. Useful for tests.
	 *
	 * @return string
	 */
	public function getCurrentToken( $previous = false, $time_ordinal = null ) {
		$token_seed = get_option( self::SITE_TOKEN_SEED_OPTION_KEY, '' );

		if ( ! $token_seed ) {
			$token_seed = $this->generateTokenSeed();
		}

		if ( ! $time_ordinal ) {
			/**
			 * Set the token lifetime to a maximum of `TTL` minutes.
			 */
			$returns_token_ttl = function() {
				return self::TOKEN_TTL_IN_MINUTES;
			};

			add_filter( 'nonce_life', $returns_token_ttl, 10 );
			$time_ordinal = wp_nonce_tick();
			remove_filter( 'nonce_life', $returns_token_ttl, 10 );

			if ( $previous ) {
				$time_ordinal--;
			}
		}

		return wp_hash( sprintf( '%s:%s', $token_seed, $time_ordinal ) );
	}

	/**
	 * Returns the previously valid token.
	 *
	 * @param int|null $time_ordinal Integer representing a time interval. In normal operation
	 *                               this value is generated by `wp_nonce_tick`. Useful for tests.
	 *
	 * @return string
	 */
	public function getPreviousToken( $time_ordinal = null ) {
		return $this->getCurrentToken( true );
	}

	/**
	 * A token is valid when it matches the current token
	 * or a token from previous period.
	 *
	 * Note: WordPress nonces use the same mechanism.
	 *
	 * @param string $token Token to be verified.
	 *
	 * @return bool
	 */
	public function verifyToken( $token ) {
		$valid_tokens = [
			$this->getCurrentToken(),
			$this->getPreviousToken(),
		];

		return in_array( $token, $valid_tokens, true );
	}

	/**
	 * Generates and stores a new site token seed string.
	 *
	 * @return string Generated seed.
	 */
	protected function generateTokenSeed() {
		$random_hex_string = bin2hex( random_bytes( 16 ) );

		update_option( self::SITE_TOKEN_SEED_OPTION_KEY, $random_hex_string, false );

		return $random_hex_string;
	}

	/**
	 * API requests require a couple of endpoint URLs to be provided
	 * with each request. This methods retrieves the relevant
	 * route instances using the `RouteManager` and constructs their URLs.
	 *
	 * @throws \Exception When one of the required routes is not registered.
	 *
	 * @return Array<string> Array of URLs.
	 */
	public function getRouteUrls() {
		$all_routes          = $this->routeManager->getRoutes();
		$required_route_urls = [];

		$required_routes = [
			PerformanceMonitorAuthRoute::class,
			PerformanceMonitorReportRoute::class,
		];

		$required_route_to_callback_key_map = [
			PerformanceMonitorAuthRoute::class   => 'authCallback',
			PerformanceMonitorReportRoute::class => 'reportCallback',
		];

		/**
		 * Retrieve the necessary route URLs.
		 */
		foreach ( $all_routes as $route ) {
			if ( ! $route instanceof RestRoute ) {
				continue;
			}

			$route_classname = get_class( $route );

			if ( in_array( $route_classname, $required_routes, true ) ) {
				$key = $required_route_to_callback_key_map[ $route_classname ];

				$required_route_urls[ $key ] = get_rest_url(
					null,
					$route->getNamespace() . $route->getRoute()
				);
			}
		}

		/**
		 * Error if the resulting number of URLs doesn't match the number of
		 * the originally required routes.
		 */
		if ( count( $required_routes ) !== count( $required_route_urls ) ) {
			throw new \Exception(
				sprintf(
					'Performance Monitor couldn\'t request Lighthouse reports, because one of these required REST API endpoints is not registered: %s',
					join( ', ', array_keys( $required_routes ) )
				)
			);
		}

		return $required_route_urls;
	}
}
