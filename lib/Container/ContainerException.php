<?php
declare(strict_types=1);

namespace Furgo\Sitechips\Core\Container;

use Psr\Container\ContainerExceptionInterface;

class ContainerException extends \InvalidArgumentException implements ContainerExceptionInterface
{
}