<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\PhpUnit\Tests\Unit\Test;

use ArrayObject;
use DateTimeImmutable;
use Patchlevel\EventSourcing\Attribute\BatchBegin;
use Patchlevel\EventSourcing\Attribute\BatchFlush;
use Patchlevel\EventSourcing\Attribute\BatchShouldFlush;
use Patchlevel\EventSourcing\Attribute\BatchState;
use Patchlevel\EventSourcing\Attribute\Partition;
use Patchlevel\EventSourcing\Attribute\PartitionKey;
use Patchlevel\EventSourcing\Attribute\Projector;
use Patchlevel\EventSourcing\Attribute\Setup;
use Patchlevel\EventSourcing\Attribute\Subscribe;
use Patchlevel\EventSourcing\Attribute\Teardown;
use Patchlevel\EventSourcing\Message\Message;
use Patchlevel\EventSourcing\Metadata\Subscriber\ArgumentMetadata;
use Patchlevel\EventSourcing\PhpUnit\Test\SubscriberBatch;
use Patchlevel\EventSourcing\PhpUnit\Test\SubscriberUtilities;
use Patchlevel\EventSourcing\PhpUnit\Tests\Unit\Fixture\Email;
use Patchlevel\EventSourcing\PhpUnit\Tests\Unit\Fixture\Notifier;
use Patchlevel\EventSourcing\PhpUnit\Tests\Unit\Fixture\ProfileBatchSubscriber;
use Patchlevel\EventSourcing\PhpUnit\Tests\Unit\Fixture\ProfileCreated;
use Patchlevel\EventSourcing\PhpUnit\Tests\Unit\Fixture\ProfileError;
use Patchlevel\EventSourcing\PhpUnit\Tests\Unit\Fixture\ProfileId;
use Patchlevel\EventSourcing\PhpUnit\Tests\Unit\Fixture\ProfileVisited;
use Patchlevel\EventSourcing\Store\Header\RecordedOnHeader;
use Patchlevel\EventSourcing\Store\Header\StreamNameHeader;
use Patchlevel\EventSourcing\Store\InMemoryStore;
use Patchlevel\EventSourcing\Subscription\Partitioner\StreamPartitioner;
use Patchlevel\EventSourcing\Subscription\Subscriber\ArgumentResolver\ArgumentResolver;
use Patchlevel\EventSourcing\Subscription\Subscriber\ArgumentResolver\ArgumentResolverContext;
use Patchlevel\EventSourcing\Subscription\Subscriber\ArgumentResolver\EventEmitterResolver;
use Patchlevel\EventSourcing\Subscription\Subscriber\EventEmitter\EventEmitter;
use Patchlevel\EventSourcing\Subscription\Subscriber\NoSuitableResolver;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use stdClass;

use function array_values;

#[CoversClass(SubscriberUtilities::class)]
#[CoversClass(SubscriberBatch::class)]
final class SubscriberUtilitiesTest extends TestCase
{
    public function testRun(): void
    {
        $subscriber = new #[Projector('test')]
        class {
            public int $called = 0;

            #[Subscribe(ProfileCreated::class)]
            public function run(): void
            {
                $this->called++;
            }
        };

        $util = new SubscriberUtilities($subscriber);
        $util->executeRun(
            new ProfileCreated(
                ProfileId::fromString('1'),
                Email::fromString('hq@patchlevel.de'),
            ),
        );

        self::assertSame(1, $subscriber->called);
    }

    public function testCanPassMessagesWithHeaders(): void
    {
        $recordedOn = new DateTimeImmutable('now');
        $subscriber = new #[Projector('test')]
        class {
            public DateTimeImmutable|null $recordedOn = null;

            #[Subscribe(ProfileCreated::class)]
            public function run(ProfileCreated $event, DateTimeImmutable $recordedOn): void
            {
                $this->recordedOn = $recordedOn;
            }
        };

        $util = new SubscriberUtilities($subscriber);
        $util->executeRun(
            Message::createWithHeaders(
                new ProfileCreated(
                    ProfileId::fromString('1'),
                    Email::fromString('hq@patchlevel.de'),
                ),
                [new RecordedOnHeader($recordedOn)],
            ),
        );

        self::assertEquals($recordedOn, $subscriber->recordedOn);
    }

    public function testRunNotFound(): void
    {
        $subscriber = new #[Projector('test')]
        class {
            public int $called = 0;

            public function run(): void
            {
                $this->called++;
            }
        };

        $util = new SubscriberUtilities($subscriber);
        $util->executeRun(
            new ProfileCreated(
                ProfileId::fromString('1'),
                Email::fromString('hq@patchlevel.de'),
            ),
        );

        self::assertSame(0, $subscriber->called);
    }

