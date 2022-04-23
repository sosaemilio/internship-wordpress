<?php

namespace Nexcess\MAPPS\Concerns;

trait HasHooks {

	/**
	 * Perform any necessary setup for the integration.
	 *
	 * This method is automatically called as part of Plugin::loadIntegration(), and is the
	 * entry-point for all integrations.
	 */
	public function setup() {
		$this->addHooks();
	}

	/**
	 * Retrieve all actions for the integration.
	 *
	 * @return array[]
	 */
	protected function getActions() {
		return [];
	}

	/**
	 * Retrieve all filters for the integration.
	 *
	 * @return array[]
	 */
	protected function getFilters() {
		return [];
	}

	/**
	 * Add hooks into WordPress.
	 */
	protected function addHooks() {
		array_map(
			function ( $hook ) {
				call_user_func_array( 'add_action', $hook );
			},
			$this->getActions()
		);

		array_map(
			function ( $hook ) {
				call_user_func_array( 'add_filter', $hook );
			},
			$this->getFilters()
		);
	}

	/**
	 * Remove hooks from WordPress.
	 */
	protected function removeHooks() {
		array_map(
			function ( $hook ) {
				call_user_func_array( 'remove_action', $hook );
			},
			$this->getActions()
		);

		array_map(
			function ( $hook ) {
				call_user_func_array( 'remove_filter', $hook );
			},
			$this->getFilters()
		);
	}
}
