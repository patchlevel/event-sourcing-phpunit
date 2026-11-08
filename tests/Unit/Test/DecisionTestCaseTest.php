<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\PhpUnit\Tests\Unit\Test;

use Patchlevel\EventSourcing\Attribute\Handle;
use Patchlevel\EventSourcing\CommandBus\HandlerNotFound;
use Patchlevel\EventSourcing\DecisionModel\Decision;
use Patchlevel\EventSourcing\Message\Message;
use Patchlevel\EventSourcing\PhpUnit\Test\DecisionTestCase;
use Patchlevel\EventSourcing\PhpUnit\Test\DecisionTestStore;
use Patchlevel\EventSourcing\PhpUnit\Test\HandlerParameterResolver;
use Patchlevel\EventSourcing\PhpUnit\Test\NoWhenProvided;
use Patchlevel\EventSourcing\PhpUnit\Test\WhenAlreadyCalled;
use Patchlevel\EventSourcing\PhpUnit\Tests\Unit\Fixture\Hotel\CheckIn;
use Patchlevel\EventSourcing\PhpUnit\Tests\Unit\Fixture\Hotel\CheckInHandler;
use Patchlevel\EventSourcing\PhpUnit\Tests\Unit\Fixture\Hotel\CheckInWithFactoryHandler;
use Patchlevel\EventSourcing\PhpUnit\Tests\Unit\Fixture\Hotel\GuestAlreadyCheckedIn;
use Patchlevel\EventSourcing\PhpUnit\Tests\Unit\Fixture\Hotel\GuestIsCheckedIn;
use Patchlevel\EventSourcing\PhpUnit\Tests\Unit\Fixture\Hotel\HotelCreated;
use Patchlevel\EventSourcing\PhpUnit\Tests\Unit\Fixture\Hotel\HotelError;
use Patchlevel\EventSourcing\PhpUnit\Tests\Unit\Fixture\Hotel\HotelExists;
use Patchlevel\EventSourcing\PhpUnit\Tests\Unit\Fixture\Hotel\HotelExistsInStream;
use Patchlevel\EventSourcing\PhpUnit\Tests\Unit\Fixture\Hotel\HotelId;
use Patchlevel\EventSourcing\PhpUnit\Tests\Unit\Fixture\Notifier;
use Patchlevel\EventSourcing\Store\AppendConditionNotMet;
use Patchlevel\EventSourcing\Store\Header\StreamNameHeader;
use Patchlevel\EventSourcing\Store\Header\TagsHeader;
use PHPUnit\Framework\AssertionFailedError;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(DecisionTestCase::class)]
#[CoversClass(DecisionTestStore::class)]
#[CoversClass(HandlerParameterResolver::class)]
final class DecisionTestCaseTest extends TestCase
{
    private HotelId $hotelId;
    private HotelId $otherHotelId;

    protected function setUp(): void
    {
        $this->hotelId = new HotelId('1');
        $this->otherHotelId = new HotelId('2');
    }

    public function testCommandIsHandledWithDecision(): void
    {
        $test = $this->tester();

        $test
            ->given(new HotelCreated($this->hotelId))
            ->when(new CheckIn($this->hotelId, 'David'))
            ->then(new GuestIsCheckedIn($this->hotelId, 'David'));

        $test->assert();
        self::assertSame(1, $test::getCount());
    }

    public function testGivenReplacesPreviousGiven(): void
    {
        $test = $this->tester();

        $test
            ->given(new HotelCreated($this->otherHotelId))
            ->given(new HotelCreated($this->hotelId))
            ->when(new CheckIn($this->hotelId, 'David'))
            ->then(new GuestIsCheckedIn($this->hotelId, 'David'));

        $test->assert();
        self::assertSame(1, $test::getCount());
    }

    public function testGivenEventsAreTagged(): void
    {
        $test = $this->tester();

        $test
            ->given(
                new HotelCreated($this->hotelId),
                new GuestIsCheckedIn($this->hotelId, 'David'),
            )
            ->when(new CheckIn($this->hotelId, 'David'))
            ->expectsException(HotelError::class)
            ->expectsExceptionMessage('Guest "David" already checked in');

        $test->assert();
        self::assertSame(2, $test::getCount());
    }

    public function testGivenEventsOfAnotherBoundaryAreIgnored(): void
    {
        $test = $this->tester();

        $test
            ->given(new HotelCreated($this->otherHotelId))
            ->when(new CheckIn($this->hotelId, 'David'))
            ->expectsExceptionMessage('Hotel does not exist');

        $test->assert();
        self::assertSame(1, $test::getCount());
    }

