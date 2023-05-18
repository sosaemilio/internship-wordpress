<?php

/**
 * Thrown when an immutable value is attempted to be changed.
 */

namespace StellarWP\PluginFramework\Exceptions;

class ImmutableValueException extends \RuntimeException implements StellarWPException
{
}
