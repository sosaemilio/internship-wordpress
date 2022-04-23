<?php

namespace Nexcess\MAPPS\Integrations\PerformanceMonitor\Factory;

use Nexcess\MAPPS\Exceptions\ValidationException;
use Nexcess\MAPPS\Integrations\PerformanceMonitor\CustomPostTypes;
use Nexcess\MAPPS\Integrations\PerformanceMonitor\Meta;
use Nexcess\MAPPS\Integrations\PerformanceMonitor\Model\BaseModel;

/**
 * The `BaseFactory` implements and prescribes the basic methods
 * individual factories must implement.
 *
 * A factory is responsible for instantiating a `Model` and for
 * making sure the model instance has an associated post stored
 * in the WordPress database.
 */
abstract class BaseFactory {

	/**
	 * The custom post type name associated with the models
	 * this factory produces.
	 *
	 * @var string
	 */
	protected $postType;

	/**
	 * Error code used in WP_Error instances emitted by validation methods.
	 */
	const VALIDATION_ERROR_CODE = 'performance_monitor_validation';

	/**
	 * Creates a new post in the database that is associated
	 * with a model instance.
	 *
	 * @param Array<mixed> $post_meta              Data to be stored as post meta.
	 * @param Array<mixed> $additional_post_fields Additional post fields to be altered when post is created.
	 *
	 * @throws \Exception                                    When a new post can't be created or the supplied post meta is invalid.
	 * @throws \Exception                                    When a new post can't be created or the supplied post meta is invalid.
	 * @throws \Nexcess\MAPPS\Exceptions\ValidationException When the supplied post meta is invalid.
	 *
	 * @return int Post ID.
	 */
	protected function createPost( array $post_meta, array $additional_post_fields = [] ) {
		$validation_result = $this->validate( $post_meta );
		if ( is_wp_error( $validation_result ) ) {
			throw new ValidationException(
				sprintf(
					/* Translators: %1$d is a post type. */
					__( 'Performance Monitor tried to create a new `%1$s` post, but the supplied post metadata is invalid. The error mesage was: "%2$s"', 'nexcess-mapps' ),
					$this->postType,
					$validation_result->get_error_message()
				)
			);
		}

		$post_fields = [
			'post_status' => 'publish',
			'post_type'   => $this->postType,
			'post_name'   => $this->getPostName( $post_meta ),
			'meta_input'  => Meta::prefix_meta_array( $post_meta ),
		];
		$post_fields = array_merge( $post_fields, $additional_post_fields );

		$created_post = wp_insert_post( $post_fields, true );

		if ( is_wp_error( $created_post ) ) {
			throw new \Exception(
				sprintf(
					/* Translators: %1$d is the post ID, %1$s is the error message. */
					__( 'Performance Monitor tried to create a new post, but failed. The error message was: "%1$s"', 'nexcess-mapps' ),
					$created_post->get_error_message()
				)
			);
		}

		return $created_post;
	}

	/**
	 * Post meta array validation. Make sure the shape of the provided post meta
	 * fields corresponds with what we expect and that all required fields are set.
	 *
	 * Individual factories may override this method and impose additional rules.
	 *
	 * @param Array<mixed> $post_meta Post meta to be validated.
	 *
	 * @return true|\WP_Error Validation result.
	 */
	protected function validate( array $post_meta ) {
		$all_meta_definitions = CustomPostTypes::getMetaFieldsDefinitions();
		$meta_definitions     = $all_meta_definitions[ $this->postType ];

		/**
		 * The meta fields definition already contains all the information
		 * we need to build out a schema that we could directly feed to
		 * `rest_validate_value_from_schema`.
		 */
		$schema = array_map( function( $item ) {
			return $item['show_in_rest']['schema'];
		}, $meta_definitions );

		$context_identifier = sprintf( 'the custom post type `%s` post meta', $this->postType );

		$is_valid = rest_validate_value_from_schema(
			$post_meta,
			[
				'type'       => 'object',
				'properties' => $schema,
			],
			$context_identifier
		);

		if ( is_wp_error( $is_valid ) ) {
			return $is_valid;
		}
		return true;
	}

	/**
	 * Implement: a method that creates the appropriate Model instance.
	 *
	 * @param Array<mixed> $post_meta Post meta data associated with the post.
	 * @param BaseModel    $parent    An object the new `Model` should belong to.
	 *
	 * @return BaseModel
	 */
	abstract public function create( array $post_meta = [], BaseModel $parent = null );

	/**
	 * Implement: Generator for `post_name` values.
	 *
	 * @param Array<mixed> $post_meta Post meta data associated with the post.
	 *
	 * @return string Post name string.
	 */
	abstract protected function getPostName( array $post_meta );
}