    public function testGivenAcceptsMessagesWithCustomTagsAndStream(): void
    {
        $test = $this->tester();

        $test
            ->given(
                Message::createWithHeaders(new HotelCreated($this->otherHotelId), [
                    new TagsHeader(['hotel:1']),
                    new StreamNameHeader('hotels'),
                ]),
            )
            ->when(static function (Decision $decision): void {
                self::assertTrue($decision->read(new HotelExists(new HotelId('1'))));
                self::assertTrue($decision->read(new HotelExistsInStream(new HotelId('1'), 'hotels')));
                self::assertFalse($decision->read(new HotelExistsInStream(new HotelId('1'), 'main')));
            })
            ->then();

        $test->assert();
    }

    public function testGivenEventsAreStoredInTheMainStream(): void
    {
        $test = $this->tester();

        $test
            ->given(new HotelCreated($this->hotelId))
            ->when(function (Decision $decision): void {
                self::assertTrue($decision->read(new HotelExistsInStream($this->hotelId, 'main')));
            });

        $test->assert();
    }

    public function testAllMessagesOfAnAppendAreRecorded(): void
    {
        $test = $this->tester();

        $test
            ->when(function (Decision $decision): void {
                $decision->append([new HotelCreated($this->hotelId), new HotelCreated($this->otherHotelId)]);
            })
            ->then(new HotelCreated($this->hotelId), new HotelCreated($this->otherHotelId));

        $test->assert();
        self::assertSame(1, $test::getCount());
    }

    public function testGivenMessagesGetDefaultStreamAndTags(): void
    {
        $test = $this->tester();

        $test
            ->given(Message::create(new HotelCreated($this->hotelId)))
            ->when(new CheckIn($this->hotelId, 'David'))
            ->then(new GuestIsCheckedIn($this->hotelId, 'David'));

        $test->assert();
        self::assertSame(1, $test::getCount());
    }

    public function testAppendedMessagesContainHeaders(): void
    {
        $test = $this->tester();

        $test
            ->given(new HotelCreated($this->hotelId))
            ->when(new CheckIn($this->hotelId, 'David'));

        $messages = $test->appendedMessages();

        self::assertCount(1, $messages);
        self::assertEquals(new GuestIsCheckedIn($this->hotelId, 'David'), $messages[0]->event());
        self::assertSame('main', $messages[0]->header(StreamNameHeader::class)->streamName);
        self::assertSame(['hotel:1', 'guest:David'], $messages[0]->header(TagsHeader::class)->tags);

        $test->then(new GuestIsCheckedIn($this->hotelId, 'David'))->assert();
    }

    public function testAppendedMessagesUseTheStreamNameOfTheDecision(): void
    {
        $test = $this->tester();

        $test->when(function (Decision $decision): void {
            $decision->append([new HotelCreated($this->hotelId)], 'hotels');
        });

        self::assertSame('hotels', $test->appendedMessages()[0]->header(StreamNameHeader::class)->streamName);
        $test->then(new HotelCreated($this->hotelId))->assert();
    }

    public function testExtraParametersArePassedToTheHandler(): void
    {
        $notifier = $this->createMock(Notifier::class);
        $notifier->expects(self::once())->method('notify');

        $test = $this->tester();

        $test
            ->given(new HotelCreated($this->hotelId))
            ->when(new CheckIn($this->hotelId, 'David'), $notifier)
            ->then(new GuestIsCheckedIn($this->hotelId, 'David'));

        $test->assert();
    }

    public function testEveryDecisionParameterGetsANewDecision(): void
    {
        $test = new class ($this->name()) extends DecisionTestCase {
            /** @return iterable<object> */
            protected function handlers(): iterable
            {
                return [
                    new class {
                        #[Handle]
                        public function __invoke(CheckIn $command, Decision $first, string $token, Decision $second): void
                        {
                            self::assertNotSame($first, $second);
                            $first->append([new GuestIsCheckedIn($command->hotelId, $token)]);
                        }

                        private static function assertNotSame(object $first, object $second): void
                        {
                            TestCase::assertNotSame($first, $second);
                        }
                    },
                ];
            }
        };

        $test
            ->when(new CheckIn($this->hotelId, 'David'), 'a-token')
            ->then(new GuestIsCheckedIn($this->hotelId, 'a-token'));

        $test->assert();
    }

