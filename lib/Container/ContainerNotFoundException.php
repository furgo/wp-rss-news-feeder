<?php
declare(strict_types=1);

namespace Furgo\Sitechips\Core\Container;

use Psr\Container\NotFoundExceptionInterface;

class ContainerNotFoundException extends \InvalidArgumentException implements NotFoundExceptionInterface
{
}