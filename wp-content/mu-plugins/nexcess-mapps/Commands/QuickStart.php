<?php

namespace Nexcess\MAPPS\Commands;

use Kadence_Starter_Templates\Importer;
use Kadence_Starter_Templates\Starter_Templates;
use Nexcess\MAPPS\Exceptions\WPErrorException;
use Nexcess\MAPPS\Integrations\QuickStart as Integration;
use Nexcess\MAPPS\Integrations\SimpleAdminMenu;
use Nexcess\MAPPS\Services\Importers\KadenceImporter;
use Nexcess\MAPPS\Services\Logger;
use Nexcess\MAPPS\Settings;

/**
 * Commands specific to WP QuickStart.
 */
class QuickStart extends Command {

	/**
	 * @var \Nexcess\MAPPS\Services\Importers\KadenceImporter
	 */
	protected $importer;

	/**
	 * @var \Nexcess\MAPPS\Integrations\QuickStart
	 */
	protected $integration;

	/**
	 * @var \Nexcess\MAPPS\Integrations\SimpleAdminMenu
	 */
	protected $adminMenus;

	/**
	 * @var \Nexcess\MAPPS\Services\Logger
	 */
	protected $logger;

	/**
	 * @var \Nexcess\MAPPS\Settings
	 */
	protected $settings;

	/**
	 * Create a new instance of the command.
	 *
	 * @param \Nexcess\MAPPS\Settings                           $settings
	 * @param \Nexcess\MAPPS\Integrations\QuickStart            $integration
	 * @param \Nexcess\MAPPS\Integrations\SimpleAdminMenu       $admin_menus
	 * @param \Nexcess\MAPPS\Services\Logger                    $logger
	 * @param \Nexcess\MAPPS\Services\Importers\KadenceImporter $importer
	 */
	public function __construct(
		Settings $settings,
		Integration $integration,
		SimpleAdminMenu $admin_menus,
		Logger $logger,
		KadenceImporter $importer
	) {
		$this->settings    = $settings;
		$this->integration = $integration;
		$this->adminMenus  = $admin_menus;
		$this->logger      = $logger;
		$this->importer    = $importer;
	}

	/**
	 * Build out a WP QuickStart site based on its ID.
	 *
	 * ## OPTIONS
	 *
	 * [<site_id>]
	 * : The WP QuickStart site ID. Defaults to $settings->quickstart_site_id.
	 *
	 * [--force]
	 * : Ingest content, even if the ingestion lock has already been set.
	 *
	 * [--reset-content]
	 * : Reset previously-imported content before running.
	 *
	 * ## EXAMPLES
	 *
	 *   # Build the site based on the ID provided by SiteWorx
	 *   wp nxmapps quickstart build
	 *
	 *   # Build the site based on specific UUID
	 *   wp nxmapps quickstart build 3e973431-56d6-4c86-8278-84a72ad5238b
	 *
	 * @param string[] $args    Positional arguments.
	 * @param string[] $options Associative arguments.
	 */
	public function build( array $args, array $options ) {
		$site_id = ! empty( $args[0] ) ? $args[0] : $this->settings->quickstart_site_id;

		$this->step( sprintf( 'Fetching details for site %s', $site_id ) );
		try {
			$spec = $this->integration->getSiteDetails( $site_id );

			add_filter( 'kadence_starter_templates_custom_array', function () use ( $spec ) {
				return [
					'wp-quickstart' => $spec,
				];
			} );
		} catch ( WPErrorException $e ) {
			return $this->error( sprintf( 'Unable to retrieve results for site %s, aborting.', $site_id ) );
		}

		// Install Kadence + related plugins.
		$this->step( 'Installing Kadence and its dependencies' );
		$this->wp( 'nxmapps storebuilder kadence' );
		$this->wp( 'plugin install --activate kadence-starter-templates' );

		// Set up the Kadence Starter Templates.
		$templates = Starter_Templates::get_instance();
		$templates->setup_plugin_with_filter_data();
		$templates->importer->set_logger( $this->logger );

		// Install any other required plugins prior to importing.
		if ( ! empty( $spec['plugins'] ) ) {
			$this->wp( sprintf( 'plugin install --activate %s', implode( ' ', $spec['plugins'] ) ) );
		}

		// If --reset-content was passed, remove previously-imported content first.
		if ( ! empty( $options['reset-content'] ) ) {
			$this->step( 'Clearing previously-imported content' );
			$templates->remove_past_data_ajax_callback();
		}

		// Execute any provided WP-CLI commands for before the import.
		if ( ! empty( $spec['quickstart']['commands_before'] ) ) {
			array_map(
				function( $command ) {
					$this->wp( $command, [ 'launch' => true ] );
				},
				(array) $spec['quickstart']['commands_before']
			);
		}

		// Import the template site using the importer service.
		$this->step( 'Importing template site' );
		$this->importer->import( 'wp-quickstart', 'custom' );

		// Execute any provided WP-CLI commands for after the import.
		if ( ! empty( $spec['quickstart']['commands_after'] ) ) {
			array_map(
				function( $command ) {
					$this->wp( $command, [ 'launch' => true ] );
				},
				(array) $spec['quickstart']['commands_after']
			);
		}

		// Store metadata.
		$option            = $this->integration->getOption();
		$option->type      = ! empty( $spec['quickstart']['type'] ) ? $spec['quickstart']['type'] : '';
		$option->dashboard = ! empty( $spec['quickstart']['dashboard'] ) ? $spec['quickstart']['dashboard'] : null;
		$option->save();

		// Store admin menu metadata.
		$menu_option               = $this->adminMenus->getOption();
		$menu_option->menuSections = ! empty( $spec['quickstart']['menu_sections'] )
			? $spec['quickstart']['menu_sections']
			: null;
		$menu_option->save();

		// Set admin menu welcome notice.
		$this->adminMenus->welcomeNotice();

		// Remove the Starter Templates plugin.
		$this->wp( 'plugin uninstall --deactivate kadence-starter-templates' );

		$this->success( sprintf( 'Site %s has been built successfully!', $site_id ) );
	}
}
