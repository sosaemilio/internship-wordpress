<?php

namespace StellarWP\PluginFramework\Support;

/**
 * Capture output buffer, regardless of any errors that might occur.
 */
class Buffer
{
    /**
     * The callable being buffered.
     *
     * @var callable
     */
    protected $callable;

    /**
     * An exception object that may have been caught during buffer execution.
     *
     * @var ?\Exception
     */
    protected $error;

    /**
     * Whether or not the output buffer has been started.
     *
     * @var bool
     */
    protected $isBuffering = false;

    /**
     * Whether or not the callable has been executed.
     *
     * @var bool
     */
    protected $hasExecuted = false;

    /**
     * The captured output buffer of the callable execution.
     *
     * @var string
     */
    protected $output = '';

    /**
     * Construct a new Buffer.
     *
     * @param callable $callable The function to execute within the buffer.
     */
    public function __construct(callable $callable)
    {
        $this->callable = $callable;
    }

    /**
     * Control the formatting when a buffer is dumped (e.g. `var_dump($buffer)`).
     *
     * @return Array<string,mixed>
     */
    public function __debugInfo()
    {
        $callable = $this->callable instanceof \Closure
            ? (new \ReflectionFunction($this->callable))->__toString()
            : $this->callable;

        return [
            'callable'    => $callable,
            'error'       => $this->error,
            'isBuffering' => $this->isBuffering,
            'hasExecuted' => $this->hasExecuted,
            'output'      => $this->output,
        ];
    }

    /**
     * Get the caught exception, if one exists.
     *
     * If the callable has not yet been executed, it will implicitly be run.
     *
     * @return ?\Exception The captured exception if one exists, otherwise null.
     */
    public function getError()
    {
        return $this->run()->error;
    }

    /**
     * Get the contents of the buffered output.
     *
     * If the callable has not yet been executed, it will implicitly be run.
     *
     * @return string The buffered output.
     */
    public function getOutput()
    {
        return $this->run()->output;
    }

    /**
     * Determine whether or not the buffered callback produced an exception.
     *
     * If the callable has not yet been executed, it will implicitly be run.
     *
     * @return bool True if an exception was caught, false otherwise.
     */
    public function hasError()
    {
        return null !== $this->run()->error;
    }

    /**
     * Reset the buffer, enabling the callable to be called again.
     *
     * @return self
     */
    public function reset()
    {
        $this->hasExecuted = false;
        $this->error       = null;
        $this->output      = '';

        return $this;
    }

    /**
     * Execute the buffered callable.
     *
     * @param Array<mixed> ...$args Arguments to pass to the callable.
     *
     * @return self
     */
    public function run(...$args)
    {
        // If the callable has already been run, return early.
        if ($this->hasExecuted) {
            return $this;
        }

        $this->startBuffering();

        try {
            call_user_func_array($this->callable, $args);
        } catch (\Exception $e) {
            $this->error = $e;
        }

        $this->output = (string) ob_get_contents();
        $this->stopBuffering();
        $this->hasExecuted = true;

        return $this;
    }

    /**
     * Start the output buffering.
     *
     * @return void
     */
    protected function startBuffering()
    {
        $this->isBuffering = ob_start();
    }

    /**
     * Stop the output buffering.
     *
     * @return void
     */
    protected function stopBuffering()
    {
        if ($this->isBuffering) {
            $this->isBuffering = ! ob_end_clean();
        }
    }
}