    public function testSetupNotFound(): void
    {
        $subscriber = new #[Projector('test')]
        class {
            public int $called = 0;

            public function run(): void
            {
                $this->called++;
            }
        };

        $subscriber2 = new #[Projector('test2')]
        class {
            public int $called = 0;

            public function run(): void
            {
                $this->called++;
            }
        };

        $util = new SubscriberUtilities([$subscriber, $subscriber2]);
        $util->executeSetup();

        self::assertSame(0, $subscriber->called);
        self::assertSame(0, $subscriber2->called);
    }

    public function testSetup(): void
    {
        $subscriber = new #[Projector('test')]
        class {
            public int $called = 0;

            #[Setup]
            public function run(): void
            {
                $this->called++;
            }
        };

        $subscriber2 = new #[Projector('test2')]
        class {
            public int $called = 0;

            #[Setup]
            public function run(): void
            {
                $this->called++;
            }
        };

        $util = new SubscriberUtilities([$subscriber, $subscriber2]);
        $util->executeSetup();

        self::assertSame(1, $subscriber->called);
        self::assertSame(1, $subscriber2->called);
    }

    public function testSetupMixed(): void
    {
        $subscriber = new #[Projector('test')]
        class {
            public int $called = 0;

            public function run(): void
            {
                $this->called++;
            }
        };

        $subscriber2 = new #[Projector('test2')]
        class {
            public int $called = 0;

            #[Setup]
            public function run(): void
            {
                $this->called++;
            }
        };

        $util = new SubscriberUtilities([$subscriber, $subscriber2]);
        $util->executeSetup();

        self::assertSame(0, $subscriber->called);
        self::assertSame(1, $subscriber2->called);
    }

    public function testTeardownNotFound(): void
    {
        $subscriber = new #[Projector('test')]
        class {
            public int $called = 0;

            public function run(): void
            {
                $this->called++;
            }
        };

        $subscriber2 = new #[Projector('test2')]
        class {
            public int $called = 0;

            public function run(): void
            {
                $this->called++;
            }
        };

        $util = new SubscriberUtilities([$subscriber, $subscriber2]);
        $util->executeTeardown();

        self::assertSame(0, $subscriber->called);
        self::assertSame(0, $subscriber2->called);
    }

    public function testTeardown(): void
    {
        $subscriber = new #[Projector('test')]
        class {
            public int $called = 0;

            #[Teardown]
            public function run(): void
            {
                $this->called++;
            }
        };

        $subscriber2 = new #[Projector('test2')]
        class {
            public int $called = 0;

            #[Teardown]
            public function run(): void
            {
                $this->called++;
            }
        };

        $util = new SubscriberUtilities([$subscriber, $subscriber2]);
        $util->executeTeardown();

        self::assertSame(1, $subscriber->called);
        self::assertSame(1, $subscriber2->called);
    }

    public function testTeardownMixed(): void
    {
        $subscriber = new #[Projector('test')]
        class {
            public int $called = 0;

            public function run(): void
            {
                $this->called++;
            }
        };

        $subscriber2 = new #[Projector('test2')]
        class {
            public int $called = 0;

            #[Teardown]
            public function run(): void
            {
                $this->called++;
            }
        };

        $util = new SubscriberUtilities([$subscriber, $subscriber2]);
        $util->executeTeardown();

        self::assertSame(0, $subscriber->called);
        self::assertSame(1, $subscriber2->called);
    }

    public function testRunStopsAtUnresolvableArgument(): void
    {
        $subscriber = new #[Projector('test')]
        class {
            #[Subscribe(ProfileCreated::class)]
            public function run(Notifier $notifier): void
            {
            }
        };

        $util = new SubscriberUtilities($subscriber);

        $this->expectException(NoSuitableResolver::class);

        $util->executeRun($this->profileCreated('1'));
    }

    public function testCustomArgumentResolverIsUsedFirst(): void
    {
        $subscriber = new #[Projector('test')]
        class {
            public ProfileCreated|null $received = null;

            #[Subscribe(ProfileCreated::class)]
            public function run(ProfileCreated $event): void
            {
                $this->received = $event;
            }
        };

        $replaced = $this->profileCreated('replaced');

        $resolver = new class ($replaced) implements ArgumentResolver {
            public function __construct(
                private readonly object $event,
            ) {
            }

            public function resolve(ArgumentMetadata $argument, ArgumentResolverContext $context): object
            {
                return $this->event;
            }

            public function support(ArgumentMetadata $argument, string $eventClass): bool
            {
                return true;
            }
        };

        $util = new SubscriberUtilities($subscriber, argumentResolvers: [$resolver]);
        $util->executeRun($this->profileCreated('1'));

        self::assertSame($replaced, $subscriber->received);
    }

