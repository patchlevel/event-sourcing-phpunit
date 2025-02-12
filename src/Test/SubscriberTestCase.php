<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\PhpUnit\Test;

use Closure;
use Patchlevel\EventSourcing\Message\Message;
use Patchlevel\EventSourcing\PhpUnit\Doctrine\Middleware\QueryCount;
use Patchlevel\EventSourcing\Subscription\Engine\SubscriptionEngine;
use Patchlevel\EventSourcing\Subscription\Subscriber\MetadataSubscriberAccessor;
use Patchlevel\EventSourcing\Subscription\Subscriber\SubscriberAccessorRepository;
use PHPUnit\Framework\Attributes\After;
use PHPUnit\Framework\Attributes\Before;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

abstract class SubscriberTestCase extends KernelTestCase
{
    protected static KernelBrowser|null $client = null;

    /** @var array<object> */
    private array $givenEvents = [];
    /** @var array<object> */
    private array $whenEvents = [];
    /** @var array<Closure> */
    private array $thenClosures = [];
    private int|null $thenUpdatedTimes = null;

    /** @return class-string<object> */
    abstract protected function subscriberClass(): string;

    protected function setUp(): void
    {
        static::$kernel = static::createKernel();
        static::$kernel->boot();

        static::resetSubscriptions();
        static::ensureKernelShutdown();
    }

    protected static function resetSubscriptions(): void
    {
        $engine = static::getService(SubscriptionEngine::class);
        $engine->remove();
    }

    /**
     * @param class-string<T> $id
     *
     * @return T
     *
     * @template T
     */
    protected static function getService(string $id): mixed
    {
        return static::getContainer()->get($id);
    }

    public function given(object ...$events): self
    {
        $this->givenEvents = $events;

        return $this;
    }

    public function when(object ...$events): self
    {
        $this->whenEvents = $events;

        return $this;
    }

    public function then(object ...$closures): self
    {
        $this->thenClosures = $closures;

        return $this;
    }

    public function thenUpdatedTimes(int $updatedTimes): self
    {
        $this->thenUpdatedTimes = $updatedTimes;

        return $this;
    }

    public function thenNotUpdated(): self
    {
        $this->thenUpdatedTimes = 0;

        return $this;
    }

    #[After]
    public function assert(): void
    {
        /** @var SubscriberAccessorRepository $subscriberAccessorRepository */
        $subscriberAccessorRepository = self::getContainer()->get(SubscriberAccessorRepository::class);

        /** @var QueryCount $queryCount */
        $queryCount = self::getContainer()->get(QueryCount::class);
        //$queryCount->reset();

        /** @var MetadataSubscriberAccessor[] $subscriberAccessors */
        $subscriberAccessors = $subscriberAccessorRepository->all();
        $subscriberAccessor = null;

        // adjust for only one subscriber
        foreach ($subscriberAccessors as $subscriberAccessorEntry) {
            if ($subscriberAccessorEntry->subscriber()::class === $this->subscriberClass()) {
                $subscriberAccessor = $subscriberAccessorEntry;
            }
        }

        if ($subscriberAccessor === null) {
            throw new \RuntimeException('No subscriber accessor found');
        }

        $setupMethod = $subscriberAccessor->setupMethod();
        if ($setupMethod) {
            $setupMethod();
        }

        foreach ($this->givenEvents as $event) {
            foreach ($subscriberAccessor->subscribeMethods($event::class) as $subscribeMethod) {
                $subscribeMethod(Message::create($event));
            }
        }

        $calls = $queryCount->get();

        foreach ($this->whenEvents as $event) {
            foreach ($subscriberAccessor->subscribeMethods($event::class) as $subscribeMethod) {
                $subscribeMethod(Message::create($event));
            }
        }

        if ($this->thenUpdatedTimes !== null) {
            self::assertEquals($this->thenUpdatedTimes, $queryCount->get() - $calls);
        }

        foreach ($this->thenClosures as $closure) {
            $closure();
        }

        $teardownMethod = $subscriberAccessor->teardownMethod();
        if ($teardownMethod) {
            $teardownMethod();
        }
    }

    #[Before]
    public function reset(): void
    {
        $this->givenEvents = [];
        $this->whenEvents = [];
        $this->thenUpdatedTimes = null;
    }
}
