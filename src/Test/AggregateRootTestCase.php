<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\PhpUnit\Test;

use Closure;
use Patchlevel\EventSourcing\Aggregate\AggregateRoot;
use Patchlevel\EventSourcing\CommandBus\HandlerFinder;
use PHPUnit\Framework\Attributes\After;
use PHPUnit\Framework\Attributes\Before;
use PHPUnit\Framework\Constraint\Exception as ExceptionConstraint;
use PHPUnit\Framework\Constraint\ExceptionMessageIsOrContains;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionFunction;
use Throwable;

use function array_filter;
use function array_values;
use function sprintf;

abstract class AggregateRootTestCase extends TestCase
{
    /** @var array<object> */
    private array $givenEvents = [];
    private object|null $when = null;

    /** @var array<mixed> */
    private array $parameters = [];
    private object|null $aggregate = null;

    /** @var array<object|Closure> */
    private array $thenExpectations = [];

    private Throwable|null $thrownException = null;
    /** @var class-string<Throwable>|null */
    private string|null $expectedException = null;
    private string|null $expectedExceptionMessage = null;

    /** @return class-string<AggregateRoot> */
    abstract protected function aggregateClass(): string;

    final public function given(object ...$events): self
    {
        $this->aggregate = null;
        $this->givenEvents = $events;

        return $this;
    }

    /** @param object|Closure $callable */
    final public function when(object $callable, mixed ...$parameters): self
    {
        $this->when = $callable;
        $this->parameters = $parameters;

        if ($this->givenEvents) {
            $this->aggregate = $this->aggregateClass()::createFromEvents($this->givenEvents);
        }

        try {
            $callableOrCommand = $this->when;
            $return = null;

            if ($callableOrCommand instanceof Closure) {
                /** @var AggregateRoot|null $return */
                $return = $callableOrCommand($this->aggregate);
            } else {
                foreach (HandlerFinder::findInClass($this->aggregateClass()) as $handler) {
                    if (!$callableOrCommand instanceof $handler->commandClass) {
                        continue;
                    }

                    $reflection = new ReflectionClass($this->aggregateClass());
                    $reflectionMethod = $reflection->getMethod($handler->method);

                    /** @var AggregateRoot|null $return */
                    $return = $reflectionMethod->invokeArgs($handler->static ? null : $this->aggregate, [$callableOrCommand, ...$this->parameters]);
                }
            }

            if ($this->aggregate !== null && $return instanceof AggregateRoot) {
                throw new AggregateAlreadySet();
            }

            if ($this->aggregate === null) {
                $this->aggregate = $return;
            }
        } catch (Throwable $throwable) {
            $this->thrownException = $throwable;
        }

        return $this;
    }

    /**
     * @param object|Closure(object): void ...$events Expected events and/or callbacks for aggregate state assertions.
     *                                                Closures receive the aggregate instance and are executed after event assertions.
     *                                                Event order is preserved regardless of callback placement.
     */
    final public function then(object ...$events): self
    {
        $this->thenExpectations = $events;

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

        if ($this->thrownException !== null) {
            $this->handleException($this->thrownException);

            return $this;
        }

        $this->expectedExceptionWasNotThrown();

        if (!$this->aggregate instanceof AggregateRoot) {
            throw new NoAggregateCreated();
        }

        $expectedEvents = array_values(array_filter($this->thenExpectations, static fn (object $item) => !$item instanceof Closure));
        $expectationCallbacks = array_filter($this->thenExpectations, static fn (object $item) => $item instanceof Closure);

        $events = $this->aggregate->releaseEvents();

        self::assertEquals($expectedEvents, $events, 'The events doesn\'t match the expected events.');

        foreach ($expectationCallbacks as $callback) {
            try {
                $callback($this->aggregate);
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

        return $this;
    }

    #[Before]
    final public function reset(): void
    {
        $this->givenEvents = [];
        $this->when = null;
        $this->parameters = [];
        $this->thenExpectations = [];
        $this->expectedException = null;
        $this->expectedExceptionMessage = null;
        $this->aggregate = null;
        $this->thrownException = null;
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
}
