<?php

namespace StellarWP\PluginFramework\Services\Managers;

use StellarWP\PluginFramework\Support\CronEvent;

/**
 * Manager responsible for the registration of WP-Cron events.
 */
class CronEventManager
{
    /**
     * All registered cron events.
     *
     * @var Array<int,CronEvent>
     * @phpstan-var list<CronEvent>
     */
    protected $events = [];

    /**
     * Retrieve all registered events.
     *
     * @phpstan-return list<CronEvent>
     * @return Array<int,CronEvent>
     */
    public function all()
    {
        return $this->events;
    }

    /**
     * Register a new cron event.
     *
     * @param string              $action   The action hook used to trigger this event.
     * @param ?string             $interval Optional. How often the event should be run, one of the
     *                                       values returned from {@see wp_get_schedules()}. If null,
     *                                       the event will be scheduled once. Default is null.
     * @param ?\DateTimeInterface $time     Optional. A DateTime object representing when the first
     *                                       event should occur. Default is null.
     * @param list<mixed>         $args     Optional. Arguments to pass to the callback. Default is empty.
     *
     * @return $this
     */
    public function register($action, $interval = null, $time = CronEvent::ONCE, $args = [])
    {
        $this->events[] = new CronEvent($action, $interval, $time, $args);

        return $this;
    }

    /**
     * Register a new cron event directly.
     *
     * @param CronEvent $event The CronEvent object.
     *
     * @return $this
     */
    public function registerCronEvent(CronEvent $event)
    {
        $this->events[] = $event;

        return $this;
    }

    /**
     * Register the CronEvents within WordPress.
     *
     * @return $this
     */
    public function scheduleEvents()
    {
        foreach ($this->events as $event) {
            // Move on if we've already registered this event.
            if (false !== wp_next_scheduled($event->action, $event->args)) {
                continue;
            }

            if (CronEvent::ONCE === $event->interval) {
                wp_schedule_single_event((int) $event->time->format('U'), $event->action, $event->args);
            } else {
                wp_schedule_event((int) $event->time->format('U'), $event->interval, $event->action, $event->args);
            }
        }

        return $this;
    }
}
