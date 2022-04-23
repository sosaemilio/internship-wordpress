<?php

namespace Nexcess\MAPPS\HealthChecks;

/**
 * @property-read string[] $actions
 * @property-read string   $badgeColor
 * @property-read string   $badgeLabel
 * @property-read string   $description
 * @property-read string   $label
 * @property-read string   $id
 * @property-read string   $priority
 */
abstract class HealthCheck {

	/**
	 * Actions that users may take to remedy the test result.
	 *
	 * @var string
	 */
	protected $actions;

	/**
	 * The health check badge color. Core styles support blue, green, red, orange, purple and gray.
	 *
	 * @var string
	 */
	protected $badgeColor = 'blue';

	/**
	 * The health check badge content.
	 *
	 * @var string
	 */
	protected $badgeLabel;

	/**
	 * A description of the test.
	 *
	 * @var string
	 */
	protected $description;

	/**
	 * The Health Check label.
	 *
	 * @var string
	 */
	protected $label;

	/**
	 * The health check's ID.
	 *
	 * @var string
	 */
	protected $id;

	/**
	 * The priority of the check. One of "recommended" or "critical".
	 *
	 * @var string
	 */
	protected $priority = 'recommended';

	/**
	 * Expose protected properties for reading.
	 *
	 * @param string $prop The property being accessed.
	 *
	 * @return mixed The value of $this->$prop or null.
	 */
	public function __get( $prop ) {
		return isset( $this->{ $prop } ) ? $this->{ $prop } : null;
	}

	/**
	 * Execute the test and return an array suitable for the site_status_tests filter.
	 *
	 * @return mixed[]
	 */
	public function run() {
		$passed = $this->performCheck();

		return [
			'label'       => $this->label,
			'status'      => $passed ? 'good' : $this->priority,
			'badge'       => [
				'label' => $this->badgeLabel,
				'color' => $this->badgeColor,
			],
			'description' => wpautop( $this->description ),
			'actions'     => wpautop( $this->actions ),
			'test'        => $this->id,
		];
	}

	/**
	 * Perform the site health check.
	 *
	 * This method is responsible for running the check and updating object properties accordingly.
	 *
	 * @return bool True if the check passes, false if it fails.
	 */
	abstract public function performCheck();
}
