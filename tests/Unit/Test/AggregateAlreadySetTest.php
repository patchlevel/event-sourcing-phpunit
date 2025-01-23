<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\PhpUnit\Tests\Unit\Test;

use Patchlevel\EventSourcing\PhpUnit\Test\AggregateAlreadySet;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(AggregateAlreadySet::class)]
final class AggregateAlreadySetTest extends TestCase
{
    public function testException(): void
    {
        $exception = new AggregateAlreadySet();
        self::assertSame(
            'Aggregate already set. You should only return the aggregate if there is no given present.',
            $exception->getMessage(),
        );
    }
}