    public function testMissingExtraParameterFailsWithArgumentCountError(): void
    {
        $test = new class ($this->name()) extends DecisionTestCase {
            /** @return iterable<object> */
            protected function handlers(): iterable
            {
                return [
                    new class {
                        #[Handle]
                        public function __invoke(CheckIn $command, string $token): void
                        {
                        }
                    },
                ];
            }
        };

        $test
            ->when(new CheckIn($this->hotelId, 'David'))
            ->expectsExceptionMessage('Too few arguments');

        $test->assert();
        self::assertSame(1, $test::getCount());
    }

    public function testCommandWithoutHandler(): void
    {
        $test = new class ($this->name()) extends DecisionTestCase {
        };

        $test
            ->when(new CheckIn($this->hotelId, 'David'))
            ->expectsException(HandlerNotFound::class);

        $test->assert();
        self::assertSame(1, $test::getCount());
    }

    public function testClosureReceivesDecision(): void
    {
        $test = $this->tester();

        $test
            ->given(new HotelCreated($this->hotelId))
            ->when(fn (Decision $decision) => (new CheckInHandler())(new CheckIn($this->hotelId, 'David'), $decision))
            ->then(new GuestIsCheckedIn($this->hotelId, 'David'));

        $test->assert();
        self::assertSame(1, $test::getCount());
    }

    public function testHandlerWithInjectedDecisionFactory(): void
    {
        $test = $this->tester();
        $handler = new CheckInWithFactoryHandler($test->decisionFactory());

        $test
            ->given(new HotelCreated($this->hotelId))
            ->when(fn () => $handler(new CheckIn($this->hotelId, 'David')))
            ->then(new GuestIsCheckedIn($this->hotelId, 'David'));

        $test->assert();
        self::assertSame(1, $test::getCount());
    }

    public function testNothingAppendedWhenNoThenGiven(): void
    {
        $test = $this->tester();

        $test->when(static fn () => null);

        $test->assert();
        self::assertSame(1, $test::getCount());
    }

    public function testUnexpectedAppendFails(): void
    {
        $test = $this->tester();

        $test
            ->given(new HotelCreated($this->hotelId))
            ->when(new CheckIn($this->hotelId, 'David'));

        $this->expectException(AssertionFailedError::class);
        $this->expectExceptionMessage('The appended events doesn\'t match the expected events.');

        $test->assert();
    }

    public function testGivenAndConcurrentEventsAreNotPartOfTheAppendedEvents(): void
    {
        $test = $this->tester();

        $test
            ->given(new HotelCreated($this->hotelId))
            ->concurrently(new HotelCreated($this->otherHotelId))
            ->when(new CheckIn($this->hotelId, 'David'))
            ->then(new GuestIsCheckedIn($this->hotelId, 'David'));

        $test->assert();
        self::assertSame(1, $test::getCount());
    }

    public function testConflictWithConcurrentEventInsideTheBoundary(): void
    {
        $test = $this->tester();

        $test
            ->given(new HotelCreated($this->hotelId))
            ->concurrently(new GuestIsCheckedIn($this->hotelId, 'David'))
            ->when(new CheckIn($this->hotelId, 'David'))
            ->expectsConflict();

        $test->assert();
        self::assertSame(1, $test::getCount());
        self::assertSame([], $test->appendedMessages());
    }

    public function testNoConflictWithConcurrentEventOutsideTheBoundary(): void
    {
        $test = $this->tester();

        $test
            ->given(new HotelCreated($this->hotelId))
            ->concurrently(new GuestIsCheckedIn($this->hotelId, 'Eve'))
            ->when(new CheckIn($this->hotelId, 'David'))
            ->expectsConflict();

        $this->expectException(AssertionFailedError::class);
        $this->expectExceptionMessage(AppendConditionNotMet::class);

        $test->assert();
    }

    public function testConcurrentMessagesAreTagged(): void
    {
        $test = $this->tester();

        $test
            ->given(new HotelCreated($this->hotelId))
            ->concurrently(Message::create(new GuestIsCheckedIn($this->hotelId, 'David')))
            ->when(new CheckIn($this->hotelId, 'David'))
            ->expectsConflict();

        $test->assert();
        self::assertSame(1, $test::getCount());
    }

