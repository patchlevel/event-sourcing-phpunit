# Getting Started

This guide walks you through testing a small profile domain end to end. You start with an aggregate test in the given / when / then style, then drive a subscriber through its lifecycle. The example assumes you already have an [event-sourcing](https://patchlevel.dev/docs/event-sourcing/latest) aggregate in place.

## Installation

Install the package as a development dependency:

```bash
composer require --dev patchlevel/event-sourcing-phpunit
```
## The example domain

The whole guide uses a `Profile` aggregate that can be created and visited. It records a `ProfileCreated` event on creation and a `ProfileVisited` event whenever someone visits it.

```php
use Patchlevel\EventSourcing\Aggregate\BasicAggregateRoot;
use Patchlevel\EventSourcing\Attribute\Aggregate;
use Patchlevel\EventSourcing\Attribute\Apply;
use Patchlevel\EventSourcing\Attribute\Id;

#[Aggregate('profile')]
final class Profile extends BasicAggregateRoot
{
    #[Id]
    private ProfileId $id;
    private Email $email;
    private int $visits = 0;

    public function id(): ProfileId
    {
        return $this->id;
    }

    public static function create(ProfileId $id, Email $email): self
    {
        $self = new self();
        $self->recordThat(new ProfileCreated($id, $email));

        return $self;
    }

    public function visit(ProfileId $visitorId): void
    {
        $this->recordThat(new ProfileVisited($visitorId));
    }

    #[Apply(ProfileCreated::class)]
    #[Apply(ProfileVisited::class)]
    protected function applyEvent(ProfileCreated|ProfileVisited $event): void
    {
        if ($event instanceof ProfileCreated) {
            $this->id = $event->profileId;
            $this->email = $event->email;

            return;
        }

        $this->visits++;
    }
}
```
:::note
Aggregates, events and the `#[Apply]` attribute come from the [event-sourcing](https://patchlevel.dev/docs/event-sourcing/latest) library, not from this package.
:::

## Write your first aggregate test

Extend [`AggregateRootTestCase`](testing-aggregates.md) and tell it which aggregate you are testing by implementing `aggregateClass()`. From there you describe the past with `given()`, trigger behaviour with `when()` and assert the recorded events with `then()`.

```php
use Patchlevel\EventSourcing\PhpUnit\Test\AggregateRootTestCase;

final class ProfileTest extends AggregateRootTestCase
{
    protected function aggregateClass(): string
    {
        return Profile::class;
    }

    public function testVisit(): void
    {
        $this
            ->given(
                new ProfileCreated(
                    ProfileId::fromString('1'),
                    Email::fromString('hq@patchlevel.de'),
                ),
            )
            ->when(static fn (Profile $profile) => $profile->visit(ProfileId::fromString('2')))
            ->then(new ProfileVisited(ProfileId::fromString('2')));
    }
}
```
## Test the creation

When there is no history yet, skip `given()` and let `when()` create the aggregate. Return the new aggregate from the closure so the test case can collect its events.

```php
use Patchlevel\EventSourcing\PhpUnit\Test\AggregateRootTestCase;

final class ProfileTest extends AggregateRootTestCase
{
    protected function aggregateClass(): string
    {
        return Profile::class;
    }

    public function testCreate(): void
    {
        $this
            ->when(static fn () => Profile::create(
                ProfileId::fromString('1'),
                Email::fromString('hq@patchlevel.de'),
            ))
            ->then(new ProfileCreated(
                ProfileId::fromString('1'),
                Email::fromString('hq@patchlevel.de'),
            ));
    }
}
```
:::tip
There are more ways to drive an aggregate, including a command bus aware `when()`. See [testing aggregates](testing-aggregates.md) for the full picture.
:::

## Test a subscriber

To test a subscriber, hand it to [`SubscriberUtilities`](testing-subscribers.md) and call the lifecycle methods. The utility resolves the `#[Setup]`, `#[Subscribe]` and `#[Teardown]` methods from the attributes for you.

```php
use Patchlevel\EventSourcing\Attribute\Subscribe;
use Patchlevel\EventSourcing\Attribute\Subscriber;
use Patchlevel\EventSourcing\PhpUnit\Test\SubscriberUtilities;
use Patchlevel\EventSourcing\Subscription\RunMode;
use PHPUnit\Framework\TestCase;

#[Subscriber('profile_counter', RunMode::FromBeginning)]
final class ProfileCounter
{
    public int $count = 0;

    #[Subscribe(ProfileCreated::class)]
    public function onProfileCreated(): void
    {
        $this->count++;
    }
}

final class ProfileCounterTest extends TestCase
{
    public function testProfileCreated(): void
    {
        $subscriber = new ProfileCounter();

        $util = new SubscriberUtilities($subscriber);
        $util->executeRun(
            new ProfileCreated(
                ProfileId::fromString('1'),
                Email::fromString('hq@patchlevel.de'),
            ),
        );

        self::assertSame(1, $subscriber->count);
    }
}
```
## Result

You now have a fast unit test suite for your aggregates and subscribers, written in a notation that reads like the behaviour it verifies. No database, no message bus and no container are involved.

## Learn more

* [How to test aggregates](testing-aggregates.md)
* [How to test subscribers](testing-subscribers.md)
