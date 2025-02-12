<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\PhpUnit\Doctrine\Middleware;

use Doctrine\DBAL\Driver;
use Doctrine\DBAL\Driver\Connection as DriverConnection;
use Doctrine\DBAL\Driver\Middleware\AbstractDriverMiddleware;
use SensitiveParameter;

final class CountQueriesDriverMiddleware extends AbstractDriverMiddleware
{
    public function __construct(Driver $wrappedDriver, private QueryCount $queryCount)
    {
        parent::__construct($wrappedDriver);
    }

    public function connect(#[SensitiveParameter] array $params): DriverConnection
    {
        return new CountQueriesConnectionMiddleware(parent::connect($params), $this->queryCount);
    }
}