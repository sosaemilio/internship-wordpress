<?php

namespace Nexcess\MAPPS\Integrations\PerformanceMonitor\Model;

use Nexcess\MAPPS\Integrations\PerformanceMonitor\Meta;
use Nexcess\MAPPS\Integrations\PerformanceMonitor\Query\RestMetaQuery;

/**
 * The `BaseModel` class descibes the basic contract other models
 * need to adhere to. Each model is associated with a single custom
 * post in the WordPress database.
 */
abstract class BaseModel {

	/**
	 * Associated post in the WordPress database.
	 *
	 * @var \WP_Post
	 */
	protected $associatedPost;

	/**
	 * Custom data stored with the post as post meta.
	 *
	 * @var Array<mixed>
	 */
	protected $postMeta = [];

	/**
	 * Constructor.
	 *
	 * @param int $post_id The ID of the post to associate with this model.
	 *
	 * @throws \Exception When the associated post can't be retrieved from the DB.
	 */
	public function __construct( $post_id ) {
		$requested_post = get_post( $post_id );

		if ( ! $requested_post instanceof \WP_Post ) {
			throw new \Exception(
				sprintf(
					/* Translators: %d is the post ID. */
					__( 'Performance Monitor tried to load a post with ID: %d, but the post doesn\'t exist.', 'nexcess-mapps' ),
					$post_id
				)
			);
		}

		$rest_query           = new RestMetaQuery( $requested_post->post_type );
		$this->postMeta       = $rest_query->getMeta( $post_id );
		$this->associatedPost = $requested_post;
	}

	/**
	 * Updates the associated post in the database to be in sync
	 * with the current state of the model instance.
	 *
	 * @throws \Exception When the associated post can't be updated in the DB.
	 *
	 * @return bool Returns true when the operation succeeds.
	 */
	public function save() {
		$post_array                      = $this->associatedPost->to_array();
		$post_array['post_modified']     = current_time( 'mysql' );
		$post_array['post_modified_gmt'] = current_time( 'mysql', 1 );
		$post_array['meta_input']        = Meta::prefix_meta_array( $this->postMeta );

		$updated_post = wp_insert_post( $post_array, true );

		if ( $updated_post instanceof \WP_Error ) {
			throw new \Exception(
				sprintf(
					/* Translators: %1$d is the post ID, %2$s is the error message. */
					__( 'Performance Monitor tried to save a post with ID: %1$d, but failed. The error message was: %2$s', 'nexcess-mapps' ),
					$updated_post->get_error_message()
				)
			);
		}
		return true;
	}

	/**
	 * Deletes the associated post from the database and usets the associated post.
	 *
	 * @return \WP_Post|false|null Post data on success, false or null on failure.
	 */
	public function delete() {
		$deleted_post = wp_delete_post( $this->associatedPost->ID, true );
		if ( $deleted_post ) {
			unset( $this->associatedPost );
		}
		return $deleted_post;
	}

	/**
	 * Returns all meta values when `$key` is not provided, otherwise a single meta value.
	 *
	 * Returns `$default_value` when `$key` is provided, but the meta value does not exist.
	 *
	 * @param string $key           Meta key.
	 * @param mixed  $default_value Default value returned when no meta with `$key` exists.
	 *
	 * @return mixed Meta value.
	 */
	public function getMeta( $key = '', $default_value = null ) {
		if ( $key ) {
			if ( isset( $this->postMeta[ $key ] ) ) {
				return $this->postMeta[ $key ];
			}
			return $default_value;
		}
		return $this->postMeta;
	}

	/**
	 * Sets all post meta when `$key` is not provided, or a single
	 * meta value when `$key` is provided.
	 *
	 * @param string $key   Meta key.
	 * @param mixed  $value Meta value.
	 */
	public function setMeta( $key = '', $value = null ) {
		if ( $key ) {
			$this->postMeta[ $key ] = $value;
		} else {
			if ( ! is_array( $value ) ) {
				$value = [];
			}
			$this->postMeta = $value;
		}
	}

	/**
	 * Returns the ID of the post in the database that is associated
	 * to this `Model` instance.
	 *
	 * @return int Associated post ID.
	 */
	public function getAssociatedPostId() {
		return $this->associatedPost->ID;
	}

	/**
	 * Returns the associated post date.
	 *
	 * @return string
	 */
	public function getDate() {
		return $this->associatedPost->post_date;
	}

	/**
	 * Returns the number of midnights that passed since the post was created.
	 *
	 * @return int|false Number of midnights that passed since the post was created.
	 */
	public function createdDaysAgo() {
		$current_date = new \DateTime( 'now', wp_timezone() );
		$current_date = $current_date->format( 'Y-m-d' );
		$post_date    = new \DateTime( $this->getDate(), wp_timezone() );
		$post_date    = $post_date->format( 'Y-m-d' );

		$current_datetime = new \DateTime( $current_date );
		$post_datetime    = new \DateTime( $post_date );

		return $current_datetime->diff( $post_datetime )->days;
	}
}
