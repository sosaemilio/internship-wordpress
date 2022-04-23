<?php

namespace Nexcess\MAPPS\Integrations\PerformanceMonitor\Generator;

use Nexcess\MAPPS\Integrations\PerformanceMonitor\Model\Report;

/**
 * The `SiteChangeGenerator` is responsible for generating post meta sets
 * used to create `SiteChange` objects.
 *
 * It accepts two latest `Report` instance to infer what has changed
 * between those two points in time.
 */
class SiteChangeGenerator extends BaseGenerator {

	/**
	 * A snapshot of the WordPress environment at the time when
	 * the `$previous_report` was generated.
	 *
	 * This includes information about the WP Core version,
	 * active plugins and themes.
	 *
	 * @var Array<mixed>
	 */
	protected $previousEnv;

	/**
	 * A snapshot of the WordPress environment at the time when
	 * the `$current_report` was generated.
	 *
	 * This includes information about the WP Core version,
	 * active plugins and themes.
	 *
	 * @var Array<mixed>
	 */
	protected $currentEnv;

	/**
	 * Constructor.
	 *
	 * @param Report $previous_report Next to last generated report.
	 * @param Report $current_report  Last generated report.
	 */
	public function __construct(
		Report $previous_report,
		Report $current_report
	) {
		$this->previousEnv = $previous_report->getMeta( 'wp_environment' );
		$this->currentEnv  = $current_report->getMeta( 'wp_environment' );
	}

	/**
	 * Uses the context provided by the two `Report` objects used to
	 * initialize the instance to determine a set of changes that happened
	 * on a site between those two points in time.
	 *
	 * @return Array<mixed> Array of SiteChange post meta sets.
	 */
	public function generate() {
		$site_changes_meta = array_merge(
			$this->getCoreUpdates(),
			$this->getPluginActivations(),
			$this->getPluginUpdates(),
			$this->getThemeChanges(),
			$this->getThemeUpdates()
		);

		return $site_changes_meta;
	}

	/**
	 * Returns site changes metadata associated with plugin activations.
	 *
	 * @return Array<mixed> Site change metadata describing plugin activation events.
	 */
	protected function getPluginActivations() {
		$current_plugin_names  = array_column( $this->currentEnv['active_plugins'], 'name' );
		$previous_plugin_names = array_column( $this->previousEnv['active_plugins'], 'name' );
		$common_plugin_names   = array_intersect( $current_plugin_names, $previous_plugin_names );

		$activated_plugins = (array) array_filter(
			$this->currentEnv['active_plugins'],
			function( $plugin ) use ( $common_plugin_names ) {
				return ! in_array( $plugin['name'], $common_plugin_names, true );
			}
		);

		$deactivated_plugins = (array) array_filter(
			$this->previousEnv['active_plugins'],
			function( $plugin ) use ( $common_plugin_names ) {
				return ! in_array( $plugin['name'], $common_plugin_names, true );
			}
		);

		/**
		 * Generates the meta data necessary to create a new `SiteChange` object
		 * describing the change applied to this plugin.
		 *
		 * @param Array<mixed> $plugin Plugin name and version.
		 * @param string       $action Action that happened with the plugin.
		 *
		 * @return Array<mixed>
		 */
		$change_meta_generator = function( $plugin, $action ) {
			$meta = [
				'action'         => $action,
				'object_type'    => 'plugin',
				'object_name'    => $plugin['name'],
				'object_version' => $plugin['version'],
			];

			return $this->fillDetailedMetaInfo( $meta );
		};

		$site_change_meta_items = [];

		foreach ( $activated_plugins as $plugin ) {
			$site_change_meta_items[] = call_user_func( $change_meta_generator, $plugin, 'activate' );
		}
		foreach ( $deactivated_plugins as $plugin ) {
			$site_change_meta_items[] = call_user_func( $change_meta_generator, $plugin, 'deactivate' );
		}

		return array_filter( $site_change_meta_items );
	}

	/**
	 * Returns site changes metadata associated with plugin updates.
	 *
	 * @return Array<mixed> Site change metadata describing plugin updates.
	 */
	protected function getPluginUpdates() {
		$site_change_meta_items = [];

		foreach ( $this->currentEnv['active_plugins'] as $current_plugin ) {
			foreach ( $this->previousEnv['active_plugins'] as $previous_plugin ) {
				$same_plugin  = $current_plugin['name'] === $previous_plugin['name'];
				$same_version = $current_plugin['version'] === $previous_plugin['version'];

				if ( $same_plugin && ! $same_version ) {
					$site_change_meta_items[] = $this->getObjectUpdateMeta(
						'plugin',
						$previous_plugin,
						$current_plugin
					);

					continue 2;
				}
			}
		}

		return array_filter( $site_change_meta_items );
	}

