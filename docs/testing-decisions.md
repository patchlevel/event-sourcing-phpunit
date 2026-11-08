# Testing Decisions

With the [dynamic consistency boundary](https://patchlevel.dev/docs/event-sourcing/latest/dynamic-consistency-boundary) a command handler reads a few small projections through a `Decision` and appends new events. `DecisionTestCase` lets you test these handlers with the same given / when / then notation as [aggregates](testing-aggregates.md). Your tests run against an in-memory store that applies the real tag queries and append conditions, so no database is involved.

:::experimental
The dynamic consistency boundary is still experimental in the event-sourcing library and so are these test helpers. They may change in a minor release.
:::

## The example domain

The examples on this page create profiles and make sure that every email address is only used once. This rule spans all profiles, so no single aggregate can enforce it. The event tags both the profile and the email address.

```php
use Patchlevel\EventSourcing\Attribute\Event;
use Patchlevel\EventSourcing\Attribute\EventTag;

#[Event('profile.created')]
final class ProfileCreated
{
    public function __construct(
        #[EventTag(prefix: 'profile')]
        public readonly ProfileId $profileId,
        public readonly Email $email,
    ) {
    }

    #[EventTag(prefix: 'email')]
    public function emailTag(): string
    {
        return $this->email->toString();
    }
}
```
A projection answers whether the email address is already taken. Its tag filter only selects the events of this one address.

```php
use Patchlevel\EventSourcing\Attribute\Apply;
use Patchlevel\EventSourcing\Projection\BasicProjection;

/** @extends BasicProjection<bool> */
final class EmailIsTaken extends BasicProjection
{
    public function __construct(
        private readonly Email $email,
    ) {
    }

    public function initialState(): bool
    {
        return false;
    }

    /** @return list<string> */
    protected function tagFilter(): array
    {
        return ['email:' . $this->email->toString()];
    }

    #[Apply]
    public function applyProfileCreated(bool $state, ProfileCreated $event): bool
    {
        return true;
    }
}
```
The handler reads the projection through the decision and appends the new event.

```php
use Patchlevel\EventSourcing\Attribute\Handle;
use Patchlevel\EventSourcing\DecisionModel\Decision;

final class EmailAlreadyTaken extends RuntimeException
{
}

final class CreateProfile
{
    public function __construct(
        public readonly ProfileId $id,
        public readonly Email $email,
    ) {
    }
}

final class CreateProfileHandler
{
    #[Handle]
    public function __invoke(CreateProfile $command, Decision $decision): void
    {
        if ($decision->read(new EmailIsTaken($command->email))) {
            throw new EmailAlreadyTaken('This email is already taken.');
        }

        $decision->append([new ProfileCreated($command->id, $command->email)]);
    }
}
```
:::note
Decisions, projections and the `#[EventTag]` attribute come from the [event-sourcing](https://patchlevel.dev/docs/event-sourcing/latest/dynamic-consistency-boundary) library, not from this package.
:::

## The test case

Extend `DecisionTestCase` and return the services with your `#[Handle]` methods from `handlers()`. A command passed to `when()` is dispatched to them, and every parameter of type `Decision` receives a new decision.

```php
use Patchlevel\EventSourcing\PhpUnit\Test\DecisionTestCase;

final class CreateProfileHandlerTest extends DecisionTestCase
{
    protected function handlers(): iterable
    {
        return [new CreateProfileHandler()];
    }
}
```
## Given, when, then

`given()` takes the events that already happened, `when()` runs the command and `then()` asserts the events that were appended. The events from `given()` are not part of that comparison.

```php
use Patchlevel\EventSourcing\PhpUnit\Test\DecisionTestCase;

final class CreateProfileHandlerTest extends DecisionTestCase
{
    protected function handlers(): iterable
    {
        return [new CreateProfileHandler()];
    }

    public function testCreateProfile(): void
    {
        $this
            ->given(
                new ProfileCreated(
                    ProfileId::fromString('1'),
                    Email::fromString('hq@patchlevel.de'),
                ),
            )
            ->when(new CreateProfile(
                ProfileId::fromString('2'),
                Email::fromString('info@patchlevel.de'),
            ))
            ->then(new ProfileCreated(
                ProfileId::fromString('2'),
                Email::fromString('info@patchlevel.de'),
            ));
    }
}
```
:::note
Given events are tagged with your `#[EventTag]` attributes exactly like in production. A projection only sees an event if its tag filter really matches, so a wrong tag fails your test.
:::

:::warning
Without `then()`, or when it receives only closures, the test asserts that no events were appended. Always list the events you expect.
:::

## Expecting exceptions

Use `expectsException()` and `expectsExceptionMessage()` to assert that a command is rejected. They work like in the [aggregate test case](testing-aggregates.md).

```php
use Patchlevel\EventSourcing\PhpUnit\Test\DecisionTestCase;

final class CreateProfileHandlerTest extends DecisionTestCase
{
    protected function handlers(): iterable
    {
        return [new CreateProfileHandler()];
    }

    public function testEmailMustBeUnique(): void
    {
        $this
            ->given(
                new ProfileCreated(
                    ProfileId::fromString('1'),
                    Email::fromString('hq@patchlevel.de'),
                ),
            )
            ->when(new CreateProfile(
                ProfileId::fromString('2'),
                Email::fromString('hq@patchlevel.de'),
            ))
            ->expectsException(EmailAlreadyTaken::class)
            ->expectsExceptionMessage('already taken');
    }
}
```
## Testing the consistency boundary

A decision must fail if something it has read changes before it appends. Pass the events of the other process to `concurrently()`. They are appended after the decision has read its state and right before it appends, so the real append condition decides what happens. `expectsConflict()` asserts that the append was rejected.

```php
use Patchlevel\EventSourcing\PhpUnit\Test\DecisionTestCase;

final class CreateProfileHandlerTest extends DecisionTestCase
{
    protected function handlers(): iterable
    {
        return [new CreateProfileHandler()];
    }

    public function testConflictsWithTheSameEmail(): void
    {
        $this
            ->concurrently(new ProfileCreated(
                ProfileId::fromString('1'),
                Email::fromString('hq@patchlevel.de'),
            ))
            ->when(new CreateProfile(
                ProfileId::fromString('2'),
                Email::fromString('hq@patchlevel.de'),
            ))
            ->expectsConflict();
    }

    public function testIsNotAffectedByOtherEmails(): void
    {
        $this
            ->concurrently(new ProfileCreated(
                ProfileId::fromString('1'),
                Email::fromString('info@patchlevel.de'),
            ))
            ->when(new CreateProfile(
                ProfileId::fromString('2'),
                Email::fromString('hq@patchlevel.de'),
            ))
            ->then(new ProfileCreated(
                ProfileId::fromString('2'),
                Email::fromString('hq@patchlevel.de'),
            ));
    }
}
```
:::tip
Test both directions. The conflict shows that your boundary is wide enough to protect the rule, the second test shows that it is not wider than necessary and does not block unrelated commands.
:::

:::note
`expectsConflict()` is a shorthand for `expectsException(AppendConditionNotMet::class)`. The events from `concurrently()` are not part of the comparison in `then()`.
:::

## Asserting the state after the append

Pass a closure to `then()` to check the state after the append. The closure receives a new decision, so you can read any projection.

```php
use Patchlevel\EventSourcing\DecisionModel\Decision;
use Patchlevel\EventSourcing\PhpUnit\Test\DecisionTestCase;

final class CreateProfileHandlerTest extends DecisionTestCase
{
    protected function handlers(): iterable
    {
        return [new CreateProfileHandler()];
    }

    public function testEmailIsTakenAfterwards(): void
    {
        $this
            ->when(new CreateProfile(
                ProfileId::fromString('1'),
                Email::fromString('hq@patchlevel.de'),
            ))
            ->then(
                new ProfileCreated(
                    ProfileId::fromString('1'),
                    Email::fromString('hq@patchlevel.de'),
                ),
                static fn (Decision $decision) => self::assertTrue(
                    $decision->read(new EmailIsTaken(Email::fromString('hq@patchlevel.de'))),
                ),
            );
    }
}
```
## Passing extra parameters

When a handler needs more than the command and the decision, pass the extra values after the command. They are forwarded in order to the parameters that are not a `Decision`.

```php
use Patchlevel\EventSourcing\Attribute\Handle;
use Patchlevel\EventSourcing\Clock\FrozenClock;
use Patchlevel\EventSourcing\DecisionModel\Decision;
use Patchlevel\EventSourcing\PhpUnit\Test\DecisionTestCase;
use Psr\Clock\ClockInterface;

final class CreateProfileWithClockHandler
{
    #[Handle]
    public function __invoke(CreateProfile $command, Decision $decision, ClockInterface $clock): void
    {
        $decision->append([new ProfileCreated($command->id, $command->email)]);
    }
}

final class CreateProfileWithClockHandlerTest extends DecisionTestCase
{
    protected function handlers(): iterable
    {
        return [new CreateProfileWithClockHandler()];
    }

    public function testCreateProfile(): void
    {
        $this
            ->when(
                new CreateProfile(
                    ProfileId::fromString('1'),
                    Email::fromString('hq@patchlevel.de'),
                ),
                new FrozenClock(new DateTimeImmutable('2026-06-14 12:00:00')),
            )
            ->then(new ProfileCreated(
                ProfileId::fromString('1'),
                Email::fromString('hq@patchlevel.de'),
            ));
    }
}
```
## Handlers without the command bus

If you don't use `#[Handle]`, for example with Symfony Messenger, pass a closure to `when()`. It receives a new decision. A handler that gets the `DecisionFactory` injected can use `decisionFactory()`, which works on the store of the test.

```php
use Patchlevel\EventSourcing\DecisionModel\DecisionFactory;
use Patchlevel\EventSourcing\PhpUnit\Test\DecisionTestCase;

final class CreateProfileMessengerHandler
{
    public function __construct(
        private readonly DecisionFactory $decisionFactory,
    ) {
    }

    public function __invoke(CreateProfile $command): void
    {
        $decision = $this->decisionFactory->begin();

        if ($decision->read(new EmailIsTaken($command->email))) {
            throw new EmailAlreadyTaken('This email is already taken.');
        }

        $decision->append([new ProfileCreated($command->id, $command->email)]);
    }
}

final class CreateProfileMessengerHandlerTest extends DecisionTestCase
{
    public function testCreateProfile(): void
    {
        $handler = new CreateProfileMessengerHandler($this->decisionFactory());

        $this
            ->when(static fn () => $handler(new CreateProfile(
                ProfileId::fromString('1'),
                Email::fromString('hq@patchlevel.de'),
            )))
            ->then(new ProfileCreated(
                ProfileId::fromString('1'),
                Email::fromString('hq@patchlevel.de'),
            ));
    }
}
```
## Working with headers

`given()` and `concurrently()` also accept a `Message`, which lets you set the stream name or your own tags. Headers you don't set are added automatically. To assert the headers of what was appended, read `appendedMessages()` after `when()`.

```php
use Patchlevel\EventSourcing\Message\Message;
use Patchlevel\EventSourcing\PhpUnit\Test\DecisionTestCase;
use Patchlevel\EventSourcing\Store\Header\StreamNameHeader;
use Patchlevel\EventSourcing\Store\Header\TagsHeader;

final class CreateProfileHandlerTest extends DecisionTestCase
{
    protected function handlers(): iterable
    {
        return [new CreateProfileHandler()];
    }

    public function testHeaders(): void
    {
        $this
            ->given(
                Message::createWithHeaders(
                    new ProfileCreated(
                        ProfileId::fromString('1'),
                        Email::fromString('info@patchlevel.de'),
                    ),
                    [new StreamNameHeader('profile-1')],
                ),
            )
            ->when(new CreateProfile(
                ProfileId::fromString('2'),
                Email::fromString('hq@patchlevel.de'),
            ))
            ->then(new ProfileCreated(
                ProfileId::fromString('2'),
                Email::fromString('hq@patchlevel.de'),
            ));

        $message = $this->appendedMessages()[0];

        self::assertSame('main', $message->header(StreamNameHeader::class)->streamName);
        self::assertSame(['profile:2', 'email:hq@patchlevel.de'], $message->header(TagsHeader::class)->tags);
    }
}
```
## Common errors

The test case guards against tests that would pass without testing anything:

* `NoWhenProvided` is raised when a test forgets to call `when()`.
* `WhenAlreadyCalled` is raised when `given()`, `concurrently()` or a second `when()` follow a `when()`. It runs immediately so that your mocks are verified correctly, which means everything it depends on has to come first.
* `HandlerNotFound` from the command bus is raised when `handlers()` does not return a handler for the command.

## Learn more

* [How to test projections](testing-projections.md)
* [How to test aggregates](testing-aggregates.md)
* [How to test subscribers](testing-subscribers.md)
