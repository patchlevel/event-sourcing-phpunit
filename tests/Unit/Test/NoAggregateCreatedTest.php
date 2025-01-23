<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\PhpUnit\Tests\Unit\Test;

use Patchlevel\EventSourcing\PhpUnit\Test\NoAggregateCreated;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(NoAggregateCreated::class)]
final class NoAggregateCreatedTest extends TestCase
{
    public function testException(): void
    {
        $exception = new NoAggregateCreated();
        self::assertSame(
            'No aggregate set and no aggregate returned. Please provide given events or create the aggregate with the first action.',
            $exception->getMessage(),
        );
    }
}
