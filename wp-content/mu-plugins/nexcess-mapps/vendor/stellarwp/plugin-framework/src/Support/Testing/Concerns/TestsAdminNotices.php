<?php

namespace StellarWP\PluginFramework\Support\Testing\Concerns;

use StellarWP\AdminNotice\AdminNotice;
use StellarWP\PluginFramework\Support\Hook;

/**
 * Methods for testing admin notices.
 *
 * @phpstan-import-type HookCallbackArray from Hook
 */
trait TestsAdminNotices
{
    /**
     * Admin notices present during test setup.
     *
     * @var Hook
     */
    protected $adminNotices;

    /**
     * Admin notices present during test setup.
     *
     * @var HookCallbackArray
     */
    protected $defaultAdminNotices;

    /**
     * Track the admin notices at the very beginning of the test.
     */
    public function setUpTestsAdminNotices()
    {
        $this->adminNotices        = new Hook($GLOBALS['wp_filter']['admin_notices'])
            ? new Hook($GLOBALS['wp_filter']['admin_notices'])
            : new Hook();
        $this->defaultAdminNotices = $this->adminNotices->flatten();
    }

    /**
     * Retrieve all currently-registered admin notices.
     *
     * @return HookCallbackArray
     */
    protected function getNewAdminNoticeCallbacks()
    {
        return array_diff_key($this->getRegisteredAdminNoticeCallbacks(), $this->defaultAdminNotices);
    }

    /**
     * Retrieve an array of any queued AdminNotice instances.
     *
     * @return Array<AdminNotice>
     */
    protected function getRegisteredAdminNotices()
    {
        return array_reduce($this->getRegisteredAdminNoticeCallbacks(), function ($carry, $callback) {
            if (is_array($callback['function']) && current($callback['function']) instanceof AdminNotice) {
                $carry[] = current($callback['function']);
            }

            return $carry;
        }, []);
    }

    /**
     * Retrieve all currently-registered admin notices.
     *
     * @return HookCallbackArray&Array
     */
    protected function getRegisteredAdminNoticeCallbacks()
    {
        return $this->adminNotices->flatten();
    }
}
