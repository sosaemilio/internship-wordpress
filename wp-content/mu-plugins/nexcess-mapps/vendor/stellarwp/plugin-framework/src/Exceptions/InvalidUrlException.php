<?php

namespace StellarWP\PluginFramework\Exceptions;

/**
 * Thrown when given a URL that cannot be parsed.
 */
class InvalidUrlException extends \DomainException implements StellarWPException
{
}
