<?php

/**
 * Indicates that an error occurred while trying to resolve a dependency from the container.
 *
 * Once the minimum version of PHP is >= 7.2, this should implement
 * Psr\Container\ContainerExceptionInterface.
 *
 * @link https://www.php-fig.org/psr/psr-11/
 */

namespace Nexcess\MAPPS\Exceptions;

class ContainerException extends \Exception {

}
