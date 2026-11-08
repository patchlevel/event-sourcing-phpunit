<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\PhpUnit\Test;

use Closure;
use Patchlevel\EventSourcing\Projection\Projection;
use PHPUnit\Framework\Attributes\After;
use PHPUnit\Framework\Attributes\Before;
use PHPUnit\Framework\TestCase;
use Throwable;

use function is_array;
use function is_object;

/** @experimental */
abstract class ProjectionTestCase extends TestCase
{
    use Expectations;

    /** @var array<object> */
    private array $givenEvents = [];

    private bool $whenCalled = false;
    private mixed $state = null;
    private bool $thenCalled = false;
    private mixed $expectedState = null;
    private Throwable|null $thrownException = null;

    /** @param object ...$events Events or messages that already happened. Events are tagged automatically. */
    final public function given(object ...$events): self
    {
        if ($this->whenCalled) {
            throw new WhenAlreadyCalled();
        }

        $this->givenEvents = $events;

        return $this;
    }

    /**
     * @param Projection<S>|array<array-key, Projection<*>> $projection The projection to read or several of them with one read, like Decision::read().
     *
     * @template S
     */
    final public function when(Projection|array $projection): self
    {
        if ($this->whenCalled) {
            throw new WhenAlreadyCalled();
        }

        $this->whenCalled = true;

        $store = new DecisionTestStore();
        $store->given(...$this->givenEvents);

        try {
            $this->state = $store->decisionFactory()->begin()->read($projection);
        } catch (Throwable $throwable) {
            $this->thrownException = $throwable;
        }

        return $this;
    }

    /** @param mixed|Closure(mixed): void $state The state you expect or a callback that receives the state. */
    final public function then(mixed $state): self
    {
        $this->thenCalled = true;
        $this->expectedState = $state;

        return $this;
    }

    #[After]
    final public function assert(): self
    {
        if (!$this->whenCalled) {
            throw new NoWhenProvided();
        }

        if ($this->thrownException !== null) {
            $this->handleException($this->thrownException);

            return $this;
        }

        $this->expectedExceptionWasNotThrown();

        if (!$this->thenCalled) {
            throw new NoThenProvided();
        }

        $expected = $this->expectedState;

        if ($expected instanceof Closure) {
            $this->runThenCallback($expected, $this->state);

            return $this;
        }

        if (is_object($expected) || is_array($expected)) {
            self::assertEquals($expected, $this->state, 'The state doesn\'t match the expected state.');

            return $this;
        }

        self::assertSame($expected, $this->state, 'The state doesn\'t match the expected state.');

        return $this;
    }

    #[Before]
    final public function reset(): void
    {
        $this->givenEvents = [];
        $this->whenCalled = false;
        $this->state = null;
        $this->thenCalled = false;
        $this->expectedState = null;
        $this->expectedException = null;
        $this->expectedExceptionMessage = null;
        $this->thrownException = null;
    }
}