    public function testConcurrentEventsAreAppendedOnlyOnce(): void
    {
        $test = $this->tester();

        $test
            ->concurrently(new HotelCreated($this->hotelId))
            ->when(static function (Decision $decision) use ($test): void {
                $decision->append([new HotelCreated(new HotelId('3'))]);
                $test->decisionFactory()->begin()->append([new HotelCreated(new HotelId('4'))]);
            })
            ->then(
                new HotelCreated(new HotelId('3')),
                new HotelCreated(new HotelId('4')),
                static fn (Decision $decision) => self::assertTrue($decision->read(new HotelExists(new HotelId('1')))),
            );

        $test->assert();
        self::assertSame(2, $test::getCount());
    }

    public function testThenCallbackReadsTheStateAfterTheAppend(): void
    {
        $test = $this->tester();

        $test
            ->given(new HotelCreated($this->hotelId))
            ->when(new CheckIn($this->hotelId, 'David'))
            ->then(
                fn (Decision $decision) => self::assertTrue($decision->read(new GuestAlreadyCheckedIn($this->hotelId, 'David'))),
                new GuestIsCheckedIn($this->hotelId, 'David'),
                fn (Decision $decision) => self::assertFalse($decision->read(new GuestAlreadyCheckedIn($this->hotelId, 'Eve'))),
            );

        $test->assert();
        self::assertSame(3, $test::getCount());
    }

    public function testThenCallbackFailureNamesTheCallback(): void
    {
        $test = $this->tester();

        $test
            ->when(static fn () => null)
            ->then(static function (): void {
                throw new HotelError('callback failed');
            });

        try {
            $test->assert();
        } catch (AssertionFailedError $error) {
            self::assertStringContainsString('then() callback defined in ' . __FILE__, $error->getMessage());
            self::assertStringContainsString('failed: callback failed', $error->getMessage());

            return;
        }

        self::fail('The callback failure was not reported.');
    }

    public function testUnexpectedExceptionIsThrown(): void
    {
        $test = $this->tester();

        $test->when(new CheckIn($this->hotelId, 'David'));

        $this->expectException(HotelError::class);
        $this->expectExceptionMessage('Hotel does not exist');

        $test->assert();
    }

    public function testExpectedExceptionNotThrown(): void
    {
        $test = $this->tester();

        $test
            ->given(new HotelCreated($this->hotelId))
            ->when(new CheckIn($this->hotelId, 'David'))
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

    public function testGivenAfterWhenFails(): void
    {
        $test = $this->tester();
        $test->when(static fn () => null);

        $this->expectException(WhenAlreadyCalled::class);

        $test->given(new HotelCreated($this->hotelId));
    }

    public function testConcurrentlyAfterWhenFails(): void
    {
        $test = $this->tester();
        $test->when(static fn () => null);

        $this->expectException(WhenAlreadyCalled::class);

        $test->concurrently(new HotelCreated($this->hotelId));
    }

    public function testWhenTwiceFails(): void
    {
        $test = $this->tester();
        $test->when(static fn () => null);

        $this->expectException(WhenAlreadyCalled::class);

        $test->when(static fn () => null);
    }

    public function testResetStartsWithAnEmptyStore(): void
    {
        $test = $this->tester();

        $test
            ->given(new HotelCreated($this->hotelId))
            ->concurrently(new GuestIsCheckedIn($this->hotelId, 'David'))
            ->when(new CheckIn($this->hotelId, 'David'))
            ->then(new GuestIsCheckedIn($this->hotelId, 'Wrong'))
            ->expectsExceptionMessage('never thrown')
            ->expectsConflict();

        $test->reset();

        self::assertSame([], $test->appendedMessages());

        $test
            ->when(static function (Decision $decision): void {
                self::assertFalse($decision->read(new HotelExists(new HotelId('1'))));
            });

        $test->assert();
    }

    public function testResetClearsGivenAndConcurrentEvents(): void
    {
        $test = $this->tester();

        $test
            ->given(new HotelCreated($this->hotelId))
            ->concurrently(new HotelCreated($this->otherHotelId));

        $test->reset();

        $test->when(function (Decision $decision): void {
            self::assertFalse($decision->read(new HotelExists($this->hotelId)));
            $decision->append([new GuestIsCheckedIn($this->hotelId, 'David')]);
        })->then(
            new GuestIsCheckedIn($this->hotelId, 'David'),
            fn (Decision $decision) => self::assertFalse($decision->read(new HotelExists($this->otherHotelId))),
        );

        $test->assert();
    }

    private function tester(): DecisionTestCase
    {
        return new class ($this->name()) extends DecisionTestCase {
            /** @return iterable<object> */
            protected function handlers(): iterable
            {
                return [new CheckInHandler()];
            }
        };
    }
}
