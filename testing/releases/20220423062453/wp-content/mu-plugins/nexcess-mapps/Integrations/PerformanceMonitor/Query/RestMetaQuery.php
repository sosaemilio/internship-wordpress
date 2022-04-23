<?php

namespace Nexcess\MAPPS\Integrations\PerformanceMonitor\Query;

use Nexcess\MAPPS\Integrations\PerformanceMonitor\Meta;

/**
 * The `RestMetaQuery` class is responsible for retrieving post meta
 * data via the REST API. Because Performance Manager registers its
 * post meta, only the public registered meta items are returned.
 */
class RestMetaQuery {

	/**
	 * Custom post type.
	 *
	 * @var string
	 */
	protected $postType = '';

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
		$this->internalRestQuery = new InternalRestQuery();
	}

	/**
	 * Returns the public metadata from the REST API
	 * post object.
	 *
	 * @param int $post_id Post ID.
	 *
	 * @return Array<mixed> Public post meta array.
	 */
	public function getMeta( $post_id ) {
		$endpoint    = sprintf( '/wp/v2/%s/%s', $this->postType, $post_id );
		$post_data   = $this->internalRestQuery->request( $endpoint, 'GET' );
		$meta_exists = isset( $post_data['meta'] ) && is_array( $post_data['meta'] );
		$meta        = $meta_exists ? $post_data['meta'] : [];

		return Meta::unprefix_meta_array( $meta );
	}
}
