<?php

/**
 * WooCommerce Sales Performance Monitor.
 */

namespace Nexcess\MAPPS\Integrations;

use Nexcess\MAPPS\Concerns\HasHooks;
use Nexcess\MAPPS\Concerns\HasWordPressDependencies;
use Nexcess\MAPPS\Concerns\ManagesGroupedOptions;
use Nexcess\MAPPS\Integrations\SalesPerformanceMonitor\Alerts\Inbox;
use Nexcess\MAPPS\Integrations\SalesPerformanceMonitor\Database\Actions;
use Nexcess\MAPPS\Integrations\SalesPerformanceMonitor\Database\Setup;
use Nexcess\MAPPS\Integrations\SalesPerformanceMonitor\Helpers;
use Nexcess\MAPPS\Integrations\SalesPerformanceMonitor\Process\CronTasks;
use Nexcess\MAPPS\Integrations\SalesPerformanceMonitor\Process\Targets;
use Nexcess\MAPPS\Integrations\SalesPerformanceMonitor\Process\Triggers;
use Nexcess\MAPPS\Modules\Telemetry;
use Nexcess\MAPPS\Services\Options;
use Nexcess\MAPPS\Settings;

class SalesPerformanceMonitor extends Integration {
	use HasHooks;
	use HasWordPressDependencies;
	use ManagesGroupedOptions;

	/**
	 * Initialization check action to prevent the check from running multiple times in one request.
	 */
	const INITIALIZE_SPM_CHECK_ACTION = 'Nexcess\\MAPPS\\Integrations\\SalesPerformanceMonitor\\InitializeCheck';

	/**
	 * Base constants.
	 */
	const INCLUDES_PATH = __DIR__ . '/includes';

	/**
	 * Various prefixes for our actions and filters.
	 */
	const HOOK_PREFIX      = 'woo_spm_';
	const NONCE_PREFIX     = 'woo_spm_nonce_';
	const TRANSIENT_PREFIX = 'wcspm_tr_';
	const OPTION_PREFIX    = 'woo_spm_setting_';

	/**
	 * Set the table prefix, option key, and DB versions used to store the schemas.
	 */
	const TABLE_PREFIX = 'wc_spm_';
	const DB_VERS      = '1';
	const SCHEMA_KEY   = self::HOOK_PREFIX . 'db_version';

	/**
	 * Set our cron function name constants.
	 */
	const FIRST_TIME_CRON = 'wc_spm_run_first_check';
	const CHECKPOINT_CRON = 'wc_spm_run_checkpoint';

	/**
	 * Define the parts for the Woo inbox.
	 */
	const WOO_INBOX_SOURCE  = 'spm-monitor-inbox';
	const WOO_INBOX_NOTE_ID = 'spm-check-result';
	const WOO_INBOX_ACTION  = 'spm-view-orders';

	/**
	 * The option for disabling the performance monitor.
	 */
	const OPTION_NAME = 'nexcess_mapps_sales_performance_monitor';

	/**
	 * The key used in the telemetry report which contains the relevant integration info.
	 */
	const TELEMETRY_FEATURE_KEY = 'sales_performance_monitor';

	/**
	 * @var \Nexcess\MAPPS\Services\Options
	 */
	protected $options;

	/**
	 * @var \Nexcess\MAPPS\Settings
	 */
	protected $settings;

	/**
	 * @param \Nexcess\MAPPS\Services\Options $options
	 * @param \Nexcess\MAPPS\Settings         $settings
	 */
	public function __construct( Options $options, Settings $settings ) {
		$this->options  = $options;
		$this->settings = $settings;
	}

	/**
	 * Determine whether or not this integration should be loaded.
	 *
	 * @return bool Whether or not this integration be loaded in this environment.
	 */
	public function shouldLoadIntegration() {
		return self::isPluginActive( 'woocommerce/woocommerce.php' );
	}

	/**
	 * Perform any necessary setup for the integration.
	 *
	 * This method is automatically called as part of Plugin::loadIntegration(), and is the
	 * entry-point for all integrations.
	 */
	public function setup() {
		$this->registerOption();
		$this->addHooks();
	}

