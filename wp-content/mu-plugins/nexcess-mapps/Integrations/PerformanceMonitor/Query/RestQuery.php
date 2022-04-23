<?php

namespace Nexcess\MAPPS\Integrations\PerformanceMonitor\Query;

use Nexcess\MAPPS\Integrations\PerformanceMonitor\CustomPostTypes;
use Nexcess\MAPPS\Integrations\PerformanceMonitor\Model\Insight;
use Nexcess\MAPPS\Integrations\PerformanceMonitor\Model\Page;
use Nexcess\MAPPS\Integrations\PerformanceMonitor\Model\Report;
use Nexcess\MAPPS\Integrations\PerformanceMonitor\Model\SiteChange;

/**
 * The `RestQuery` class is responsible for querying the REST API.
 *
 * Querying the REST API instead of using `WP_Query` in order to make use
 * of the data we use to register all custom posts meta. As a result, we
 * are able to retrieve only the public meta fields all in one request.
 */
class RestQuery {

	/**
	 * Custom post type.
	 *
	 * @var string
	 */
	protected $postType = '';

	/**
	 * Model class associated with the custom post type.
	 *
	 * @var string
	 */
	protected $modelClass = '';

	/**
	 * Last set of response headers returned by the REST API.
	 *
	 * @var Array<mixed>
	 */
	protected $last_headers = [];

	/**
	 * Instance of a class responsible for making the actual
	 * REST API requests and retrieving results.
	 *
	 * @var InternalRestQuery
	 */
	protected $internalRestQuery;

	/**
	 * @param string $post_type Custom post type.
	 */
	public function __construct( $post_type ) {
		$this->postType          = $post_type;
		$this->modelClass        = CustomPostTypes::POST_TYPE_MODELS[ $post_type ];
		$this->internalRestQuery = new InternalRestQuery();
	}

	/**
	 * Returns REST data corresponding with a custom post specified by an ID.
	 *
	 * @param int $post_id Post ID.
	 *
	 * @return Report|SiteChange|Page|Insight|null
	 */
	public function get( $post_id ) {
		$endpoint = sprintf( '/wp/v2/%s/%s', $this->postType, $post_id );
		$data     = $this->internalRestQuery->request( $endpoint, 'GET' );

		if ( $data['id'] ) {
			/**
			 * @var Report|SiteChange|Page|Insight
			 */
			$model_instance = new $this->modelClass( (int) $data['id'] );

			return $model_instance;
		}
		return null;
	}

	/**
	 * Returns REST data corresponding with a most recent published custom post
	 * of a certain type.
	 *
	 * @return Report|SiteChange|Page|Insight|null
	 */
	public function getMostRecent() {
		$objects = $this->query( [ 'per_page' => 1 ] );

		return isset( $objects[0] ) ? $objects[0] : null;
	}

	/**
	 * Returns custom post data for all posts with a particular parent post.
	 *
	 * @param int          $parent_id    ID of the parent post.
	 * @param Array<mixed> $query_params Parameters passed to `\WP_REST_Request->set_query_params`.
	 *
	 * @return Array<Report>|Array<Page>|Array<SiteChange>|Array<Insight>
	 */
	public function getByParent( $parent_id, $query_params = [] ) {
		$query_params['parent'] = $parent_id;

		return $this->query( $query_params );
	}

	/**
	 * Returns a collection of post data for posts matching
	 * a set of custom query parameters.
	 *
	 * @param Array<mixed> $query_params Parameters passed to `\WP_REST_Request->set_query_params`.
	 *
	 * @return Array<Report>|Array<Page>|Array<SiteChange>|Array<Insight>
	 */
	public function query( $query_params ) {
		$endpoint = sprintf( '/wp/v2/%s', $this->postType );
		$data     = $this->internalRestQuery->request( $endpoint, 'GET', $query_params );

		$instances = [];
		if ( isset( $data[0]['id'] ) ) {
			foreach ( $data as $post_data ) {
				/**
				 * @var Report|SiteChange|Page|Insight
				 */
				$model_instance = new $this->modelClass( (int) $post_data['id'] );

				$instances[] = $model_instance;
			}
		}
		return $instances;
	}

	/**
	 * Returns the last set of HTTP headers returned
	 * by the REST API.
	 */
	public function getLastHeaders() {
		return $this->internalRestQuery->getLastHeaders();
	}
}
