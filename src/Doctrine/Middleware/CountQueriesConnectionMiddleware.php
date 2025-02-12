<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\PhpUnit\Doctrine\Middleware;

use Doctrine\DBAL\Driver\Connection;
use Doctrine\DBAL\Driver\Middleware\AbstractConnectionMiddleware;
use Doctrine\DBAL\Driver\Result;
use Doctrine\DBAL\Driver\Statement;

final class CountQueriesConnectionMiddleware extends AbstractConnectionMiddleware
{
    public function __construct(Connection $wrappedConnection, private QueryCount $queryCount)
    {
        parent::__construct($wrappedConnection);
    }

    public function exec(string $sql): int|string
    {
        $this->queryCount->increase();

        return parent::exec($sql);
    }

    public function query(string $sql): Result
    {
        $this->queryCount->increase();

        return parent::query($sql);
    }

    public function prepare(string $sql): Statement
    {
        $this->queryCount->increase();

        return parent::prepare($sql);
    }
}
