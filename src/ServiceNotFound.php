<?php

namespace Weebel\Container;

use Psr\Container\NotFoundExceptionInterface;

class ServiceNotFound extends \Exception implements NotFoundExceptionInterface
{
}
