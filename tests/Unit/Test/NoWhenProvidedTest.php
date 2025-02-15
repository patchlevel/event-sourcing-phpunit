<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\PhpUnit\Tests\Unit\Test;

use Patchlevel\EventSourcing\PhpUnit\Test\NoWhenProvided;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(NoWhenProvided::class)]
final class NoWhenProvidedTest extends TestCase
{
    public function testException(): void
    {
        $exception = new NoWhenProvided();
        self::assertSame(
            'No when was specified. Please provide what should happen otherwise no real testing is done.',
            $exception->getMessage(),
        );
    }
}
