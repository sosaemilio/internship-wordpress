<?php

/**
 * Responsible for managing and registering dashboard widgets.
 */

namespace Nexcess\MAPPS\Services\Managers;

use Nexcess\MAPPS\Widgets\DashboardWidget;

class DashboardWidgetManager {

	/**
	 * An array of registered DashboardWidget objects.
	 *
	 * @var DashboardWidget[]
	 */
	protected $widgets = [];

	/**
	 * Register a new widget.
	 *
	 * @param DashboardWidget $widget The dashboard widget to register.
	 *
	 * @return self
	 */
	public function addWidget( DashboardWidget $widget ) {
		$this->widgets[] = $widget;

		return $this;
	}

	/**
	 * Return all registered widgets.
	 *
	 * @return DashboardWidget[] An array consisting of DashboardWidget instances.
	 */
	public function getWidgets() {
		return $this->widgets;
	}

	/**
	 * Register the widget with WordPress.
	 *
	 * @return self
	 */
	public function registerWidgets() {
		foreach ( $this->getWidgets() as $widget ) {
			wp_add_dashboard_widget(
				$widget->id,
				$widget->name,
				[ $widget, 'render' ],
				$widget->controlCallback,
				null, // Widgets manage template data internally.
				$widget->context,
				$widget->priority
			);
		}

		return $this;
	}
}