	/**
	 * Retrieve all filters for the integration.
	 *
	 * @return array[]
	 */
	protected function getFilters() {
		return [
			[ 'cron_schedules', [ $this, 'setCustomCronInterval' ] ],
			[ Telemetry::REPORT_DATA_FILTER, [ $this, 'addFeatureToTelemetry' ] ],
		];
	}

	/**
	 * Retrieve all actions for the integration.
	 *
	 * @return array[]
	 */
	protected function getActions() {
		return [
			[ self::FIRST_TIME_CRON, [ $this, 'runInitialCheckpointEntry' ] ],
			[ self::CHECKPOINT_CRON, [ $this, 'runOngoingCheckpointEntries' ] ],
			[
				'Nexcess\MAPPS\Options\Update',
				[ $this, 'saveValue' ],
				10,
				3,
			],
			[
				'woocommerce_after_register_post_type',
				[ $this, 'initializeIntegration' ],
				11,
			],
		];
	}

	/**
	 * Add a new interval to run every 4 hours.
	 *
	 * @param array $schedules The current array of intervals.
	 *
	 * @return array
	 */
	public function setCustomCronInterval( $schedules ) {

		// Only add it if it doesn't exist.
		if ( ! isset( $schedules['fourhours'] ) ) {

			// Make the interval.
			$setup_interval = HOUR_IN_SECONDS * 4;

			// Add our new one.
			$schedules['fourhours'] = [
				'interval' => absint( $setup_interval ),
				'display'  => esc_html__( 'Every 4 Hours', 'nexcess-mapps' ),
			];
		}

		return $schedules;
	}

	/**
	 * When the settings are saved, update the SPM settings.
	 *
	 * @param string|array $key  The key of the option being saved.
	 * @param mixed        $new  New value.
	 * @param mixed        $prev Previous value, most likely true or false.
	 */
	public function saveValue( $key, $new, $prev ) {
		// If nothing changed, do nothing.
		if ( $prev === $new ) {
			return;
		}

		// Only apply to our option.
		if ( ! $this->options->verifyOptionKey( $key, [ self::OPTION_NAME, 'sales_performance_monitor_is_enabled' ] ) ) {
			return;
		}

		if ( true === (bool) $new ) {
			$this->enableSalesPerformanceMonitor();
		} else {
			$this->disableSalesPerformanceMonitor();
		}
	}

	/**
	 * Require the includes after WooCommerce has loaded.
	 */
	public function initializeIntegration() {

		// Make sure that we don't run this action multiple times in one request.
		if ( did_action( self::INITIALIZE_SPM_CHECK_ACTION ) ) {
			return;
		}

		// Load the multi-use files first.
		require_once __DIR__ . '/SalesPerformanceMonitor/Helpers.php';
		require_once __DIR__ . '/SalesPerformanceMonitor/Utilities.php';

		// Load our files relating to the custom DB.
		require_once __DIR__ . '/SalesPerformanceMonitor/Database/Setup.php';
		require_once __DIR__ . '/SalesPerformanceMonitor/Database/Functions.php';
		require_once __DIR__ . '/SalesPerformanceMonitor/Database/Actions.php';
		require_once __DIR__ . '/SalesPerformanceMonitor/Database/Queries.php';

		// Pull in the processing files.
		require_once __DIR__ . '/SalesPerformanceMonitor/Process/Orders.php';
		require_once __DIR__ . '/SalesPerformanceMonitor/Process/Targets.php';
		require_once __DIR__ . '/SalesPerformanceMonitor/Process/Triggers.php';
		require_once __DIR__ . '/SalesPerformanceMonitor/Process/CronTasks.php';

		// And our alerts.
		require_once __DIR__ . '/SalesPerformanceMonitor/Alerts/Email.php';
		require_once __DIR__ . '/SalesPerformanceMonitor/Alerts/Inbox.php';
		require_once __DIR__ . '/SalesPerformanceMonitor/Alerts/Content.php';

		// Check if the setup was completed already.
		if ( empty( get_option( 'sales_performance_monitor_setup_complete', false ) ) ) {
			$this->activate();
		}

		do_action( self::INITIALIZE_SPM_CHECK_ACTION );
	}

