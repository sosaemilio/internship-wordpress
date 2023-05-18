<?php

namespace StellarWP\PluginFramework\Support\Testing\Concerns;

use PHPUnit\Framework\Assert;

/**
 * Custom assertions for testing the WordPress Plugin API (e.g. "actions" and "filters").
 */
trait TestsHooks
{
    /**
     * Assert that the given action was fired.
     *
     * @param string $action  The action name.
     * @param ?int   $times   Optional. The number of times the action was expected to have been called.
     *                        If null, verify the hook fired at least once. Default is null.
     * @param string $message Optional. Additional context to include if the assertion fails.
     */
    protected function assertActionWasCalled($action, $times = null, $message = '')
    {
        $actual = did_action($action);

        // A specific count has been provided.
        if (is_numeric($times) && 0 <= $times) {
            $message = $message ?: sprintf(
                'Expected to see the "%s" action fired %d time(s), observed %d.',
                $action,
                $times,
                $actual
            );
            Assert::assertSame($times, $actual, $message);
        } else {
            $message = $message ?: sprintf('Expected to see the "%s" action fired at least once.', $action);
            Assert::assertGreaterThan(0, $actual, $message);
        }
    }

    /**
     * Assert that the given action was not fired.
     *
     * @param string $action  The action name.
     * @param string $message Optional. Additional context to include if the assertion fails.
     */
    protected function assertActionWasNotCalled($action, $message = '')
    {
        if (! $message) {
            $message = sprintf('Did not expect to see the "%s" action hook fired.', $action);
        }

        Assert::assertSame(0, did_action($action), $message);
    }
}
