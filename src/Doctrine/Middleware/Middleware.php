<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\PhpUnit\Doctrine\Middleware;

use Doctrine\DBAL\Driver;
use Doctrine\DBAL\Driver\Middleware as MiddlewareInterface;

final class Middleware implements MiddlewareInterface
{
    public function __construct(private QueryCount $queryCount)
    {
    }

    public function wrap(Driver $driver): Driver
    {
        return new CountQueriesDriverMiddleware($driver, $this->queryCount);
    }
}