	/**
	 * Adds feature integration information to the telemetry report.
	 *
	 * @param array[] $report The gathered report data.
	 *
	 * @return array[] The $report array.
	 */
	public function addFeatureToTelemetry( array $report ) {
		$report['features'][ self::TELEMETRY_FEATURE_KEY ] = $this->getOption()->sales_performance_monitor_is_enabled;
		return $report;
	}

	/**
	 * Add a toggle to the settings page.
	 */
	public function registerOption() {
		$this->options->addOption(
			[ self::OPTION_NAME, 'sales_performance_monitor_is_enabled' ],
			'checkbox',
			__( 'Enable WooCommerce Sales Performance Monitor', 'nexcess-mapps' ),
			[
				'description' => __( 'WooCommerce Sales Performance Monitor - track ongoing sales data and alert store owners when changes occur.', 'nexcess-mapps' ),
				'default'     => false,
			]
		);
	}

	/**
	 * Get current setting for Sales Performance Monitor.
	 *
	 * @return bool
	 */
	public function getSalesPerformanceMonitorSetting() {
		return $this->getOption()->sales_performance_monitor_is_enabled;
	}

	/**
	 * Enable Sales Performance Monitor.
	 */
	public function enableSalesPerformanceMonitor() {
		$this->activate();
		$this->getOption()->set( 'sales_performance_monitor_is_enabled', true )->save();
	}

	/**
	 * Disable Sales Performance Monitor.
	 */
	public function disableSalesPerformanceMonitor() {
		$this->deactivate();
		$this->getOption()->set( 'sales_performance_monitor_is_enabled', false )->save();
	}

	/**
	 * Deactivation process.
	 */
	public function deactivate() {
		// Include our action so that we may add to this later.
		do_action( self::HOOK_PREFIX . 'before_deactivate_process' );

		// Clear events.
		wp_clear_scheduled_hook( self::FIRST_TIME_CRON );
		wp_clear_scheduled_hook( self::CHECKPOINT_CRON );

		// Include our action so that we may add to this later.
		do_action( self::HOOK_PREFIX . 'after_deactivate_process' );

		// And flush our rewrite rules.
		flush_rewrite_rules();
	}

	/**
	 * Our inital setup function when activated.
	 */
	public function activate() {

		// Include our action so that we may add to this later.
		do_action( self::HOOK_PREFIX . 'before_activate_process' );

		// Run the database build.
		$this->buildDbTables();

		// Set our initial values.
		$this->setInitialValues();

		// Include our action so that we may add to this later.
		do_action( self::HOOK_PREFIX . 'after_activate_process' );

		// And flush our rewrite rules.
		flush_rewrite_rules();

		update_option( 'sales_performance_monitor_setup_complete', true );
	}

	/**
	 * Run the database setup.
	 */
	protected function buildDbTables() {

		// Attempt the table build.
		$maybe_db_installed = Setup::maybe_install_tables();

		// If it didn't come back false, we're good.
		if ( $maybe_db_installed ) {
			return true;
		}

		// Deactivate the integation.
		$this->disableSalesPerformanceMonitor();
		return false;
	}

	/**
	 * Calculate our initial values.
	 */
	protected function setInitialValues() {

		// Now try to run the first batch revenue.
		$maybe_base_targets = Targets::calculate_initial_base_revenue();

		// If nothing came back, or a WP_Error, set a flag for a notice.
		// @@todo set the actual admin notice.
		if ( empty( $maybe_base_targets ) || 'done' !== sanitize_text_field( $maybe_base_targets ) ) {

			// Determine the error.
			$set_error_code = ! empty( $maybe_base_targets ) ? sanitize_text_field( $maybe_base_targets ) : 'unknown';

			// And set the error.
			update_option( self::OPTION_PREFIX . 'initial_error_setup', $set_error_code, 'no' );
		}

		// Schedule our first cron job for setup.
		CronTasks::set_initial_checkpoint_cron();
	}