    public function testEventEmitterCanBeResolvedWithTheResolverOfTheLibrary(): void
    {
        $subscriber = new #[Projector('test')]
        class {
            #[Subscribe(ProfileCreated::class)]
            public function run(ProfileCreated $event, EventEmitter $eventEmitter): void
            {
                $eventEmitter->emit([new ProfileVisited($event->profileId)]);
            }
        };

        $store = new InMemoryStore();

        $util = new SubscriberUtilities($subscriber, argumentResolvers: [new EventEmitterResolver($store)]);
        $util->executeRun($this->profileCreated('1'));

        $message = $store->load()->current();

        self::assertNotNull($message);
        self::assertEquals(new ProfileVisited(ProfileId::fromString('1')), $message->event());
        self::assertSame('subscription_test', $message->header(StreamNameHeader::class)->streamName);
    }

    public function testBatchIsFlushedAtTheEndOfTheRun(): void
    {
        $subscriber = new ProfileBatchSubscriber();

        $util = new SubscriberUtilities($subscriber);
        $util->executeRun($this->profileCreated('1'), $this->profileCreated('2'));

        self::assertSame(1, $subscriber->begun);
        self::assertSame([['1', '2']], $subscriber->flushed);

        $util->executeRun($this->profileCreated('3'));

        self::assertSame(2, $subscriber->begun);
        self::assertSame([['1', '2'], ['3']], $subscriber->flushed);
    }

    public function testBatchIgnoresEventsWithoutSubscribeMethod(): void
    {
        $subscriber = new ProfileBatchSubscriber();

        $util = new SubscriberUtilities($subscriber);
        $util->executeRun(new ProfileVisited(ProfileId::fromString('1')));

        self::assertSame(0, $subscriber->begun);
        self::assertSame([], $subscriber->flushed);
    }

    public function testBatchIsRolledBackAndTheExceptionRethrown(): void
    {
        $subscriber = new ProfileBatchSubscriber();

        $util = new SubscriberUtilities($subscriber);

        try {
            $util->executeRun($this->profileCreated('1'), $this->profileCreated('fail'));
            self::fail('The exception was not rethrown.');
        } catch (ProfileError) {
            self::assertSame([['1']], $subscriber->rolledBack);
            self::assertSame([], $subscriber->flushed);
        }

        $util->executeRun($this->profileCreated('2'));

        self::assertSame(2, $subscriber->begun);
        self::assertSame([['2']], $subscriber->flushed);
    }

    public function testBatchWithoutRollbackMethodRethrowsTheException(): void
    {
        $subscriber = new #[Projector('test')]
        class {
            #[Subscribe(ProfileCreated::class)]
            public function run(): void
            {
                throw new ProfileError('failed');
            }

            #[BatchFlush]
            public function flush(): void
            {
            }
        };

        $util = new SubscriberUtilities($subscriber);

        $this->expectException(ProfileError::class);

        $util->executeRun($this->profileCreated('1'));
    }

    public function testBatchIsFlushedAfterMessages(): void
    {
        $subscriber = new #[Projector('test')]
        class {
            /** @var list<int> */
            public array $flushed = [];

            /** @return ArrayObject<int, string> */
            #[BatchBegin]
            public function begin(): ArrayObject
            {
                return new ArrayObject();
            }

            /** @param ArrayObject<int, string> $batch */
            #[Subscribe(ProfileCreated::class)]
            public function run(
                ProfileCreated $event,
                #[BatchState]
                ArrayObject $batch,
            ): void {
                $batch->append($event->profileId->toString());
            }

            /** @param ArrayObject<int, string> $batch */
            #[BatchFlush(afterMessages: 2)]
            public function flush(ArrayObject $batch): void
            {
                $this->flushed[] = $batch->count();
            }
        };

        $util = new SubscriberUtilities($subscriber);
        $util->executeRun(
            $this->profileCreated('1'),
            $this->profileCreated('2'),
            $this->profileCreated('3'),
        );

        self::assertSame([2, 1], $subscriber->flushed);
    }

