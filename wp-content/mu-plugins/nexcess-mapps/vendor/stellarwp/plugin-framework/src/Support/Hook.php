<?php

namespace StellarWP\PluginFramework\Support;

use WP_Hook;

/**
 * A decorator for the WP_Hook class with methods to filter hooks.
 *
 * @phpstan-type HookCallbackArray Array<string,array{function: callable, accepted_args: int, priority: int}>
 */
class Hook
{
    /**
     * The underlying WP_Hook object.
     *
     * @var WP_Hook
     */
    public $wpHook;

    /**
     * Construct a new Hook instance from an existing WP_Hook.
     *
     * @param ?WP_Hook $hook Optional. The WP_Hook object, or NULL to create a fresh instance.
     *                       Default is null.
     */
    public function __construct(WP_Hook $hook = null)
    {
        $this->wpHook = $hook ?: new WP_Hook();
    }

    /**
     * Proxy unknown methods to the underlying WP_Hook object.
     *
     * @param string       $name The method name.
     * @param Array<mixed> $args Method arguments.
     *
     * @throws \BadMethodCallException If no matching method can be found.
     *
     * @return mixed The return value of the underlying WP_Hook method.
     */
    public function __call($name, array $args)
    {
        if (method_exists($this->wpHook, $name)) {
            return $this->wpHook->$name(...$args);
        }

        // Attempt to convert the method name into snake_case.
        $snake = Str::snake($name);
        if (method_exists($this->wpHook, $snake)) {
            return $this->wpHook->$snake(...$args);
        }

        throw new \BadMethodCallException(sprintf('Call to undefined method %s::%s', static::class, $name));
    }

    /**
     * Flatten the array of callbacks into a simplified structure.
     *
     * Before:
     *
     *     [
     *         10 => [
     *             'first_callback'  => [
     *                 'function'      => 'some_function',
     *                 'accepted_args' => 2,
     *             ],
     *             'second_callback' => [
     *                 'function'      => [ 'SomeClass', someMethod' ],
     *                 'accepted_args' => 1,
     *             ],
     *         ],
     *     ]
     *
     * After:
     *
     *     [
     *         'first_callback'  => [
     *             'function'      => 'some_function',
     *             'accepted_args' => 2,
     *             'priority'      => 10,
     *         ],
     *         'second_callback' => [
     *             'function'      => [ 'SomeClass', someMethod' ],
     *             'accepted_args' => 1,
     *             'priority'      => 10,
     *         ],
     *     ]
     *
     * @return HookCallbackArray The flattened array of callbacks.
     */
    public function flatten()
    {
        $flattened = [];

        foreach ($this->wpHook->callbacks as $priority => $callbacks) {
            foreach ($callbacks as $key => $callback) {
                /**
                 * @var int                                           $priority
                 * @var string                                        $key
                 * @var array{function: callable, accepted_args: int} $callback
                 */
                $flattened[$key] = [
                    'function'      => $callback['function'],
                    'accepted_args' => (int) $callback['accepted_args'],
                    'priority'      => (int) $priority,
                ];
            }
        }

        return $flattened;
    }
}
