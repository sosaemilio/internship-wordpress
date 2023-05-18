<?php

namespace StellarWP\PluginFramework\Support;

use DateInterval;
use DateTimeImmutable;
use DateTimeInterface;
use Exception;

/**
 * A representation of a single WP-Cron event.
 */
class CronEvent
{
    /**
     * The action hook to trigger this event.
     *
     * @var string
     */
    public $action;

    /**
     * Arguments to pass to callbacks hooked into this event.
     *
     * @phpstan-var list<mixed>
     * @var Array<int,mixed>
     */
    public $args = [];

    /**
     * How often this event should occur. Null indicates a single occurrence.
     *
     * @var ?string
     */
    public $interval;

    /**
     * A DateTime object representing when the event should first occur.
     *
     * @var DateTimeInterface
     */
    public $time;

    /**
     * Default intervals.
     */
    const DAILY      = 'daily';
    const HOURLY     = 'hourly';
    const ONCE       = null;
    const TWICEDAILY = 'twicedaily';
    const WEEKLY     = 'weekly';

    /**
     * Construct a new CronEvent instance.
     *
     * @param string             $action   The action hook used to trigger this event.
     * @param ?string            $interval Optional. How often the event should be run, one of the
     *                                      values returned from {@see wp_get_schedules()}. If null,
     *                                      the event will be scheduled once. Default is null.
     * @param ?DateTimeInterface $time     Optional. A DateTime object representing when the first
     *                                      event should occur. If left null, a random time in the
     *                                      next 24 hours will be selected. Default is null.
     * @param list<mixed>        $args     Optional. Arguments to pass to the callback. Default is empty.
     */
    public function __construct($action, $interval = null, $time = self::ONCE, $args = [])
    {
        $this->action   = $action;
        $this->interval = $interval;
        $this->time     = $time ?: $this->getRandomTimeInFuture(DAY_IN_MINUTES);
        $this->args     = $args;
    }

    /**
     * Get a randomized DateTime object within the next $minutes minutes.
     *
     * @param int $minutes The maximum number of minutes from right now.
     *
     * @throws Exception If the $minutes cannot be converted to a valid interval.
     *
     * @return DateTimeImmutable
     */
    protected function getRandomTimeInFuture($minutes)
    {
        $interval = new DateInterval(sprintf('PT%dM', wp_rand(1, $minutes)));

        return current_datetime()->add($interval);
    }
}
