<?php

namespace StellarWP\PluginFramework\Support\Testing\Concerns;

use Mockery;
use Mockery\Exception\InvalidCountException;
use PHPUnit\Framework\Assert;

/**
 * This trait replaces Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration, making it compatible with
 * the yoast/phpunit-polyfills package.
 */
trait UsesMockery
{
    /**
     * @var bool
     */
    private $mockeryOpen;

    /**
     * @before
     *
     * @return void
     */
    protected function startMockery()
    {
        $this->mockeryOpen = true;
    }

    /**
     * @after
     *
     * @return void
     */
    protected function purgeMockeryContainer()
    {
        if ($this->mockeryOpen) {
            Mockery::close();
        }
    }

    /**
     * Instead of causing tests with unsatisfied Mockery expectations to be reported as errors,
     * explicitly mark them as failed.
     *
     * @return void
     */
    protected function closeMockery()
    {
        try {
            Mockery::close();
            $this->mockeryOpen = false;
        } catch (InvalidCountException $e) {
            // Replace "method from Some_Mockery_Class_Name" with "ActualClass::method", clean up whitespace.
            Assert::fail(str_replace(
                sprintf('%1$s from %2$s', $e->getMethodName(), $e->getMockName()),
                sprintf('%1$s::%2$s', get_parent_class($e->getMock()), $e->getMethodName()),
                (string) preg_replace('/\s{2,}/', ' ', (string) $e->getMessage())
            ));
        }
    }

    /**
     * Run Mockery assertions at the end of each test method.
     *
     * @return void
     *
     * phpcs:disable PSR1.Methods.CamelCapsMethodName.NotCamelCaps
     */
    protected function assert_post_conditions()
    {
        $this->addMockeryExpectationsToAssertionCount();
        $this->checkMockeryExceptions();
        $this->closeMockery();

        parent::assert_post_conditions();
    }
    // phpcs:enable PSR1.Methods.CamelCapsMethodName.NotCamelCaps

    /**
     * Add Mockery checks to the count of assertions.
     *
     * @return void
     */
    protected function addMockeryExpectationsToAssertionCount()
    {
        $this->addToAssertionCount(Mockery::getContainer()->mockery_getExpectationCount());
    }

    /**
     * Mark tests with failing expectations as risky.
     *
     * @return void
     */
    protected function checkMockeryExceptions()
    {
        foreach (Mockery::getContainer()->mockery_thrownExceptions() as $e) {
            if (! $e->dismissed()) {
                $this->markAsRisky();
            }
        }
    }

    /**
     * Helper for creating a mock without intellisense having a meltdown.
     *
     * @see Mockery::mock() for a full list of arguments.
     *
     * @template T
     * @param class-string<T>|T $class A class/interface name or a concrete instance of a class to mock.
     *
     * @return \Mockery\MockInterface&T
     */
    protected function mock($class)
    {
        /** @var \Mockery\Mock&T $mock */
        $mock = Mockery::mock(...func_get_args());

        return $mock;
    }
}
