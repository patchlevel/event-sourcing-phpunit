<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\PhpUnit\Test;

use Patchlevel\EventSourcing\Message\Message;
use Patchlevel\EventSourcing\Subscription\Subscriber\MetadataSubscriberAccessor;
use Patchlevel\EventSourcing\Subscription\Subscriber\MetadataSubscriberAccessorRepository;
use PHPUnit\Framework\Attributes\Before;

trait SubscriberUtilities
{
    /** @var array<object> */
    private array $givenEvents = [];
    /**  @var iterable<MetadataSubscriberAccessor<object>>|null */
    private iterable|null $subscriberAccessors;

    public function given(object ...$events): self
    {
        $this->givenEvents = $events;

        return $this;
    }

    public function executeSetup(object ...$subscribers): self
    {
        $subscriberAccessors = $this->createSubscriberAccessors($subscribers);

        foreach ($subscriberAccessors as $subscriberAccessor) {
            $setupMethod = $subscriberAccessor->setupMethod();

            if (!$setupMethod) {
                continue;
            }

            $setupMethod();
        }

        return $this;
    }

    public function executeRun(object ...$subscribers): self
    {
        $subscriberAccessors = $this->createSubscriberAccessors($subscribers);

        foreach ($this->givenEvents as $event) {
            foreach ($subscriberAccessors as $subscriberAccessor) {
                foreach ($subscriberAccessor->subscribeMethods($event::class) as $subscribeMethod) {
                    $subscribeMethod(Message::create($event));
                }
            }
        }

        return $this;
    }

    public function executeTeardown(object ...$subscribers): self
    {
        $subscriberAccessors = $this->createSubscriberAccessors($subscribers);

        foreach ($subscriberAccessors as $subscriberAccessor) {
            $teardownMethod = $subscriberAccessor->teardownMethod();

            if (!$teardownMethod) {
                continue;
            }

            $teardownMethod();
        }

        return $this;
    }

    #[Before]
    public function reset(): void
    {
        $this->givenEvents = [];
        $this->subscriberAccessors = null;
    }

    /**
     * @param array<object> $subscribers
     *
     * @return iterable<MetadataSubscriberAccessor<object>>
     */
    private function createSubscriberAccessors(array $subscribers): iterable
    {
        return $this->subscriberAccessors ??= (new MetadataSubscriberAccessorRepository($subscribers))->all();
    }
}
