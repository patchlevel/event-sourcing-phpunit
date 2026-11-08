<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\PhpUnit\Test;

use Closure;
use PHPUnit\Framework\Constraint\Exception as ExceptionConstraint;
use PHPUnit\Framework\Constraint\ExceptionMessageIsOrContains;
use ReflectionFunction;
use Throwable;

use function sprintf;

/** @internal */
trait Expectations
{
    /** @var class-string<Throwable>|null */
    private string|null $expectedException = null;
    private string|null $expectedExceptionMessage = null;

    /** @param class-string<Throwable> $exception */
    final public function expectsException(string $exception): self
    {
        $this->expectedException = $exception;

        return $this;
    }

    final public function expectsExceptionMessage(string $exceptionMessage): self
    {
        $this->expectedExceptionMessage = $exceptionMessage;

        return $this;
    }

    private function handleException(Throwable $throwable): void
    {
        $checked = false;

        if ($this->expectedException) {
            self::assertThat($throwable, new ExceptionConstraint($this->expectedException));
            $checked = true;
        }

        if ($this->expectedExceptionMessage) {
            self::assertThat($throwable->getMessage(), new ExceptionMessageIsOrContains($this->expectedExceptionMessage));
            $checked = true;
        }

        if (!$checked) {
            throw $throwable;
        }
    }

    private function expectedExceptionWasNotThrown(): void
    {
        if ($this->expectedException !== null) {
            self::assertThat(null, new ExceptionConstraint($this->expectedException));
        }

        if ($this->expectedExceptionMessage === null) {
            return;
        }

        self::assertThat(
            null,
            new ExceptionMessageIsOrContains($this->expectedExceptionMessage),
            sprintf(
                'Failed asserting that exception with message "%s" is thrown',
                $this->expectedExceptionMessage,
            ),
        );
    }

    private function runThenCallback(Closure $callback, mixed $subject): void
    {
        try {
            $callback($subject);
        } catch (Throwable $t) {
            $reflection = new ReflectionFunction($callback);

            self::fail(
                sprintf(
                    'then() callback defined in %s on line %d failed: %s',
                    $reflection->getFileName(),
                    $reflection->getStartLine(),
                    $t->getMessage(),
                ),
            );
        }
    }
}
