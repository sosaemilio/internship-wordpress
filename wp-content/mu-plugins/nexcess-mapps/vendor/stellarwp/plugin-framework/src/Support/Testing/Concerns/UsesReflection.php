<?php

namespace StellarWP\PluginFramework\Support\Testing\Concerns;

/**
 * Helper methods for using PHP's Reflection features.
 */
trait UsesReflection
{
    /**
     * A helper for retrieving protected properties.
     *
     * @param class-string|object $class The name or and instance of the class that holds the property.
     * @param string              $prop  The property name.
     *
     * @return mixed The value of $class::$prop.
     */
    protected function getProtectedProperty($class, $prop)
    {
        $reflection = new \ReflectionProperty($class, $prop);
        $reflection->setAccessible(true);

        return is_object($class)
            ? $reflection->getValue($class)
            : $reflection->getValue();
    }

    /**
     * Invoke a proected method on an object.
     *
     * @param class-string|object $class   The name or and instance of the class that holds the method.
     * @param string              $method  The method name.
     * @param mixed               ...$args Method arguments.
     *
     * @return mixed The return value of $class::$method().
     */
    protected function invokeProtectedMethod($class, $method, ...$args)
    {
        $reflection = new \ReflectionMethod($class, $method);
        $reflection->setAccessible(true);

        return is_object($class)
            ? $reflection->invoke($class, ...$args)
            : $reflection->invoke(null, ...$args);
    }

    /**
     * A helper for setting the values of protected properties.
     *
     * @param class-string|object $class The name or and instance of the class that holds the property.
     * @param string              $prop  The property name.
     * @param mixed               $value The value to set.
     *
     * @return void
     */
    protected function setProtectedProperty($class, $prop, $value)
    {
        $reflection = new \ReflectionProperty($class, $prop);
        $reflection->setAccessible(true);

        is_object($class)
            ? $reflection->setValue($class, $value)
            : $reflection->setValue($value);
    }
}
