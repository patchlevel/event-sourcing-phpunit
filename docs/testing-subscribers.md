# Testing Subscribers

Subscribers react to events to build projections, send notifications or run other side effects. `SubscriberUtilities` lets you drive a subscriber through its lifecycle without a running subscription engine, so you can unit test the reaction to a single event in isolation.

## The utility

Create a `SubscriberUtilities` instance with the subscriber you want to test. It reads the `#[Setup]`, `#[Subscribe]` and `#[Teardown]` attributes and exposes one method per lifecycle step: `executeSetup()`, `executeRun()` and `executeTeardown()`. Each method returns the utility, so you can chain the calls.

```php
use Patchlevel\EventSourcing\Attribute\Setup;
use Patchlevel\EventSourcing\Attribute\Subscribe;
use Patchlevel\EventSourcing\Attribute\Subscriber;
use Patchlevel\EventSourcing\Attribute\Teardown;
use Patchlevel\EventSourcing\Subscription\RunMode;

#[Subscriber('profile_counter', RunMode::FromBeginning)]
final class ProfileCounter
{
    public int $count = 0;

    #[Setup]
    public function setup(): void
    {
        $this->count = 0;
    }

    #[Subscribe(ProfileCreated::class)]
    public function onProfileCreated(): void
    {
        $this->count++;
    }

    #[Teardown]
    public function teardown(): void
    {
        $this->count = 0;
    }
}
```
:::note
The subscriber attributes come from the [event-sourcing](https://patchlevel.dev/docs/event-sourcing/latest) library. This package only invokes the methods they mark.
:::

## Running the lifecycle

Pass the subscriber to the utility and call the steps you want to verify. `executeRun()` accepts one or more events and forwards each to the matching `#[Subscribe]` methods.

```php
use Patchlevel\EventSourcing\PhpUnit\Test\SubscriberUtilities;
use PHPUnit\Framework\TestCase;

final class ProfileCounterTest extends TestCase
{
    public function testProfileCreated(): void
    {
        $subscriber = new ProfileCounter();

        $util = new SubscriberUtilities($subscriber);
        $util->executeSetup();
        $util->executeRun(
            new ProfileCreated(
                ProfileId::fromString('1'),
                Email::fromString('hq@patchlevel.de'),
            ),
        );
        $util->executeTeardown();

        self::assertSame(0, $subscriber->count);
    }
}
```
:::note
Each step is optional. If a subscriber has no setup or teardown method, `executeSetup()` and `executeTeardown()` simply do nothing, so you can call only the steps your test cares about.
:::

## Passing messages with headers

A subscribe method can ask for header values such as the recording time. To provide them, wrap the event in a `Message` and add the headers you need, then pass the message to `executeRun()`. Plain events are wrapped in a message automatically.

```php
use Patchlevel\EventSourcing\Attribute\Subscribe;
use Patchlevel\EventSourcing\Attribute\Subscriber;
use Patchlevel\EventSourcing\Subscription\RunMode;

#[Subscriber('profile_log', RunMode::FromBeginning)]
final class ProfileLog
{
    public DateTimeImmutable|null $lastSeen = null;

    #[Subscribe(ProfileCreated::class)]
    public function onProfileCreated(ProfileCreated $event, DateTimeImmutable $recordedOn): void
    {
        $this->lastSeen = $recordedOn;
    }
}
```
Build the message with the matching header in your test:

```php
use Patchlevel\EventSourcing\Message\Message;
use Patchlevel\EventSourcing\PhpUnit\Test\SubscriberUtilities;
use Patchlevel\EventSourcing\Store\Header\RecordedOnHeader;
use PHPUnit\Framework\TestCase;

final class ProfileLogTest extends TestCase
{
    public function testRecordsTimestamp(): void
    {
        $recordedOn = new DateTimeImmutable('2026-06-14 12:00:00');
        $subscriber = new ProfileLog();

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

        self::assertEquals($recordedOn, $subscriber->lastSeen);
    }
}
```
## Testing multiple subscribers

Pass an array of subscribers to test them together against the same events. Every lifecycle call is dispatched to all of them, which is useful when several projections react to one event.

```php
$util = new SubscriberUtilities([
    new ProfileCounter(),
    new ProfileLog(),
]);
$util->executeRun(
    new ProfileCreated(
        ProfileId::fromString('1'),
        Email::fromString('hq@patchlevel.de'),
    ),
);
```
:::tip
For subscribers that need extra constructor dependencies, build them yourself before handing them to the utility, just like any other object under test.

```php
$subscriber = new ProfileNotifier($mailerMock);
$util = new SubscriberUtilities($subscriber);
```
:::

## Custom argument resolvers

If your subscribe methods rely on custom arguments, pass your own argument resolvers as the third constructor argument. The second argument is the metadata factory, which defaults to the attribute based factory.

```php
use Patchlevel\EventSourcing\Metadata\Subscriber\AttributeSubscriberMetadataFactory;
use Patchlevel\EventSourcing\PhpUnit\Test\SubscriberUtilities;

$util = new SubscriberUtilities(
    new ProfileCounter(),
    new AttributeSubscriberMetadataFactory(),
    [new MyCustomArgumentResolver()],
);
```
:::note
Metadata factories and argument resolvers are defined by the [event-sourcing](https://patchlevel.dev/docs/event-sourcing/latest) library. You only need them when your subscribers go beyond the default event and header arguments.
:::

## Learn more

* [How to test aggregates](testing-aggregates.md)
* [How to get started](getting-started.md)