    public function testBatchIsFlushedWhenTheSubscriberAsksForIt(): void
    {
        $subscriber = new #[Projector('test')]
        class {
            /** @var list<int> */
            public array $flushed = [];

            /** @return ArrayObject<int, string> */
            #[BatchBegin]
            public function begin(): ArrayObject
            {
                return new ArrayObject();
            }

            /** @param ArrayObject<int, string> $batch */
            #[Subscribe(ProfileCreated::class)]
            public function run(
                ProfileCreated $event,
                #[BatchState]
                ArrayObject $batch,
            ): void {
                $batch->append($event->profileId->toString());
            }

            /** @param ArrayObject<int, string> $batch */
            #[BatchShouldFlush]
            public function shouldFlush(ArrayObject $batch): bool
            {
                return $batch->count() === 1;
            }

            /** @param ArrayObject<int, string> $batch */
            #[BatchFlush]
            public function flush(ArrayObject $batch): void
            {
                $this->flushed[] = $batch->count();
            }
        };

        $util = new SubscriberUtilities($subscriber);
        $util->executeRun($this->profileCreated('1'), $this->profileCreated('2'));

        self::assertSame([1, 1], $subscriber->flushed);
    }

    public function testBatchWithoutBeginMethodGetsAnEmptyState(): void
    {
        $subscriber = new #[Projector('test')]
        class {
            public object|null $state = null;

            #[Subscribe(ProfileCreated::class)]
            public function run(
                ProfileCreated $event,
                #[BatchState]
                object $state,
            ): void {
                $this->state = $state;
            }

            #[BatchFlush]
            public function flush(object $state): void
            {
            }
        };

        $util = new SubscriberUtilities($subscriber);
        $util->executeRun($this->profileCreated('1'));

        self::assertEquals(new stdClass(), $subscriber->state);
    }

    public function testBatchStateIsOnlyPassedToMarkedArguments(): void
    {
        $subscriber = new #[Projector('test')]
        class {
            public Message|null $message = null;

            #[Subscribe(ProfileCreated::class)]
            public function run(Message $message): void
            {
                $this->message = $message;
            }

            #[BatchFlush]
            public function flush(object $state): void
            {
            }
        };

        $message = Message::create($this->profileCreated('1'));

        $util = new SubscriberUtilities($subscriber);
        $util->executeRun($message);

        self::assertSame($message, $subscriber->message);
    }

    public function testPartitionKeyIsResolved(): void
    {
        $subscriber = new #[Projector('test')]
        #[Partition(new StreamPartitioner())]
        class {
            /** @var list<string|null> */
            public array $keys = [];

            #[Subscribe(ProfileCreated::class)]
            public function run(
                #[PartitionKey]
                string|null $key,
            ): void {
                $this->keys[] = $key;
            }
        };

        $util = new SubscriberUtilities($subscriber);
        $util->executeRun(
            Message::createWithHeaders($this->profileCreated('1'), [new StreamNameHeader('profile-1')]),
            Message::createWithHeaders($this->profileCreated('2'), [new StreamNameHeader('profile-2')]),
        );

        self::assertSame(['profile-1', 'profile-2'], $subscriber->keys);
    }

    public function testPartitionerIsNotCalledForEventsWithoutSubscribeMethod(): void
    {
        $subscriber = new #[Projector('test')]
        #[Partition(new StreamPartitioner())]
        class {
            public int $called = 0;

            #[Subscribe(ProfileCreated::class)]
            public function run(): void
            {
                $this->called++;
            }
        };

        $util = new SubscriberUtilities($subscriber);
        $util->executeRun(new ProfileVisited(ProfileId::fromString('1')));

        self::assertSame(0, $subscriber->called);
    }

    public function testEveryPartitionHasItsOwnBatch(): void
    {
        $subscriber = new #[Projector('test')]
        #[Partition(new StreamPartitioner())]
        class {
            /** @var list<list<string>> */
            public array $flushed = [];

            /** @return ArrayObject<int, string> */
            #[BatchBegin]
            public function begin(): ArrayObject
            {
                return new ArrayObject();
            }

            /** @param ArrayObject<int, string> $batch */
            #[Subscribe(ProfileCreated::class)]
            public function run(
                ProfileCreated $event,
                #[BatchState]
                ArrayObject $batch,
            ): void {
                $batch->append($event->profileId->toString());
            }

            /** @param ArrayObject<int, string> $batch */
            #[BatchFlush]
            public function flush(ArrayObject $batch): void
            {
                $this->flushed[] = array_values($batch->getArrayCopy());
            }
        };

        $util = new SubscriberUtilities($subscriber);
        $util->executeRun(
            Message::createWithHeaders($this->profileCreated('1'), [new StreamNameHeader('a')]),
            Message::createWithHeaders($this->profileCreated('2'), [new StreamNameHeader('b')]),
            Message::createWithHeaders($this->profileCreated('3'), [new StreamNameHeader('a')]),
        );

        self::assertSame([['1', '3'], ['2']], $subscriber->flushed);
    }

    private function profileCreated(string $id): ProfileCreated
    {
        return new ProfileCreated(
            ProfileId::fromString($id),
            Email::fromString('hq@patchlevel.de'),
        );
    }
}
