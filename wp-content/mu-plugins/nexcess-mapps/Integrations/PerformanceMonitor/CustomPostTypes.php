<?php

namespace Nexcess\MAPPS\Integrations\PerformanceMonitor;

use Nexcess\MAPPS\Integrations\PerformanceMonitor;
use Nexcess\MAPPS\Integrations\PerformanceMonitor\Model\Insight;
use Nexcess\MAPPS\Integrations\PerformanceMonitor\Model\Page;
use Nexcess\MAPPS\Integrations\PerformanceMonitor\Model\Report;
use Nexcess\MAPPS\Integrations\PerformanceMonitor\Model\SiteChange;

/**
 * The `CustomPostTypes` class is responsible for defining custom
 * post types and registering post meta to be used with
 * the `PerformanceMonitor` integration.
 */
class CustomPostTypes {
	const POST_TYPES = [
		'report'      => PerformanceMonitor::DATA_PREFIX . 'report',
		'page'        => PerformanceMonitor::DATA_PREFIX . 'page',
		'site_change' => PerformanceMonitor::DATA_PREFIX . 'site_change',
		'insight'     => PerformanceMonitor::DATA_PREFIX . 'insight',
	];

	/**
	 * @var Array<class-string>
	 */
	const POST_TYPE_MODELS = [
		PerformanceMonitor::DATA_PREFIX . 'report'      => Report::class,
		PerformanceMonitor::DATA_PREFIX . 'page'        => Page::class,
		PerformanceMonitor::DATA_PREFIX . 'site_change' => SiteChange::class,
		PerformanceMonitor::DATA_PREFIX . 'insight'     => Insight::class,
	];

	/**
	 * Register post types that serve as a data layer for Performance Monitor.
	 */
	public static function registerPostTypes() {
		$meta_definitions       = self::getMetaFieldsDefinitions();
		$default_post_type_args = [
			'show_in_rest' => true,
			'hierarchical' => true, // Necessary requirement for `parent` REST queries to work properly.
			'supports'     => [ 'custom-fields' ],
		];

		foreach ( $meta_definitions as $post_type_name => $meta_fields ) {
			register_post_type(
				$post_type_name,
				$default_post_type_args
			);

			foreach ( $meta_fields as $meta_key => $meta_definition ) {
				$prefixed_meta_key = PerformanceMonitor::DATA_PREFIX . $meta_key;

				register_post_meta( $post_type_name, $prefixed_meta_key, $meta_definition );
			}
		}
	}

	/**
	 * Returns predefined sets of arguments passed to
	 * `register_post_meta` that correspond with meta field
	 * types used by the Performance Monitor integration.
	 *
	 * @param string       $type      Requested meta type definition.
	 * @param Array<mixed> $meta_args Arguments to be passed to `register_meta`.
	 *
	 * @throws \Exception When unknown `$returned_type` is provided.
	 *
	 * @return Array<mixed>
	 */
	protected static function getMetaTypeDefinition( $type, $meta_args = [] ) {
		$default_meta_args = [
			'single'       => true,
			'show_in_rest' => true,
		];

		$meta_types = [
			'integer' => array_merge(
				$default_meta_args,
				[
					'type'         => 'integer',
					'show_in_rest' => [
						'schema' => [
							'type' => 'integer',
						],
					],
				],
				$meta_args
			),

			'string'  => array_merge(
				$default_meta_args,
				[
					'type'         => 'string',
					'show_in_rest' => [
						'schema' => [
							'type' => 'string',
						],
					],
				],
				$meta_args
			),

			'object'  => array_merge(
				$default_meta_args,
				[
					'type' => 'object',
				],
				$meta_args
			),
		];

		if ( ! isset( $meta_types[ $type ] ) ) {
			throw new \Exception(
				sprintf(
					/* Translators: %1$d is the post meta type, e.g. integer or string. */
					__( 'Performance Monitor tried to register a post meta with type `%1$d`, but failed. The definition is missing.', 'nexcess-mapps' ),
					$type
				)
			);
		}

		$definition  = $meta_types[ $type ];
		$is_required = isset( $meta_args['required'] ) && $meta_args['required'];

		if ( $is_required && isset( $definition['show_in_rest']['schema'] ) ) {
			$definition['show_in_rest']['schema']['required'] = true;
		}

		return $definition;
	}

