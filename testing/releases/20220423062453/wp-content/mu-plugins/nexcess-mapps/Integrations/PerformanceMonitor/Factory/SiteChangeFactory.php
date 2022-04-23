<?php

namespace Nexcess\MAPPS\Integrations\PerformanceMonitor\Factory;

use Nexcess\MAPPS\Integrations\PerformanceMonitor\CustomPostTypes;
use Nexcess\MAPPS\Integrations\PerformanceMonitor\Model\BaseModel;
use Nexcess\MAPPS\Integrations\PerformanceMonitor\Model\SiteChange;

/**
 * Instantiates and returns a `SiteChange` model.
 */
class SiteChangeFactory extends BaseFactory {

	/**
	 * The `SiteChange` post type.
	 *
	 * @var string
	 */
	protected $postType = CustomPostTypes::POST_TYPES['site_change'];

	/**
	 * Creates a post in WordPress database containing the provided
	 * meta data and returns an `SiteChange` model instance that uses
	 * the newly created post as an underlaying data source.
	 *
	 * @param Array<mixed> $post_meta Post metadata to create the SiteChange from.
	 * @param BaseModel    $parent    A `Model` object this `SiteChange` belongs to.
	 *
	 * @return SiteChange SiteChange instance.
	 */
	public function create( array $post_meta = [], BaseModel $parent = null ) {
		$additional_post_meta = [];
		if ( $parent instanceof BaseModel ) {
			$additional_post_meta['post_parent'] = $parent->getAssociatedPostId();
		}
		$new_post_id = $this->createPost( $post_meta, $additional_post_meta );

		return new SiteChange( $new_post_id );
	}

	/**
	 * Generates a `post_name` value based on the provided
	 * post meta values.
	 *
	 * @param Array<mixed> $post_meta Post meta data associated with the post.
	 *
	 * @return string Post name string.
	 */
	protected function getPostName( array $post_meta ) {
		$object_type = empty( $post_meta['object_type'] ) ? 'unknown' : $post_meta['object_type'];
		$action      = empty( $post_meta['action'] ) ? 'unknown' : $post_meta['action'];

		return sprintf( 'site-change-%s-%s', $object_type, $action );
	}
}
