<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\PhpUnit\Test;

use Closure;
use Patchlevel\EventSourcing\Aggregate\AggregateRoot;
use PHPUnit\Framework\Attributes\After;
use PHPUnit\Framework\Attributes\Before;
use PHPUnit\Framework\Constraint\Exception as ExceptionConstraint;
use PHPUnit\Framework\Constraint\ExceptionMessageIsOrContains;
use PHPUnit\Framework\TestCase;
use Throwable;

abstract class AggregateRootTestCase extends TestCase
{
    /** @var array<object> */
    private array $givenEvents = [];
    private Closure|null $when = null;

    /** @var array<object> */
    private array $expectedEvents = [];
    /** @var class-string<Throwable>|null  */
    private string|null $expectedException = null;
    private string|null $expectedExceptionMessage = null;

    /** @return class-string<AggregateRoot> */
    abstract protected function aggregateClass(): string;

    final public function given(object ...$events): self
    {
        $this->givenEvents = $events;

        return $this;
    }

    final public function when(Closure $callable): self
    {
        $this->when = $callable;

        return $this;
    }

    final public function then(object ...$events): self
    {
        $this->expectedEvents = $events;

        return $this;
    }

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

    #[After]
    final public function assert(): self
    {
        if ($this->when === null) {
            throw new NoWhenProvided();
        }

        $aggregate = null;

        if ($this->givenEvents) {
            $aggregate = $this->aggregateClass()::createFromEvents($this->givenEvents);
        }

        try {
            $return = ($this->when)($aggregate);

            if ($aggregate !== null && $return instanceof AggregateRoot) {
                throw new AggregateAlreadySet();
            }

            if ($aggregate === null) {
                $aggregate = $return;
            }
        } catch (Throwable $throwable) {
            $this->handleException($throwable);
        }

        if (!$aggregate instanceof AggregateRoot) {
            throw new NoAggregateCreated();
        }

        $events = $aggregate->releaseEvents();

        self::assertEquals($this->expectedEvents, $events, 'The events doesn\'t match the expected events.');

        return $this;
    }

    #[Before]
    final public function reset(): void
    {
        $this->givenEvents = [];
        $this->when = null;
        $this->expectedEvents = [];
        $this->expectedException = null;
        $this->expectedExceptionMessage = null;
    }

    private function handleException(Throwable $throwable): void
    {
        $checked = false;

        if ($this->expectedException) {
            self::assertThat(
                $throwable,
                new ExceptionConstraint(
                    $this->expectedException,
                ),
            );
            $checked = true;
        }

        if ($this->expectedExceptionMessage) {
            self::assertThat(
                $throwable->getMessage(),
                new ExceptionMessageIsOrContains(
                    $this->expectedExceptionMessage,
                ),
            );
            $checked = true;
        }

        if (!$checked) {
            throw $throwable;
        }
    }
}
