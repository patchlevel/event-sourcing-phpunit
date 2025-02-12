<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\PhpUnit\Test;

use Patchlevel\EventSourcing\Message\Message;
use Patchlevel\EventSourcing\Metadata\Subscriber\AttributeSubscriberMetadataFactory;
use Patchlevel\EventSourcing\Metadata\Subscriber\SubscriberMetadataFactory;
use Patchlevel\EventSourcing\Subscription\Subscriber\ArgumentResolver\ArgumentResolver;
use Patchlevel\EventSourcing\Subscription\Subscriber\MetadataSubscriberAccessorRepository;
use Patchlevel\EventSourcing\Subscription\Subscriber\SubscriberAccessorRepository;

final class SubscriberUtilities
{
    private SubscriberAccessorRepository $subscriberAccessorRepository;

    /**
     * @param object|array<object> $subscribers
     * @param SubscriberMetadataFactory $metadataFactory
     * @param iterable<ArgumentResolver> $argumentResolvers
     */
    public function __construct(
        object|array $subscribers,
        SubscriberMetadataFactory $metadataFactory = new AttributeSubscriberMetadataFactory(),
        iterable $argumentResolvers = [],
    )
    {
        $this->subscriberAccessorRepository = new MetadataSubscriberAccessorRepository(
            is_array($subscribers) ? $subscribers : [$subscribers],
            $metadataFactory,
            $argumentResolvers
        );
    }

    public function executeSetup(): self
    {
        $subscriberAccessors = $this->subscriberAccessorRepository->all();

        foreach ($subscriberAccessors as $subscriberAccessor) {
            $setupMethod = $subscriberAccessor->setupMethod();

            if (!$setupMethod) {
                continue;
            }

            $setupMethod();
        }

        return $this;
    }

    public function executeRun(object ...$events): self
    {
        $subscriberAccessors = $this->subscriberAccessorRepository->all();

        foreach ($events as $event) {
            foreach ($subscriberAccessors as $subscriberAccessor) {
                foreach ($subscriberAccessor->subscribeMethods($event::class) as $subscribeMethod) {
                    $subscribeMethod(Message::create($event));
                }
            }
        }

        return $this;
    }

    public function executeTeardown(): self
    {
        $subscriberAccessors = $this->subscriberAccessorRepository->all();

        foreach ($subscriberAccessors as $subscriberAccessor) {
            $teardownMethod = $subscriberAccessor->teardownMethod();

            if (!$teardownMethod) {
                continue;
            }

            $teardownMethod();
        }

        return $this;
    }
}