	/**
	 * Returns a list of custom post types to be registered using
	 * `register_post_type` along with schema for all the meta fields
	 * registered for each of the custom post types.
	 *
	 * The schema is used twice:
	 *
	 * 1. As a parameter for `register_post_meta`.
	 * 2. For custom validation when we create a post type from data.
	 *    See `BaseFactory::validate` for more context on this type of usage.
	 *
	 * @return array[]
	 */
	public static function getMetaFieldsDefinitions() {
		$optional_integer_field = self::getMetaTypeDefinition( 'integer' );
		$optional_string_field  = self::getMetaTypeDefinition( 'string' );
		$required_integer_field = self::getMetaTypeDefinition( 'integer', [ 'required' => true ] );
		$required_string_field  = self::getMetaTypeDefinition( 'string', [ 'required' => true ] );

		return [
			self::POST_TYPES['report']      => [
				// Average Lighthouse score across all monitored pages.
				'average_score'      => $required_integer_field,

				// Lighthouse score change between today and yesterday across all monitored pages.
				'average_score_diff' => $required_integer_field,

				// Number of site changes from yesterday.
				'changes'            => $required_integer_field,

				// Number of insights generated today.
				'insights'           => $required_integer_field,

				// Details around the state of WordPress core, themes and plugins today.
				'wp_environment'     => self::getMetaTypeDefinition( 'object', [
					'type'              => 'object',
					'sanitize_callback' => null,
					'show_in_rest'      => [
						'schema' => [
							'required'   => true,
							'type'       => 'object',
							'properties' => [
								'core_version'   => [
									'type'     => 'string',
									'required' => true,
								],
								'parent_theme'   => [
									'type'       => 'object',
									'properties' => [
										'name'    => [
											'type'     => 'string',
											'required' => true,
										],
										'version' => [
											'type'     => 'string',
											'required' => true,
										],
									],
								],
								'active_theme'   => [
									'type'       => 'object',
									'required'   => true,
									'properties' => [
										'name'    => [
											'type'     => 'string',
											'required' => true,
										],
										'version' => [
											'type'     => 'string',
											'required' => true,
										],
									],
								],
								'active_plugins' => [
									'type'     => 'array',
									'required' => true,
									'items   ' => [
										'type'       => 'object',
										'properties' => [
											'name'    => [
												'type'     => 'string',
												'required' => true,
											],
											'version' => [
												'type'     => 'string',
												'required' => true,
											],
										],
									],
								],
							], // Ends `properties`.
						], // Ends `schema`.
					], // Ends `show_in_rest`.
				] ), // Ends `wp_environment`.
			],
			self::POST_TYPES['page']        => [
				'name'                          => $required_string_field,
				'url'                           => $required_string_field,
				'score'                         => $required_integer_field,
				'load_time'                     => $required_integer_field,
				'load_time_diff'                => $optional_integer_field,
				'bootup_time'                   => $optional_integer_field,
				'lcp_time'                      => $optional_integer_field,
				'max_fid'                       => $optional_integer_field,
				'render_blocking_time'          => $optional_integer_field,
				'total_blocking_time'           => $optional_integer_field,
				'weight'                        => $required_integer_field,
				'weight_diff'                   => $optional_integer_field,
				'weight_document'               => $optional_integer_field,
				'weight_document_diff'          => $optional_integer_field,
				'weight_script'                 => $optional_integer_field,
				'weight_script_diff'            => $optional_integer_field,
				'number_files_script'           => $optional_integer_field,
				'number_files_script_diff'      => $optional_integer_field,
				'weight_stylesheet'             => $optional_integer_field,
				'weight_stylesheet_diff'        => $optional_integer_field,
				'number_files_stylesheet'       => $optional_integer_field,
				'number_files_stylesheet_diff'  => $optional_integer_field,
				'weight_image'                  => $optional_integer_field,
				'weight_image_diff'             => $optional_integer_field,
				'number_files_image'            => $optional_integer_field,
				'number_files_image_diff'       => $optional_integer_field,
				'weight_media'                  => $optional_integer_field,
				'weight_media_diff'             => $optional_integer_field,
				'number_files_media'            => $optional_integer_field,
				'number_files_media_diff'       => $optional_integer_field,
				'weight_third-party'            => $optional_integer_field,
				'weight_third-party_diff'       => $optional_integer_field,
				'number_files_third-party'      => $optional_integer_field,
				'number_files_third-party_diff' => $optional_integer_field,
				'large_files'                   => self::getMetaTypeDefinition( 'object', [
					'type'              => 'array',
					'sanitize_callback' => null,
					'show_in_rest'      => [
						'schema' => [
							'type'  => 'array',
							'items' => [
								'type'       => 'object',
								'properties' => [
									'type'     => [
										'type' => 'string',
									],
									'url'      => [
										'type' => 'string',
									],
									'filename' => [
										'type' => 'string',
									],
									'weight'   => [
										'type' => 'integer',
									],
									'old'      => [
										'type' => 'boolean',
									],
									'source'   => [
										'type'       => 'object',
										'properties' => [
											'type' => [
												'type' => 'string',
											],
											'name' => [
												'type' => 'string',
											],
											'date' => [
												'type' => 'string',
											],
										],
									],
								], // Ends `properties`.
							], // Ends `schema`.
						], // Ends `show_in_rest`.
					], // Ends `large_files`.
				] ),
			],
			self::POST_TYPES['site_change'] => [
				'action'                        => $required_string_field,
				'object_type'                   => $required_string_field,
				'object_name'                   => $optional_string_field,
				'object_version'                => $optional_string_field,
				'object_version_major'          => $optional_integer_field,
				'object_version_minor'          => $optional_integer_field,
				'object_version_patch'          => $optional_integer_field,
				'previous_object_type'          => $optional_string_field,
				'previous_object_name'          => $optional_string_field,
				'previous_object_version'       => $optional_string_field,
				'previous_object_version_major' => $optional_integer_field,
				'previous_object_version_minor' => $optional_integer_field,
				'previous_object_version_patch' => $optional_integer_field,
			],
			self::POST_TYPES['insight']     => [
				'type'      => $required_string_field,
				'variables' => self::getMetaTypeDefinition( 'object', [
					'type'              => 'array',
					'sanitize_callback' => null,
					'show_in_rest'      => [
						'schema' => [
							'type'  => 'array',
							'items' => [
								'type'       => 'object',
								'properties' => [
									'variable' => [
										'type' => 'string',
									],
									'value'    => [
										'type' => 'string',
									],
								],
							],
						],
					],
				] ),
				'sources'   => self::getMetaTypeDefinition( 'object', [
					'type'              => 'array',
					'sanitize_callback' => null,
					'show_in_rest'      => [
						'schema' => [
							'type'  => 'array',
							'items' => [
								'type'       => 'object',
								'properties' => [
									'type' => [
										'type' => 'string',
									],
									'name' => [
										'type' => 'string',
									],
									'date' => [
										'type' => 'string',
									],
								],
							],
						],
					],
				] ),
			],
		];
	}
}
