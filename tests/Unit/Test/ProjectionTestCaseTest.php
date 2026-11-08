<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\PhpUnit\Tests\Unit\Test;

use Patchlevel\EventSourcing\Message\Message;
use Patchlevel\EventSourcing\Message\Message as StoredMessage;
use Patchlevel\EventSourcing\PhpUnit\Test\NoThenProvided;
use Patchlevel\EventSourcing\PhpUnit\Test\NoWhenProvided;
use Patchlevel\EventSourcing\PhpUnit\Test\ProjectionTestCase;
use Patchlevel\EventSourcing\PhpUnit\Test\WhenAlreadyCalled;
use Patchlevel\EventSourcing\PhpUnit\Tests\Unit\Fixture\Hotel\GuestAlreadyCheckedIn;
use Patchlevel\EventSourcing\PhpUnit\Tests\Unit\Fixture\Hotel\GuestIsCheckedIn;
use Patchlevel\EventSourcing\PhpUnit\Tests\Unit\Fixture\Hotel\GuestNames;
use Patchlevel\EventSourcing\PhpUnit\Tests\Unit\Fixture\Hotel\HotelCreated;
use Patchlevel\EventSourcing\PhpUnit\Tests\Unit\Fixture\Hotel\HotelError;
use Patchlevel\EventSourcing\PhpUnit\Tests\Unit\Fixture\Hotel\HotelExists;
use Patchlevel\EventSourcing\PhpUnit\Tests\Unit\Fixture\Hotel\HotelId;
use Patchlevel\EventSourcing\Projection\Projection;
use Patchlevel\EventSourcing\Store\Header\TagsHeader;
use PHPUnit\Framework\AssertionFailedError;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ProjectionTestCase::class)]
final class ProjectionTestCaseTest extends TestCase
{
    private HotelId $hotelId;
    private HotelId $otherHotelId;

    protected function setUp(): void
    {
        $this->hotelId = new HotelId('1');
        $this->otherHotelId = new HotelId('2');
    }

    public function testInitialState(): void
    {
        $test = $this->tester();

        $test
            ->when(new HotelExists($this->hotelId))
            ->then(false);

        $test->assert();
        self::assertSame(1, $test::getCount());
    }

    public function testAppliesGivenEvents(): void
    {
        $test = $this->tester();

        $test
            ->given(new HotelCreated($this->hotelId))
            ->when(new HotelExists($this->hotelId))
            ->then(true);

        $test->assert();
        self::assertSame(1, $test::getCount());
    }

    public function testEventsOutsideTheTagFilterAreIgnored(): void
    {
        $test = $this->tester();

        $test
            ->given(
                new GuestIsCheckedIn($this->hotelId, 'David'),
                new GuestIsCheckedIn($this->otherHotelId, 'Eve'),
                new GuestIsCheckedIn($this->hotelId, 'Daniel'),
            )
            ->when(new GuestNames($this->hotelId))
            ->then(['David', 'Daniel']);

        $test->assert();
        self::assertSame(1, $test::getCount());
    }

    public function testAcceptsMessages(): void
    {
        $test = $this->tester();

        $test
            ->given(Message::createWithHeaders(new HotelCreated($this->otherHotelId), [new TagsHeader(['hotel:1'])]))
            ->when(new HotelExists($this->hotelId))
            ->then(true);

        $test->assert();
        self::assertSame(1, $test::getCount());
    }

    public function testSeveralProjectionsAtOnce(): void
    {
        $test = $this->tester();

        $test
            ->given(new HotelCreated($this->hotelId))
            ->when([
                'exists' => new HotelExists($this->hotelId),
                'guest' => new GuestAlreadyCheckedIn($this->hotelId, 'David'),
            ])
            ->then(['exists' => true, 'guest' => false]);

        $test->assert();
        self::assertSame(1, $test::getCount());
    }

    public function testScalarStateIsComparedStrictly(): void
    {
        $test = $this->tester();

        $test
            ->given(new HotelCreated($this->hotelId))
            ->when(new HotelExists($this->hotelId))
            ->then(1);

        $this->expectException(AssertionFailedError::class);
        $this->expectExceptionMessage('The state doesn\'t match the expected state.');

        $test->assert();
    }

    public function testObjectStateIsComparedByValue(): void
    {
        $projection = new class implements Projection {
            public function initialState(): HotelId
            {
                return new HotelId('1');
            }

            public function apply(mixed $state, StoredMessage $message): mixed
            {
                return $state;
            }
        };

        $test = $this->tester();

        $test
            ->when($projection)
            ->then(new HotelId('1'));

        $test->assert();
        self::assertSame(1, $test::getCount());
    }

