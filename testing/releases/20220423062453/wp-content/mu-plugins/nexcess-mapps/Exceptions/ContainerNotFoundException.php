<?php

/**
 * Indicates that an entry could not be returned from the container.
 *
 * Once the minimum version of PHP is >= 7.2, this should implement
 * Psr\Container\NotFoundExceptionInterface.
 *
 * @link https://www.php-fig.org/psr/psr-11/
 */

namespace Nexcess\MAPPS\Exceptions;

class ContainerNotFoundException extends \Exception {

}
