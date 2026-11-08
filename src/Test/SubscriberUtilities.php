<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\PhpUnit\Test;

use Patchlevel\EventSourcing\Attribute\BatchState;
use Patchlevel\EventSourcing\Message\Message;
use Patchlevel\EventSourcing\Metadata\Subscriber\ArgumentMetadata;
use Patchlevel\EventSourcing\Metadata\Subscriber\AttributeSubscriberMetadataFactory;
use Patchlevel\EventSourcing\Metadata\Subscriber\SubscribeMethodMetadata;
use Patchlevel\EventSourcing\Metadata\Subscriber\SubscriberMetadataFactory;
use Patchlevel\EventSourcing\Subscription\Status;
use Patchlevel\EventSourcing\Subscription\Subscriber\ArgumentResolver\ArgumentResolver;
use Patchlevel\EventSourcing\Subscription\Subscriber\ArgumentResolver\ArgumentResolverContext;
use Patchlevel\EventSourcing\Subscription\Subscriber\ArgumentResolver\EventArgumentResolver;
use Patchlevel\EventSourcing\Subscription\Subscriber\ArgumentResolver\MessageArgumentResolver;
use Patchlevel\EventSourcing\Subscription\Subscriber\ArgumentResolver\PartitionKeyArgumentResolver;
use Patchlevel\EventSourcing\Subscription\Subscriber\ArgumentResolver\RecordedOnArgumentResolver;
use Patchlevel\EventSourcing\Subscription\Subscriber\MetadataSubscriberAccessor;
use Patchlevel\EventSourcing\Subscription\Subscriber\MetadataSubscriberAccessorRepository;
use Patchlevel\EventSourcing\Subscription\Subscriber\NoSuitableResolver;
use Patchlevel\EventSourcing\Subscription\Subscriber\SubscriberAccessorRepository;
use Patchlevel\EventSourcing\Subscription\Subscription;
use Throwable;

use function array_map;
use function is_array;

final class SubscriberUtilities
{
    private SubscriberAccessorRepository $subscriberAccessorRepository;

    /** @var array<ArgumentResolver> */
    private array $argumentResolvers;

    /** @var array<string, SubscriberBatch> */
    private array $batches = [];

    /**
     * @param object|array<object>       $subscribers
     * @param iterable<ArgumentResolver> $argumentResolvers
     */
    public function __construct(
        object|array $subscribers,
        SubscriberMetadataFactory $metadataFactory = new AttributeSubscriberMetadataFactory(),
        iterable $argumentResolvers = [],
    ) {
        $this->subscriberAccessorRepository = new MetadataSubscriberAccessorRepository(
            is_array($subscribers) ? $subscribers : [$subscribers],
            $metadataFactory,
        );

        $this->argumentResolvers = [
            ...$argumentResolvers,
            new PartitionKeyArgumentResolver(),
            new MessageArgumentResolver(),
            new EventArgumentResolver(),
            new RecordedOnArgumentResolver(),
        ];
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
            $message = $event instanceof Message ? $event : Message::create($event);

            foreach ($subscriberAccessors as $subscriberAccessor) {
                $this->process($subscriberAccessor, $message);
            }
        }

        foreach ($this->batches as $subscriptionId => $batch) {
            unset($this->batches[$subscriptionId]);
            $batch->flush();
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

    /** @param MetadataSubscriberAccessor<object> $subscriberAccessor */
    private function process(MetadataSubscriberAccessor $subscriberAccessor, Message $message): void
    {
        $subscribeMethods = $subscriberAccessor->subscribeMethods($message->event()::class);

        if ($subscribeMethods === []) {
            return;
        }

        $subscription = $this->subscription($subscriberAccessor, $message);
        $subscriptionId = $subscription->id();
        $batch = $this->batch($subscriberAccessor, $subscriptionId);
        $context = new ArgumentResolverContext($message, $subscription, $subscriberAccessor->metadata());

        try {
            foreach ($subscribeMethods as $subscribeMethod) {
                $subscriberAccessor->subscriber()->{$subscribeMethod->name}(
                    ...array_map(
                        fn (ArgumentMetadata $argument): mixed => $this->resolveArgument(
                            $subscriberAccessor,
                            $subscribeMethod,
                            $argument,
                            $context,
                            $batch,
                        ),
                        $subscribeMethod->arguments,
                    ),
                );
            }
        } catch (Throwable $exception) {
            unset($this->batches[$subscriptionId]);
            $batch?->rollback();

            throw $exception;
        }

        if ($batch === null || !$batch->messageProcessed()) {
            return;
        }

        unset($this->batches[$subscriptionId]);
        $batch->flush();
    }

    /** @param MetadataSubscriberAccessor<object> $subscriberAccessor */
    private function subscription(MetadataSubscriberAccessor $subscriberAccessor, Message $message): Subscription
    {
        $metadata = $subscriberAccessor->metadata();
        $partitioner = $subscriberAccessor->partitioner();

        if ($partitioner === null) {
            return new Subscription($metadata->id, $metadata->group, $metadata->runMode, Status::Active);
        }

        $partitionKey = $partitioner($message);

        return new Subscription(
            Subscription::partitionId($metadata->id, $partitionKey),
            $metadata->group,
            $metadata->runMode,
            Status::Active,
            partitionKey: $partitionKey,
        );
    }

    /** @param MetadataSubscriberAccessor<object> $subscriberAccessor */
    private function batch(MetadataSubscriberAccessor $subscriberAccessor, string $subscriptionId): SubscriberBatch|null
    {
        $batchMetadata = $subscriberAccessor->metadata()->batch;

        if ($batchMetadata === null) {
            return null;
        }

        return $this->batches[$subscriptionId] ??= SubscriberBatch::begin(
            $subscriberAccessor->subscriber(),
            $batchMetadata,
        );
    }

    /** @param MetadataSubscriberAccessor<object> $subscriberAccessor */
    private function resolveArgument(
        MetadataSubscriberAccessor $subscriberAccessor,
        SubscribeMethodMetadata $subscribeMethod,
        ArgumentMetadata $argument,
        ArgumentResolverContext $context,
        SubscriberBatch|null $batch,
    ): mixed {
        if ($batch !== null && $argument->hasAttribute(BatchState::class)) {
            return $batch->state;
        }

        foreach ($this->argumentResolvers as $argumentResolver) {
            if ($argumentResolver->support($argument, $context->message->event()::class)) {
                return $argumentResolver->resolve($argument, $context);
            }
        }

        throw new NoSuitableResolver($subscriberAccessor->subscriber()::class, $subscribeMethod->name, $argument->name);
    }
}
