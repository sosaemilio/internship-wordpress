<?php
/**
 * Helper class to simplify manipulation with the Lighthouse JSON data.
 */

namespace Nexcess\MAPPS\Integrations\PerformanceMonitor;

class LighthouseReport {

	/**
	 * Report data.
	 *
	 * @var \stdClass
	 */
	protected $report;

	/**
	 * Constructor.
	 *
	 * @param string $report_json_string Lighthouse report as a string.
	 */
	public function __construct( $report_json_string ) {
		$this->report = json_decode( $report_json_string );
	}

	/**
	 * Returns the summary information about page performance.
	 *
	 * Example:
	 * [
	 *   'score'     => 71,      // Lighthouse Score metric
	 *   'load_time' => 1741,    // value in miliseconds
	 *   'weight'    => 6123000, // value in bytes
	 * ]
	 *
	 * @return Array<int>
	 */
	public function getSummary() {
		$score = isset( $this->report->categories->performance->score )
			? intval( $this->report->categories->performance->score * 100 )
			: 0;

		$load_time = isset( $this->report->audits->interactive->numericValue )
			? intval( $this->report->audits->interactive->numericValue )
			: 0;

		$weight = isset( $this->report->audits->{'total-byte-weight'}->numericValue )
			? intval( $this->report->audits->{'total-byte-weight'}->numericValue )
			: 0;

		$bootup_time = isset( $this->report->audits->{'bootup-time'}->numericValue )
			? intval( $this->report->audits->{'bootup-time'}->numericValue )
			: 0;

		$lcp_time = isset( $this->report->audits->{'largest-contentful-paint'}->numericValue )
			? intval( $this->report->audits->{'largest-contentful-paint'}->numericValue )
			: 0;

		$max_fid = isset( $this->report->audits->{'max-potential-fid'}->numericValue )
			? intval( $this->report->audits->{'max-potential-fid'}->numericValue )
			: 0;

		$render_blocking_time = isset( $this->report->audits->{'render-blocking-resources'}->numericValue )
			? intval( $this->report->audits->{'render-blocking-resources'}->numericValue )
			: 0;

		$total_blocking_time = isset( $this->report->audits->{'total-blocking-time'}->numericValue )
			? intval( $this->report->audits->{'total-blocking-time'}->numericValue )
			: 0;

		return [
			'score'                => $score,
			'load_time'            => $load_time,
			'lcp_time'             => $lcp_time,
			'max_fid'              => $max_fid,
			'render_blocking_time' => $render_blocking_time,
			'total_blocking_time'  => $total_blocking_time,
			'bootup_time'          => $bootup_time,
			'weight'               => $weight,
		];
	}

	/**
	 * Returns the URL of the page tested.
	 *
	 * @return string
	 */
	public function getUrl() {
		$final_url     = $this->getValue( 'finalUrl' );
		$requested_url = $this->getValue( 'requestedUrl', '' );

		return $final_url ?: $requested_url;
	}

	/**
	 * Returns the requested URL of the page tested.
	 *
	 * @return string
	 */
	public function getRequestedUrl() {
		return $this->getValue( 'requestedUrl', '' );
	}

	/**
	 * Returns information about the total weight and number of files
	 * per every asset group (i.e. stylesheets, scripts, ...).
	 *
	 * @return Array<int>
	 */
	public function getAssetsData() {
		$assets_data  = [];
		$asset_groups = [ 'script', 'stylesheet', 'image', 'media', 'third-party' ];

		/**
		 * Add main document data.
		 */
		$page_url            = $this->getUrl();
		$network_requests_lh = $this->getValue( 'audits/network-requests/details/items', [] );

		foreach ( $network_requests_lh as $request ) {
			if ( $request->url === $page_url ) {
				$weight = isset( $request->transferSize ) ? intval( $request->transferSize ) : 0;

				$assets_data = [
					'weight_document' => $weight,
				];
			}
		}

		/**
		 * Add individual types of assets to the summary.
		 */
		$summary_lh             = $this->getValue( 'audits/resource-summary/details/items', [] );
		$asset_groups_w_summary = [];

		foreach ( $summary_lh as $summary_lh_item ) {
			if ( in_array( $summary_lh_item->resourceType, $asset_groups, true ) ) {
				$asset_groups_w_summary[ $summary_lh_item->resourceType ] = $summary_lh_item;
			}
		}

		foreach ( $asset_groups_w_summary as $asset_group => $summary_lh_item ) {
			$weight_key       = sprintf( 'weight_%s', $asset_group );
			$number_files_key = sprintf( 'number_files_%s', $asset_group );

			$assets_data[ $weight_key ]       = $summary_lh_item->transferSize;
			$assets_data[ $number_files_key ] = $summary_lh_item->requestCount;
		}

		return $assets_data;
	}

	/**
	 * Returns a list of large as identified in the report.
	 *
	 * @return Array<Array>
	 */
	public function getLargeFiles() {
		$large_files         = [];
		$large_files_lh      = $this->getValue( 'audits/total-byte-weight/details/items', [] );
		$network_requests_lh = $this->getValue( 'audits/network-requests/details/items', [] );

		foreach ( $large_files_lh as $file ) {
			$large_file_item = [
				'url'    => $file->url,
				'weight' => $file->totalBytes,
			];

			// Extract filename from the whole URL.
			$url_path = wp_parse_url( $file->url, PHP_URL_PATH );
			if ( $url_path ) {
				$large_file_item['filename'] = basename( $url_path );
			}

			// Extract resource types.
			foreach ( $network_requests_lh as $network_request ) {
				if ( $network_request->url === $file->url ) {
					$type = isset( $network_request->resourceType ) ? $network_request->resourceType : null;

					if ( $type ) {
						$large_file_item['type'] = $type;
					}
				}
			}

			$large_files[] = $large_file_item;
		}

		return $large_files;
	}

	/**
	 * Returns a single value from the report if the
	 * value is defined.
	 *
	 * @param string $key_path A `/` delimited path to the value in JSON object.
	 * @param mixed  $default  Default value to be returned if requested value is not set.
	 */
	public function getValue( $key_path, $default = null ) {
		$key_array = preg_split( '~/~', $key_path );

		if ( ! is_array( $key_array ) ) {
			return $default;
		}

		$value = $this->report;
		foreach ( $key_array as $key ) {
			if ( ! isset( $value->{$key} ) ) {
				return $default;
			}
			$value = $value->{$key};
		}

		return $value;
	}
}
