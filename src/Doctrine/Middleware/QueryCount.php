<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\PhpUnit\Doctrine\Middleware;

final class QueryCount
{
    private int $count = 0;

    public function increase(): void
    {
        $this->count++;
    }

    public function get(): int
    {
        return $this->count;
    }

    public function reset(): void
    {
        $this->count = 0;
    }
}