	/**
	 * Handle running our first checkpoint entry.
	 */
	public function runInitialCheckpointEntry() {

		// Ensure the integration is active.
		if ( ! $this->getSalesPerformanceMonitorSetting() ) {
			return;
		}

		// Set the process.
		$maybe_first_entry = Triggers::add_initial_checkpoint_entry();

		// If we failed, set the flag and a new entry.
		if ( false === $maybe_first_entry ) {

			// Set the error.
			update_option( self::OPTION_PREFIX . 'initial_error_setup', 'first-checkpoint', 'no' );

			// Set the timestamp to run in an hour.
			$set_initial_stamp = time() + HOUR_IN_SECONDS;

			// Now schedule our new one, assuming we passed a new frequency.
			wp_schedule_single_event( $set_initial_stamp, self::FIRST_TIME_CRON );
		}

		// If this worked, go and set our ongoing cron.
		if ( false !== $maybe_first_entry ) {
			CronTasks::set_ongoing_checkpoints_cron();
		}

		return true;
	}

	/**
	 * Handle running our ongoing entries.
	 */
	public function runOngoingCheckpointEntries() {

		// Ensure the integration is active.
		if ( ! $this->getSalesPerformanceMonitorSetting() ) {
			return;
		}

		// Determine if we need to run the checkpoint.
		$fetch_checkpoint_args = Helpers::maybe_run_checkpoint();

		// Bail if we don't have the flag to run.
		if ( empty( $fetch_checkpoint_args ) ) {
			return false;
		}

		// Get the numeric day for the range.
		$get_range_num_day = gmdate( 'N', absint( $fetch_checkpoint_args['next'] ) );

		// On these days, we do nothing.
		if ( 2 === absint( $get_range_num_day ) || 3 === absint( $get_range_num_day ) || 5 === absint( $get_range_num_day ) ) {
			return true;
		}

		// Handle running the Monday week end check.
		if ( 1 === absint( $get_range_num_day ) ) {

			// Get our week end results.
			$week_end_results = Targets::calculate_week_end_revenue( $fetch_checkpoint_args );

			// Now add the checkpoint.
			$insert_checkpoint = Triggers::add_ongoing_checkpoint_entry( $week_end_results );

			// If the insert failed, bail on the rest.
			// @todo some sort of error reporting here?
			if ( true !== $insert_checkpoint ) {
				return false;
			}

			// Update the start stamp.
			update_option( self::OPTION_PREFIX . 'range_start', $fetch_checkpoint_args['next'], 'no' );

			// Run the new target calculations using the past 8 weeks (again).
			Targets::calculate_new_weekly_targets( $week_end_results['stamp'] );

			// And trigger our notifications.
			Triggers::send_week_end_check_results( $fetch_checkpoint_args, $week_end_results );

			// Include an action to run after the alert is done.
			do_action( self::HOOK_PREFIX . 'after_week_end_alerts', $fetch_checkpoint_args, $week_end_results );
		}

		// Handle the two midpoint checks.
		if ( 4 === absint( $get_range_num_day ) || 6 === absint( $get_range_num_day ) ) {

			// First get the results.
			$midpoint_results = Targets::calculate_midpoint_revenue( $fetch_checkpoint_args );

			// Run the new target calculations.
			Targets::calculate_new_midpoint_targets( $midpoint_results['variance'] );

			// And trigger our notifications.
			Triggers::send_midpoint_check_results( $fetch_checkpoint_args, $midpoint_results );

			// Include an action to run after the alert is done.
			do_action( self::HOOK_PREFIX . 'after_midpoint_alerts', $fetch_checkpoint_args, $midpoint_results );
		}

		// Update the last checked.
		update_option( self::OPTION_PREFIX . 'range_last', $fetch_checkpoint_args['next'], 'no' );

		// Nothing else (probably).
		return true;
	}
}
