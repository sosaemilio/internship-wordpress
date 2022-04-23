<?php

namespace Nexcess\MAPPS\Concerns;

trait HasCronEvents {
	use HasHooks {
		HasHooks::addHooks as defaultAddHooks;
		HasHooks::removeHooks as defaultRemoveHooks;
	}

	/**
	 * @var array[]
	 */
	private $cronEvents = [];

	/**
	 * Add hooks into WordPress.
	 */
	protected function addHooks() {
		$this->defaultAddHooks();

		add_action( 'init', [ $this, 'scheduleEvents' ] );
	}

	/**
	 * Remove hooks from WordPress.
	 */
	protected function removeHooks() {
		$this->defaultRemoveHooks();

		remove_action( 'init', [ $this, 'scheduleEvents' ] );
	}

	/**
	 * Register a new cron event.
	 *
	 * @param string              $hook     The action hook for the event.
	 * @param ?string             $interval The cron interval, which can be any value returned by
	 *                                      wp_get_schedules() or null to schedule the event only once.
	 * @param ?\DateTimeInterface $time     Optional. A DateTime object representing when the first
	 *                                      event should occur. Default is null.
	 * @param mixed[]             $args     Optional. An array of arguments to pass to the callbacks.
	 *
	 * @return self
	 */
	protected function registerCronEvent( $hook, $interval, \DateTimeInterface $time = null, array $args = [] ) {
		$this->cronEvents[] = [
			'args'     => $args,
			'hook'     => $hook,
			'interval' => $interval,
			'time'     => $time,
		];

		return $this;
	}

	/**
	 * Schedule cron events for this integration.
	 */
	public function scheduleEvents() {
		foreach ( $this->cronEvents as $event ) {
			// Move on if we've already registered this event.
			if ( false !== wp_next_scheduled( $event['hook'], $event['args'] ) ) {
				continue;
			}

			// If no time was provided, pick one at random in the next 24 hours.
			if ( ! $event['time'] instanceof \DateTimeInterface ) {
				$event['time'] = $this->getRandomTimeInFuture( DAY_IN_MINUTES );
			}

			// If no interval is provided, treat this as a one-time event.
			if ( null === $event['interval'] ) {
				wp_schedule_single_event( (int) $event['time']->format( 'U' ), $event['hook'], $event['args'] );
			} else {
				wp_schedule_event( (int) $event['time']->format( 'U' ), $event['interval'], $event['hook'], $event['args'] );
			}
		}
	}

	/**
	 * Unschedule cron events for this integration.
	 */
	protected function unscheduleEvents() {
		foreach ( $this->cronEvents as $event ) {
			$timestamp = wp_next_scheduled( $event['hook'] );

			if ( ! $timestamp ) {
				continue;
			}

			wp_unschedule_event( $timestamp, $event['hook'] );
		}
	}

	/**
	 * Get a randomized DateTime object within the next $minutes minutes.
	 *
	 * @param int $minutes The maximum number of minutes from right now.
	 *
	 * @return \DateTimeImmutable
	 */
	private function getRandomTimeInFuture( $minutes ) {
		$interval = new \DateInterval( sprintf( 'PT%dM', wp_rand( 1, $minutes ) ) );

		return current_datetime()->add( $interval );
	}
}
