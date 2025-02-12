<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\PhpUnit\Tests\Unit\Test;

use Closure;
use Generator;
use Patchlevel\EventSourcing\PhpUnit\Test\AggregateAlreadySet;
use Patchlevel\EventSourcing\PhpUnit\Test\AggregateRootTestCase;
use Patchlevel\EventSourcing\PhpUnit\Test\NoAggregateCreated;
use Patchlevel\EventSourcing\PhpUnit\Tests\Unit\Fixture\Email;
use Patchlevel\EventSourcing\PhpUnit\Tests\Unit\Fixture\Profile;
use Patchlevel\EventSourcing\PhpUnit\Tests\Unit\Fixture\ProfileCreated;
use Patchlevel\EventSourcing\PhpUnit\Tests\Unit\Fixture\ProfileError;
use Patchlevel\EventSourcing\PhpUnit\Tests\Unit\Fixture\ProfileId;
use Patchlevel\EventSourcing\PhpUnit\Tests\Unit\Fixture\ProfileVisited;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(AggregateRootTestCase::class)]
final class AggregateRootTestCaseTest extends TestCase
{
    public function testException(): void
    {
        $test = $this->getTester();

        $test
            ->given(
                new ProfileCreated(
                    ProfileId::fromString('1'),
                    Email::fromString('hq@patchlevel.de'),
                ),
            )
            ->when(static fn (Profile $profile) => $profile->throwException())
            ->expectsException(ProfileError::class);

        $test->assert();
        self::assertSame(2, $test::getCount());
    }

    public function testExceptionMessage(): void
    {
        $test = $this->getTester();

        $test
            ->given(
                new ProfileCreated(
                    ProfileId::fromString('1'),
                    Email::fromString('hq@patchlevel.de'),
                ),
            )
            ->when(
                static fn (Profile $profile) => $profile->throwException(),
            )
            ->expectsExceptionMessage('throwing so that you can catch it!');

        $test->assert();
        self::assertSame(2, $test::getCount());
    }

    public function testExceptionAndMessage(): void
    {
        $test = $this->getTester();

        $test
            ->given(
                new ProfileCreated(
                    ProfileId::fromString('1'),
                    Email::fromString('hq@patchlevel.de'),
                ),
            )
            ->when(
                static fn (Profile $profile) => $profile->throwException(),
            )
            ->expectsException(ProfileError::class)
            ->expectsExceptionMessage('throwing so that you can catch it!');

        $test->assert();
        self::assertSame(3, $test::getCount());
    }

    public function testExceptionUncatched(): void
    {
        $test = $this->getTester();

        $test
            ->given(
                new ProfileCreated(
                    ProfileId::fromString('1'),
                    Email::fromString('hq@patchlevel.de'),
                ),
            )
            ->when(
                static fn (Profile $profile) => $profile->throwException(),
            );

        $this->expectException(ProfileError::class);
        $test->assert();
        self::assertSame(2, $test::getCount());
    }

    public function testVisited(): void
    {
        $test = $this->getTester();

        $test
            ->given(
                new ProfileCreated(
                    ProfileId::fromString('1'),
                    Email::fromString('hq@patchlevel.de'),
                ),
            )
            ->when(
                static fn (Profile $profile) => $profile->visitProfile(ProfileId::fromString('2')),
            )
            ->then(
                new ProfileVisited(ProfileId::fromString('2')),
            );

        $test->assert();
        self::assertSame(1, $test::getCount());
    }

    public function testVisitedDoubleAssert(): void
    {
        $test = $this->getTester();

        $test
            ->given(
                new ProfileCreated(
                    ProfileId::fromString('1'),
                    Email::fromString('hq@patchlevel.de'),
                ),
            )
            ->when(
                static fn (Profile $profile) => $profile->visitProfile(ProfileId::fromString('2')),
            )
            ->then(
                new ProfileVisited(ProfileId::fromString('2')),
            );

        $test->assert();
        $test->assert();
        self::assertSame(2, $test::getCount());
    }

