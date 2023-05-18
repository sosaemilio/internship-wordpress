<?php

namespace StellarWP\PluginFramework\Modules;

use StellarWP\PluginFramework\Services\DropIn;
use StellarWP\PluginFramework\Services\Managers\CronEventManager;
use StellarWP\PluginFramework\Support\CronEvent;

/**
 * The Maintenance module is responsible for registering general maintenance tasks that should be
 * run on a cron schedule.
 */
class Maintenance extends Module
{
    /**
     * The CronEventManager instance.
     *
     * @var CronEventManager
     */
    protected $cron;

    /**
     * The DropIn service instance.
     *
     * @var DropIn
     */
    protected $dropins;

    /**
     * The daily cron action name.
     */
    const DAILY_CRON_HOOK = 'stellarwp_daily_maintenance';

    /**
     * Construct a new instance of the module.
     *
     * @param CronEventManager $cron    The CronEventManager instance.
     * @param DropIn           $dropins The DropIn service.
     */
    public function __construct(CronEventManager $cron, DropIn $dropins)
    {
        $this->cron    = $cron;
        $this->dropins = $dropins;
    }

    /**
     * Perform any necessary setup for the module.
     *
     * @return void
     */
    public function setup()
    {
        // Register a daily cron event for maintenance tasks.
        $this->cron->register(static::DAILY_CRON_HOOK, CronEvent::DAILY, current_datetime());

        // Once a day, clean up any broken drop-ins.
        add_action(static::DAILY_CRON_HOOK, [$this->dropins, 'clean']);
    }
}
