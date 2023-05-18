<?php

namespace StellarWP\PluginFramework\Support\Testing\Concerns;

use Psr\Log\LoggerInterface;
use StellarWP\PluginFramework\Container;
use StellarWP\PluginFramework\Support\Testing\TestLogger;

/**
 * Assign $this->container and reset it between tests.
 *
 * Additionally, inject a test logger into the newly-constructed container, preventing error logs
 * from leaking into test output.
 */
trait InteractsWithContainer
{
    /**
     * The test container instance.
     *
     * @var Container
     */
    protected $container;

    /**
     * The test logger.
     *
     * @var TestLogger
     */
    protected $logger;

    /**
     * @before
     *
     * @return void
     */
    public function resetContainerBetweenTests()
    {
        $container       = getenv('STELLARWP_CONTAINER_CLASS') ?: Container::class;
        $this->logger    = new TestLogger();
        $this->container = $container::getInstance(new $container());
        $this->container->extend(LoggerInterface::class, $this->logger);

        /*
         * Older versions of PHPUnit (5.x, used by PHP 5.6) process the ordering of @before annotations
         * differently than modern versions. To prevent issues with (for example) fixtures relying on
         * the $container property, name these fixtures "setUpAfterContainer":
         *
         *     protected function setUpAfterContainer()
         *     {
         *         $this->container->extend(...);
         *     }
         *
         * Note that these methods should *not* have the @before annotation!
         *
         * If you don't need to support PHP 5.6, you may safely ignore this :).
         */
        if (method_exists($this, 'setUpAfterContainer')) {
            $this->setUpAfterContainer();
        }
    }

    /**
     * Get an array of dependencies for objects resolved through the container using reflection.
     *
     * This comes in handy in tests, as we're able to create mocks that dynamically retrieve their
     * class dependencies via the DI container.
     *
     * @param object|string $class An instance or the name of the class to inspect.
     *
     * @return Array<mixed> An array of resolved constructor args for the class.
     */
    protected function getClassDependencies($class)
    {
        // PHP 5.x doesn't let us extract types via reflection, so skip these tests.
        if (5 === PHP_MAJOR_VERSION) {
            $this->markTestSkipped('Unable to get class dependencies via Reflection in PHP 5.x.');
        }

        $reflection  = new \ReflectionClass($class);
        $constructor = $reflection->getConstructor();
        $args        = [];

        // No class constructor means no arguments.
        if (null === $constructor) {
            return $args;
        }

        // Loop through the parameters, collecting types.
        foreach ($constructor->getParameters() as $parameter) {
            $type = $parameter->getType();
            $name = null;

            // If we have a declared type, attempt to resolve it through the container.
            if ($type instanceof \ReflectionNamedType) {
                $name = $type->getName();
            } elseif (version_compare(PHP_VERSION, '7.1', '<')) {
                $name = (string) $type;
            }

            $args[] = $name && $this->container->has($name) ?
                $this->container->get($name)
                : $name;
        }

        return $args;
    }
}