    public function testCreation(): void
    {
        $test = $this->getTester();

        $test
            ->when(
                static fn () => Profile::createProfile(ProfileId::fromString('1'), Email::fromString('hq@patchlevel.de')),
            )
            ->then(
                new ProfileCreated(ProfileId::fromString('1'), Email::fromString('hq@patchlevel.de')),
            );

        $test->assert();
        self::assertSame(1, $test::getCount());
    }

    public function testCreationWithEmptyGiven(): void
    {
        $test = $this->getTester();

        $test
            ->given()
            ->when(
                static fn () => Profile::createProfile(ProfileId::fromString('1'), Email::fromString('hq@patchlevel.de')),
            )
            ->then(
                new ProfileCreated(ProfileId::fromString('1'), Email::fromString('hq@patchlevel.de')),
            );

        $test->assert();
        self::assertSame(1, $test::getCount());
    }

    public function testMissingGiven(): void
    {
        $test = $this->getTester();

        $test
            ->when(
                static fn () => Profile::createProfile(ProfileId::fromString('1'), Email::fromString('hq@patchlevel.de')),
            )
            ->then(
                new ProfileCreated(ProfileId::fromString('1'), Email::fromString('hq@patchlevel.de')),
            );

        $test->assert();
        self::assertSame(1, $test::getCount());
    }

    public function testNoGivenAndNoCreation(): void
    {
        $test = $this->getTester();

        $test
            ->when(
                static fn () => 'no aggregate as return',
            )
            ->then(
                new ProfileVisited(ProfileId::fromString('2')),
            );

        $this->expectException(NoAggregateCreated::class);
        $test->assert();
        self::assertSame(1, $test::getCount());
    }

    public function testDoubleAggregateCreation(): void
    {
        $test = $this->getTester();

        $test
            ->given(
                new ProfileCreated(
                    ProfileId::fromString('1'),
                    Email::fromString('hq@patchlevel.de'),
                ),
            )
            ->when(
                static fn () => Profile::createProfile(ProfileId::fromString('1'), Email::fromString('hq@patchlevel.de')),
            )
            ->then(
                new ProfileCreated(ProfileId::fromString('1'), Email::fromString('hq@patchlevel.de')),
            );

        $this->expectException(AggregateAlreadySet::class);
        $test->assert();
        self::assertSame(1, $test::getCount());
    }

    public function testReset(): void
    {
        $test = $this->getTester();

        $test
            ->given(
                new ProfileCreated(
                    ProfileId::fromString('1'),
                    Email::fromString('hq@patchlevel.de'),
                ),
            )
            ->when(
                static fn (Profile $profile) => $profile->visitProfile(ProfileId::fromString('2')),
            )
            ->then(
                new ProfileVisited(ProfileId::fromString('2')),
            );

        $test->reset();

        $this->expectException(NoAggregateCreated::class);
        $test->assert();
        self::assertSame(1, $test::getCount());
    }

    /** @return Generator<array{array<object>, array<Closure>, array<object>}> */
    public static function provideVariousTestCases(): iterable
    {
        yield [
            [
                new ProfileCreated(ProfileId::fromString('1'), Email::fromString('hq@patchlevel.de')),
            ],
            [
                static fn (Profile $profile) => $profile->visitProfile(ProfileId::fromString('2')),
            ],
            [
                new ProfileVisited(ProfileId::fromString('2')),
            ],
        ];

        yield [
            [],
            [
                static fn () => Profile::createProfile(ProfileId::fromString('1'), Email::fromString('hq@patchlevel.de')),
            ],
            [
                new ProfileCreated(ProfileId::fromString('1'), Email::fromString('hq@patchlevel.de')),
            ],
        ];
    }

    /**
     * @param array<object>  $givenEvents
     * @param array<Closure> $whens
     * @param array<object>  $expectedEvents
     */
    #[DataProvider('provideVariousTestCases')]
    public function testWithDataProvider(array $givenEvents, array $whens, array $expectedEvents): void
    {
        $test = $this->getTester();

        $test
            ->given(...$givenEvents)
            ->when(...$whens)
            ->then(...$expectedEvents);

        $test->assert();
        self::assertSame(1, $test::getCount());
    }

    public function getTester(): AggregateRootTestCase
    {
        return new class ($this->name()) extends AggregateRootTestCase {
            protected function aggregateClass(): string
            {
                return Profile::class;
            }
        };
    }
}