    public function testWrongArrayStateFails(): void
    {
        $test = $this->tester();

        $test
            ->given(new GuestIsCheckedIn($this->hotelId, 'David'))
            ->when(new GuestNames($this->hotelId))
            ->then(['Eve']);

        $this->expectException(AssertionFailedError::class);
        $this->expectExceptionMessage('The state doesn\'t match the expected state.');

        $test->assert();
    }

    public function testThenCallbackReceivesState(): void
    {
        $test = $this->tester();

        $test
            ->given(new GuestIsCheckedIn($this->hotelId, 'David'))
            ->when(new GuestNames($this->hotelId))
            ->then(static fn (array $state) => self::assertSame(['David'], $state));

        $test->assert();
        self::assertSame(1, $test::getCount());
    }

    public function testThenNull(): void
    {
        $projection = new class implements Projection {
            public function initialState(): mixed
            {
                return null;
            }

            public function apply(mixed $state, StoredMessage $message): mixed
            {
                return $state;
            }
        };

        $test = $this->tester();

        $test->when($projection)->then(null);

        $test->assert();
        self::assertSame(1, $test::getCount());
    }

    public function testExceptionIsExpected(): void
    {
        $test = $this->tester();

        $test
            ->when($this->failingProjection())
            ->expectsException(HotelError::class);

        $test->assert();
        self::assertSame(1, $test::getCount());
    }

    public function testUnexpectedExceptionIsThrown(): void
    {
        $test = $this->tester();

        $test
            ->when($this->failingProjection())
            ->then(null);

        $this->expectException(HotelError::class);

        $test->assert();
    }

    public function testExpectedExceptionNotThrown(): void
    {
        $test = $this->tester();

        $test
            ->when(new HotelExists($this->hotelId))
            ->expectsException(HotelError::class);

        $this->expectException(AssertionFailedError::class);

        $test->assert();
    }

    public function testNoWhenProvided(): void
    {
        $test = $this->tester();

        $this->expectException(NoWhenProvided::class);

        $test->assert();
    }

    public function testNoThenProvided(): void
    {
        $test = $this->tester();
        $test->when(new HotelExists($this->hotelId));

        $this->expectException(NoThenProvided::class);

        $test->assert();
    }

    public function testGivenAfterWhenFails(): void
    {
        $test = $this->tester();
        $test->when(new HotelExists($this->hotelId));

        $this->expectException(WhenAlreadyCalled::class);

        $test->given(new HotelCreated($this->hotelId));
    }

    public function testWhenTwiceFails(): void
    {
        $test = $this->tester();
        $test->when(new HotelExists($this->hotelId));

        $this->expectException(WhenAlreadyCalled::class);

        $test->when(new HotelExists($this->hotelId));
    }

    public function testReset(): void
    {
        $test = $this->tester();

        $test
            ->given(new HotelCreated($this->hotelId))
            ->when(new HotelExists($this->hotelId))
            ->then(true)
            ->expectsException(HotelError::class)
            ->expectsExceptionMessage('never thrown');

        $test->reset();

        $this->expectException(NoWhenProvided::class);

        $test->assert();
    }

    public function testResetClearsGivenEventsAndExpectations(): void
    {
        $test = $this->tester();

        $test
            ->given(new HotelCreated($this->hotelId))
            ->when($this->failingProjection())
            ->then(true)
            ->expectsException(HotelError::class)
            ->expectsExceptionMessage('never thrown');

        $test->reset();

        $test->when(new HotelExists($this->hotelId));

        $this->expectException(NoThenProvided::class);

        $test->assert();
    }

    public function testResetClearsTheStateOfThePreviousRun(): void
    {
        $test = $this->tester();

        $test->when(new HotelExists($this->hotelId))->then(false);
        $test->reset();
        $test->given(new HotelCreated($this->hotelId))->when(new HotelExists($this->hotelId))->then(true);

        $test->assert();
        self::assertSame(1, $test::getCount());
    }

    /** @return Projection<mixed> */
    private function failingProjection(): Projection
    {
        return new class implements Projection {
            public function initialState(): mixed
            {
                throw new HotelError('failed');
            }

            public function apply(mixed $state, StoredMessage $message): mixed
            {
                return $state;
            }
        };
    }

    private function tester(): ProjectionTestCase
    {
        return new class ($this->name()) extends ProjectionTestCase {
        };
    }
}
