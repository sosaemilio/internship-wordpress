<?php

namespace Nexcess\MAPPS\Commands;

use Nexcess\MAPPS\Integrations\VisualComparison as Integration;

/**
 * WP-CLI sub-commands for integrating with Visual Comparison.
 */
class VisualComparison extends Command {

	/**
	 * @var \Nexcess\MAPPS\Integrations\VisualComparison
	 */
	protected $integration;

	/**
	 * @param \Nexcess\MAPPS\Integrations\VisualComparison $integration
	 */
	public function __construct( Integration $integration ) {
		$this->integration = $integration;
	}

	/**
	 * Retrieve a list of URLs to process during Visual Comparison.
	 *
	 * URLs will be returned as a flat object, with a key corresponding
	 * to the ID of the check.
	 *
	 * For example:
	 *
	 *     {
	 *       "homepage": "\/",
	 *       "single-page": "\/some-page\/"
	 *       "single-post": "\/blog\/some-post-slug\/",
	 *       "category-archive": "\/cat\/some-category\/"
	 *     }
	 *
	 * Returned URLs will be relative to the site root.
	 *
	 * ## EXAMPLES
	 *
	 *   wp nxmapps vc urls
	 *
	 * @subcommand urls
	 */
	public function getRegressionUrls() {
		$urls = [];

		foreach ( $this->integration->getUrls() as $url ) {
			$urls[ $url->getId() ] = $url->getPath();
		}

		// Filter URLs that return a response code other than 200.
		$urls = $this->filterUrls( array_unique( array_filter( $urls ) ), 200 );

		$this->line( (string) wp_json_encode( $urls, JSON_PRETTY_PRINT ) );
	}

	/**
	 * Get a list of plugins that should be eligible for visual regression testing.
	 *
	 * ## EXAMPLES
	 *
	 *   wp nxmapps vc plugins
	 *
	 * @subcommand plugins
	 */
	public function getPlugins() {
		return $this->wp( 'plugin list --status=active,active-network,inactive --format=json' );
	}

	/**
	 * Retrieve the system path to the site's upload directory.
	 *
	 * ## EXAMPLES
	 *
	 *   wp nxmapps vc upload-dir
	 *
	 * @subcommand upload-dir
	 */
	public function getUploadDir() {
		$this->line( wp_upload_dir()['basedir'] );
	}

	/**
	 * Filter URLs that do not match the response code.
	 *
	 * @param string[] $urls          URLs to filter.
	 * @param int      $response_code Response Code filter the list.
	 *
	 * @return string[] An array of URLs
	 */
	protected function filterUrls( $urls, $response_code = 200 ) {
		$urls = $this->integration->resolveTrailingSlashes( $urls );

		return array_filter( $urls, function( $url ) use ( $response_code ) {
			$response = wp_remote_head( site_url( $url ), [
				'redirection' => 0,
			] );

			// phpcs:ignore WordPress.PHP.YodaConditions.NotYoda
			return $response_code === wp_remote_retrieve_response_code( $response );
		});
	}
}