	/**
	 * Returns site changes metadata associated with theme changes.
	 *
	 * @return Array<mixed> Site change metadata describing theme changes.
	 */
	protected function getThemeChanges() {
		$previous_theme = $this->previousEnv['active_theme'];
		$current_theme  = $this->currentEnv['active_theme'];

		if ( $previous_theme['name'] === $current_theme['name'] ) {
			return [];
		}

		$meta = [
			'action'                  => 'change',
			'object_type'             => 'theme',
			'object_name'             => $current_theme['name'],
			'object_version'          => $current_theme['version'],
			'previous_object_type'    => 'theme',
			'previous_object_name'    => $previous_theme['name'],
			'previous_object_version' => $previous_theme['version'],
		];

		$site_change_meta_items = [
			$this->fillDetailedMetaInfo( $meta ),
		];

		return array_filter( $site_change_meta_items );
	}

	/**
	 * Returns site changes metadata associated with theme updates.
	 *
	 * @return Array<mixed> Site changes metadata describing theme updates.
	 */
	protected function getThemeUpdates() {
		$site_change_meta_items = [];

		$had_parent_theme = isset( $this->previousEnv['parent_theme'] );
		$has_parent_theme = isset( $this->currentEnv['parent_theme'] );

		$active_theme_object_type = 'theme';

		if ( $had_parent_theme && $has_parent_theme ) {
			$active_theme_object_type = 'child_theme';

			$site_change_meta_items[] = $this->getObjectUpdateMeta(
				'parent_theme',
				$this->previousEnv['parent_theme'],
				$this->currentEnv['parent_theme']
			);
		}

		$site_change_meta_items[] = $this->getObjectUpdateMeta(
			$active_theme_object_type,
			$this->previousEnv['active_theme'],
			$this->currentEnv['active_theme']
		);

		return array_filter( $site_change_meta_items );
	}

	/**
	 * Returns site changes metadata associated with any core
	 * updates that might have happened.
	 *
	 * @return Array<mixed> Site change metadata describing WordPress core updates.
	 */
	protected function getCoreUpdates() {
		$site_change_meta_items = [];
		$prev_core_version      = $this->previousEnv['core_version'];
		$current_core_version   = $this->currentEnv['core_version'];

		$site_change_meta_items[] = $this->getObjectUpdateMeta(
			'core',
			[
				'name'    => 'WordPress Core',
				'version' => $prev_core_version,
			],
			[
				'name'    => 'WordPress Core',
				'version' => $current_core_version,
			]
		);

		return array_filter( $site_change_meta_items );
	}

	/**
	 * Fills in detailed version information to a meta array.
	 *
	 * Takes the `object_version` and `previous_object_version` properties
	 * and parses them into individual components: integers corresponding with
	 * the major, minor and patch versions of the version string.
	 *
	 * Then saves those component numbers as individual meta items.
	 *
	 * @param Array<mixed> $meta Meta information.
	 *
	 * @return Array<mixed> Meta information with detailed version information.
	 */
	private function fillDetailedMetaInfo( array $meta ) {
		if ( isset( $meta['object_version'] ) ) {
			if ( is_string( $meta['object_version'] ) ) {
				$version_components = $this->parseVersionString( $meta['object_version'] );
				if ( $version_components ) {
					$meta['object_version_major'] = intval( $version_components[0] );
					$meta['object_version_minor'] = intval( $version_components[1] );
					$meta['object_version_patch'] = intval( $version_components[2] );
				}
			}
		}

		if ( isset( $meta['previous_object_version'] ) ) {
			if ( is_string( $meta['previous_object_version'] ) ) {
				$version_components = $this->parseVersionString( $meta['previous_object_version'] );
				if ( $version_components ) {
					$meta['previous_object_version_major'] = intval( $version_components[0] );
					$meta['previous_object_version_minor'] = intval( $version_components[1] );
					$meta['previous_object_version_patch'] = intval( $version_components[2] );
				}
			}
		}

		return $meta;
	}

