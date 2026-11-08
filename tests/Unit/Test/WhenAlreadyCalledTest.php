<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\PhpUnit\Tests\Unit\Test;

use Patchlevel\EventSourcing\PhpUnit\Test\WhenAlreadyCalled;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(WhenAlreadyCalled::class)]
final class WhenAlreadyCalledTest extends TestCase
{
    public function testException(): void
    {
        $exception = new WhenAlreadyCalled();
        self::assertSame(
            'when() was already called. It runs immediately, so call given() and concurrently() before it and call it only once per test.',
            $exception->getMessage(),
        );
    }
}
