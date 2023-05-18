<?php

namespace StellarWP\PluginFramework\Exceptions;

/**
 * Thrown when attempting to work with an invalid WordPress drop-in file.
 *
 * Available drop-ins are defined in {@see _get_dropins()}.
 */
class InvalidDropInException extends DropInException
{
}
