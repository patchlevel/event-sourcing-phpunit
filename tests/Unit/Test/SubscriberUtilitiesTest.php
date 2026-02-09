<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\PhpUnit\Tests\Unit\Test;

use DateTimeImmutable;
use Patchlevel\EventSourcing\Attribute\Projector;
use Patchlevel\EventSourcing\Attribute\Setup;
use Patchlevel\EventSourcing\Attribute\Subscribe;
use Patchlevel\EventSourcing\Attribute\Teardown;
use Patchlevel\EventSourcing\Message\Message;
use Patchlevel\EventSourcing\PhpUnit\Test\SubscriberUtilities;
use Patchlevel\EventSourcing\PhpUnit\Tests\Unit\Fixture\Email;
use Patchlevel\EventSourcing\PhpUnit\Tests\Unit\Fixture\ProfileCreated;
use Patchlevel\EventSourcing\PhpUnit\Tests\Unit\Fixture\ProfileId;
use Patchlevel\EventSourcing\Store\Header\RecordedOnHeader;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(SubscriberUtilities::class)]
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
}
