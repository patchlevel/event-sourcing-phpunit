<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\PhpUnit\Tests\Unit\Test;

use Patchlevel\EventSourcing\Attribute\Projector;
use Patchlevel\EventSourcing\Attribute\Setup;
use Patchlevel\EventSourcing\Attribute\Subscribe;
use Patchlevel\EventSourcing\Attribute\Teardown;
use Patchlevel\EventSourcing\PhpUnit\Test\SubscriberUtilities;
use Patchlevel\EventSourcing\PhpUnit\Tests\Unit\Fixture\Email;
use Patchlevel\EventSourcing\PhpUnit\Tests\Unit\Fixture\ProfileCreated;
use Patchlevel\EventSourcing\PhpUnit\Tests\Unit\Fixture\ProfileId;
use Patchlevel\EventSourcing\PhpUnit\Tests\Unit\Fixture\SubscriberUtilitiesTestClass;
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

        $test = $this->getTester();
        $test
            ->given(
                new ProfileCreated(
                    ProfileId::fromString('1'),
                    Email::fromString('hq@patchlevel.de'),
                ),
            )
            ->executeRun($subscriber);

        self::assertSame(1, $subscriber->called);
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

        $test = $this->getTester();
        $test
            ->given(
                new ProfileCreated(
                    ProfileId::fromString('1'),
                    Email::fromString('hq@patchlevel.de'),
                ),
            )
            ->executeRun($subscriber);

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

        $test = $this->getTester();
        $test->executeSetup($subscriber, $subscriber2);

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

        $test = $this->getTester();
        $test->executeSetup($subscriber, $subscriber2);

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

        $test = $this->getTester();
        $test->executeSetup($subscriber, $subscriber2);

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

        $test = $this->getTester();
        $test->executeTeardown($subscriber, $subscriber2);

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

        $test = $this->getTester();
        $test->executeTeardown($subscriber, $subscriber2);

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

        $test = $this->getTester();
        $test->executeTeardown($subscriber, $subscriber2);

        self::assertSame(0, $subscriber->called);
        self::assertSame(1, $subscriber2->called);
    }

    public function testReset(): void
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

        $event = new ProfileCreated(ProfileId::fromString('1'), Email::fromString('hq@patchlevel.de'));

        $test = $this->getTester();
        $test
            ->given($event)
            ->executeRun($subscriber);

        self::assertSame(1, $subscriber->called);
        self::assertSame([$event], $test->getGivenEvents());

        $metadataSubscriberAccessors = $test->getSubscriberAccessors();
        self::assertNotNull($metadataSubscriberAccessors);
        self::assertCount(1, $metadataSubscriberAccessors);

        $test->reset();

        self::assertSame([], $test->getGivenEvents());
        self::assertNull($test->getSubscriberAccessors());

        $test = $this->getTester();
        $test
            ->given($event)
            ->executeRun($subscriber);

        self::assertSame(2, $subscriber->called);
        self::assertSame([$event], $test->getGivenEvents());
        self::assertNotSame($metadataSubscriberAccessors, $test->getSubscriberAccessors());
        self::assertEquals($metadataSubscriberAccessors, $test->getSubscriberAccessors());
    }

    public function testCaching(): void
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

        $event = new ProfileCreated(ProfileId::fromString('1'), Email::fromString('hq@patchlevel.de'));

        $test = $this->getTester();
        $test
            ->given($event)
            ->executeRun($subscriber);

        self::assertSame(1, $subscriber->called);
        self::assertSame([$event], $test->getGivenEvents());
        $metadataSubscriberAccessors = $test->getSubscriberAccessors();
        self::assertNotNull($metadataSubscriberAccessors);
        self::assertCount(1, $metadataSubscriberAccessors);

        $test->executeRun($subscriber);

        self::assertSame(2, $subscriber->called);
        self::assertSame([$event], $test->getGivenEvents());
        self::assertSame($metadataSubscriberAccessors, $test->getSubscriberAccessors());
    }

    public function getTester(): SubscriberUtilitiesTestClass
    {
        return new SubscriberUtilitiesTestClass();
    }
}
