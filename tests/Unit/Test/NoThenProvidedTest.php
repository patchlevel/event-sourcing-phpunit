<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\PhpUnit\Tests\Unit\Test;

use Patchlevel\EventSourcing\PhpUnit\Test\NoThenProvided;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(NoThenProvided::class)]
final class NoThenProvidedTest extends TestCase
{
    public function testException(): void
    {
        $exception = new NoThenProvided();
        self::assertSame(
            'No then was specified. Please provide what you expect otherwise no real testing is done.',
            $exception->getMessage(),
        );
    }
}
