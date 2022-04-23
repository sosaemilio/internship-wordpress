<?php

namespace Nexcess\MAPPS\Integrations\PerformanceMonitor\Generator;

use Nexcess\MAPPS\Integrations\PerformanceMonitor\FileSource;
use Nexcess\MAPPS\Integrations\PerformanceMonitor\LighthouseReport;
use Nexcess\MAPPS\Integrations\PerformanceMonitor\Model\Page;

/**
 * The `PageGenerator` is responsible for generating post meta for a `Report`
 * instance once all Lighthouse reports are available for a given day.
 */
class PageGenerator extends BaseGenerator {

	/**
	 * @var LighthouseReport
	 */
	protected $lighthouseReport;

	/**
	 * @var Page|null
	 */
	protected $previousPage;

	/**
	 * Page description, i.e. 'Home page'.
	 *
	 * @var string
	 */
	protected $pageDescription;

	/**
	 * Constructor.
	 *
	 * @param string           $page_description  Description of the page.
	 * @param LighthouseReport $lighthouse_report Lighthouse report object containing performance metrics
	 *                                            for this page.
	 * @param Page|null        $previous_page     The previous `Page` instance.
	 */
	public function __construct(
		$page_description,
		LighthouseReport $lighthouse_report,
		Page $previous_page = null
	) {
		$this->lighthouseReport = $lighthouse_report;
		$this->previousPage     = $previous_page;
		$this->pageDescription  = $page_description;
	}

	/**
	 * Generate a post meta array corresponding with a `Page` object.
	 *
	 * @return Array<mixed>
	 */
	public function generate() {
		$meta = [
			'url'  => $this->lighthouseReport->getUrl(),
			'name' => $this->pageDescription,
		];

		$summary     = $this->lighthouseReport->getSummary();
		$assets_data = $this->lighthouseReport->getAssetsData();
		$large_files = $this->lighthouseReport->getLargeFiles();
		$file_source = new FileSource();

		$large_files = array_map( function( $large_file ) use ( $file_source ) {
			$large_file['source'] = $file_source->getSource( $large_file['url'] );

			return $large_file;
		}, $large_files );

		if ( $this->previousPage ) {
			$previous_meta = $this->previousPage->getMeta();

			/**
			 * Fill in the `diff` values for page summary values.
			 */
			$diffed_keys = [ 'load_time', 'weight' ];
			foreach ( $diffed_keys as $key ) {
				if ( isset( $previous_meta[ $key ] ) ) {
					$summary[ $key . '_diff' ] = $summary[ $key ] - $previous_meta[ $key ];
				}
			}

			/**
			 * Fill if the `diff` values for all asset groups.
			 */
			foreach ( $assets_data as $key => $value ) {
				if ( isset( $previous_meta[ $key ] ) ) {
					$assets_data[ $key . '_diff' ] = $value - $previous_meta[ $key ];
				}
			}

			if ( isset( $previous_meta['large_files'][0]['url'] ) ) {
				$previous_files_urls = array_column( $previous_meta['large_files'], 'url' );

				foreach ( $large_files as $index => $large_file ) {
					$is_old = in_array( $large_file['url'], $previous_files_urls, true );

					$large_files[ $index ]['old'] = $is_old;
				}
			}
		}

		return array_merge(
			$meta,
			$summary,
			$assets_data,
			[ 'large_files' => $large_files ]
		);
	}
}