	/**
	 * Returns a set of meta values describing a site change
	 * corresponding to any object (core, theme, plugin) update
	 * or downgrade.
	 *
	 * @param string       $object_type     Type of object, e.g. `core`, `plugin`, ...
	 * @param Array<mixed> $previous_object Previous object name and version.
	 * @param Array<mixed> $current_object  Current object name and version.
	 *
	 * @return Array<mixed> Generated meta item.
	 */
	private function getObjectUpdateMeta( $object_type, array $previous_object, array $current_object ) {
		$same_version   = $previous_object['version'] === $current_object['version'];
		$different_name = $previous_object['name'] !== $current_object['name'];

		if ( $different_name || $same_version ) {
			return [];
		}

		$action = version_compare(
			$current_object['version'],
			$previous_object['version'],
			'>'
		) ? 'update' : 'downgrade';

		$meta = [
			'action'                  => $action,
			'object_type'             => $object_type,
			'object_name'             => $current_object['name'],
			'object_version'          => $current_object['version'],
			'previous_object_type'    => $object_type,
			'previous_object_name'    => $current_object['name'],
			'previous_object_version' => $previous_object['version'],
		];
		$meta = $this->fillDetailedMetaInfo( $meta );

		return $meta;
	}

	/**
	 * Returns the description of all the different site change types.
	 *
	 * @return Array<Array>
	 */
	public static function getSiteChangeTypes() {
		return [
			[
				'type'     => 'plugin_update',
				'title'    => __( 'Updated plugin', 'nexcess-mapps' ),
				'template' => __( '<%- object_name %> <%- previous_object_version %> to <%- object_version %>', 'nexcess-mapps' ),
				'icon'     => 'plug',
			],
			[
				'type'     => 'plugin_downgrade',
				'title'    => __( 'Downgraded plugin', 'nexcess-mapps' ),
				'template' => __( '<%- object_name %> <%- previous_object_version %> to <%- object_version %>', 'nexcess-mapps' ),
				'icon'     => 'plug',
			],
			[
				'type'     => 'plugin_activate',
				'title'    => __( 'Activated plugin', 'nexcess-mapps' ),
				'template' => __( '<%- object_name %> <%- object_version %>', 'nexcess-mapps' ),
				'icon'     => 'plug',
			],
			[
				'type'     => 'plugin_deactivate',
				'title'    => __( 'Deactivated plugin', 'nexcess-mapps' ),
				'template' => __( '<%- object_name %> <%- object_version %>', 'nexcess-mapps' ),
				'icon'     => 'plug',
			],
			[
				'type'     => 'core_update',
				'title'    => __( 'WordPress Core update', 'nexcess-mapps' ),
				'template' => __( '<%- previous_object_version %> to <%- object_version %>', 'nexcess-mapps' ),
				'icon'     => 'refresh',
			],
			[
				'type'     => 'core_downgrade',
				'title'    => __( 'WordPress Core downgrade', 'nexcess-mapps' ),
				'template' => __( '<%- previous_object_version %> to <%- object_version %>', 'nexcess-mapps' ),
				'icon'     => 'refresh',
			],
			[
				'type'     => 'parent_theme_update',
				'title'    => __( 'Parent theme update', 'nexcess-mapps' ),
				'template' => __( '<%- object_name %> <%- previous_object_version %> to <%- object_version %>', 'nexcess-mapps' ),
				'icon'     => 'paintbrush',
			],
			[
				'type'     => 'child_theme_update',
				'title'    => __( 'Child theme update', 'nexcess-mapps' ),
				'template' => __( '<%- object_name %> <%- previous_object_version %> to <%- object_version %>', 'nexcess-mapps' ),
				'icon'     => 'paintbrush',
			],
			[
				'type'     => 'theme_update',
				'title'    => __( 'Theme update', 'nexcess-mapps' ),
				'template' => __( '<%- object_name %> <%- previous_object_version %> to <%- object_version %>', 'nexcess-mapps' ),
				'icon'     => 'paintbrush',
			],
			[
				'type'     => 'parent_theme_downgrade',
				'title'    => __( 'Parent theme downgrade', 'nexcess-mapps' ),
				'template' => __( '<%- object_name %> <%- previous_object_version %> to <%- object_version %>', 'nexcess-mapps' ),
				'icon'     => 'paintbrush',
			],
			[
				'type'     => 'child_theme_downgrade',
				'title'    => __( 'Child theme downgrade', 'nexcess-mapps' ),
				'template' => __( '<%- object_name %> <%- previous_object_version %> to <%- object_version %>', 'nexcess-mapps' ),
				'icon'     => 'paintbrush',
			],
			[
				'type'     => 'theme_downgrade',
				'title'    => __( 'Theme downgrade', 'nexcess-mapps' ),
				'template' => __( '<%- object_name %> <%- previous_object_version %> to <%- object_version %>', 'nexcess-mapps' ),
				'icon'     => 'paintbrush',
			],
			[
				'type'     => 'theme_change',
				'title'    => __( 'Theme change', 'nexcess-mapps' ),
				'template' => __( '<%- previous_object_name %> to <%- object_name %>', 'nexcess-mapps' ),
				'icon'     => 'paintbrush',
			],
		];
	}
}
