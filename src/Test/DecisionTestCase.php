<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\PhpUnit\Test;

use Closure;
use Patchlevel\EventSourcing\CommandBus\ServiceHandlerProvider;
use Patchlevel\EventSourcing\CommandBus\SyncCommandBus;
use Patchlevel\EventSourcing\DecisionModel\Decision;
use Patchlevel\EventSourcing\DecisionModel\DecisionFactory;
use Patchlevel\EventSourcing\Message\Message;
use Patchlevel\EventSourcing\Store\AppendConditionNotMet;
use PHPUnit\Framework\Attributes\After;
use PHPUnit\Framework\Attributes\Before;
use PHPUnit\Framework\TestCase;
use Throwable;

use function array_filter;
use function array_map;
use function array_values;

/** @experimental */
abstract class DecisionTestCase extends TestCase
{
    use Expectations;

    private DecisionTestStore|null $store = null;

    /** @var array<object> */
    private array $givenEvents = [];

    /** @var array<object> */
    private array $concurrentEvents = [];
    private object|null $when = null;

    /** @var array<object|Closure> */
    private array $thenExpectations = [];

    private Throwable|null $thrownException = null;

    /**
     * The services with the #[Handle] methods that are called when a command is passed to when().
     *
     * @return iterable<object>
     */
    protected function handlers(): iterable
    {
        return [];
    }

    /** @param object ...$events Events or messages that already happened. Events are tagged automatically. */
    final public function given(object ...$events): self
    {
        if ($this->when !== null) {
            throw new WhenAlreadyCalled();
        }

        $this->givenEvents = $events;

        return $this;
    }

    /** @param object ...$events Events or messages another process appends after the decision has read its state. */
    final public function concurrently(object ...$events): self
    {
        if ($this->when !== null) {
            throw new WhenAlreadyCalled();
        }

        $this->concurrentEvents = $events;

        return $this;
    }

    /**
     * @param object|Closure(Decision): mixed $commandOrCallable A command that is dispatched to the handlers or a closure that receives a new decision.
     * @param mixed                           ...$parameters     Passed to the handler parameters that are not a decision.
     */
    final public function when(object $commandOrCallable, mixed ...$parameters): self
    {
        if ($this->when !== null) {
            throw new WhenAlreadyCalled();
        }

        $this->when = $commandOrCallable;

        $store = $this->store();
        $store->given(...$this->givenEvents);
        $store->concurrently(...$this->concurrentEvents);

        try {
            if ($commandOrCallable instanceof Closure) {
                $commandOrCallable($store->decisionFactory()->begin());
            } else {
                $this->dispatch($commandOrCallable, $parameters);
            }
        } catch (Throwable $throwable) {
            $this->thrownException = $throwable;
        }

        return $this;
    }

    /**
     * @param object|Closure(Decision): void ...$expectations Events you expect to be appended and/or callbacks.
     *                                                        Callbacks receive a new decision to read the state after the append.
     */
    final public function then(object ...$expectations): self
    {
        $this->thenExpectations = $expectations;

        return $this;
    }

    /** Expects that the append was rejected because something the decision has read changed in the meantime. */
    final public function expectsConflict(): self
    {
        return $this->expectsException(AppendConditionNotMet::class);
    }

    /**
     * Use it for handlers that get the factory injected instead of a decision per command.
     * All decisions of this factory work on the store of this test.
     */
    final public function decisionFactory(): DecisionFactory
    {
        return $this->store()->decisionFactory();
    }

    /**
     * The messages with all their headers, like tags and stream name, the code under test has appended.
     *
     * @return list<Message>
     */
    final public function appendedMessages(): array
    {
        return $this->store()->appendedMessages();
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

        $expectedEvents = array_values(array_filter($this->thenExpectations, static fn (object $item) => !$item instanceof Closure));
        $expectationCallbacks = array_filter($this->thenExpectations, static fn (object $item) => $item instanceof Closure);

        $events = array_map(static fn (Message $message) => $message->event(), $this->appendedMessages());

        self::assertEquals($expectedEvents, $events, 'The appended events doesn\'t match the expected events.');

        foreach ($expectationCallbacks as $callback) {
            $this->runThenCallback($callback, $this->decisionFactory()->begin());
        }

        return $this;
    }

    #[Before]
    final public function reset(): void
    {
        $this->store = null;
        $this->givenEvents = [];
        $this->concurrentEvents = [];
        $this->when = null;
        $this->thenExpectations = [];
        $this->expectedException = null;
        $this->expectedExceptionMessage = null;
        $this->thrownException = null;
    }

    /** @param array<mixed> $parameters */
    private function dispatch(object $command, array $parameters): void
    {
        $commandBus = new SyncCommandBus(
            new ServiceHandlerProvider(
                $this->handlers(),
                new HandlerParameterResolver($this->decisionFactory(), $parameters),
            ),
        );

        $commandBus->dispatch($command);
    }

    private function store(): DecisionTestStore
    {
        return $this->store ??= new DecisionTestStore();
    }
}
