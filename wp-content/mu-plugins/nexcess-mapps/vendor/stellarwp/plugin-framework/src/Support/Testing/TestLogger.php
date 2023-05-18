<?php

namespace StellarWP\PluginFramework\Support\Testing;

use StellarWP\PluginFramework\Services\Logger;

/**
 * An implementation of StellarWP\PluginFramework\Services\Logger that captures all logged
 * messages instead of printing them.
 */
class TestLogger extends Logger
{
    /**
     * Messages that have been logged through this logger.
     *
     * @var Array<mixed>
     */
    protected $messages = [];

    /**
     * Get all captured messages.
     *
     * @return Array<mixed>
     */
    public function getMessages()
    {
        return $this->messages;
    }

    /**
     * {@inheritDoc}
     *
     * Note that we're JSON-encoding the values in order to stay compatible with the parent method.
     */
    protected function formatLogMessage($level, $message, array $context = [])
    {
        return (string) wp_json_encode([
            'timestamp' => current_datetime()->format('r'),
            'level'     => $level,
            'message'   => $message,
            'context'   => $context,
        ]);
    }

    /**
     * {@inheritDoc}
     */
    protected function writeLogMessage($message, $level)
    {
        $this->messages[] = json_decode($message, true);
    }
}
