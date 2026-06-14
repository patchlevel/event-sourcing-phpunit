# Testing Aggregates

Aggregates hold your domain behaviour, so they deserve focused tests. The `AggregateRootTestCase` gives you a given / when / then notation that makes each test read like a small specification: the history that happened, the action you trigger and the events you expect in return.

## The test case

Extend `AggregateRootTestCase` and implement `aggregateClass()` to return the fully qualified class name of the aggregate under test. The test case uses it to rebuild the aggregate from your given events and to find command handlers.

```php
use Patchlevel\EventSourcing\PhpUnit\Test\AggregateRootTestCase;

final class ProfileTest extends AggregateRootTestCase
{
    protected function aggregateClass(): string
    {
        return Profile::class;
    }
}
```
## Given, when, then

`given()` takes the events that already happened, `when()` triggers behaviour on the rebuilt aggregate and `then()` asserts the events that were recorded. The closure passed to `when()` receives the aggregate instance.

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
:::note
The expected events are compared with `assertEquals`, so value objects inside your events are matched by value, not by identity.
:::

## Multiple events

You can pass several events to both `given()` and `then()`. The order of the expected events must match the order in which they were recorded.

```php
use Patchlevel\EventSourcing\PhpUnit\Test\AggregateRootTestCase;

final class ProfileTest extends AggregateRootTestCase
{
    protected function aggregateClass(): string
    {
        return Profile::class;
    }

    public function testMultipleVisits(): void
    {
        $this
            ->given(
                new ProfileCreated(
                    ProfileId::fromString('1'),
                    Email::fromString('hq@patchlevel.de'),
                ),
                new ProfileVisited(ProfileId::fromString('2')),
            )
            ->when(static function (Profile $profile): void {
                $profile->visit(ProfileId::fromString('3'));
                $profile->visit(ProfileId::fromString('4'));
            })
            ->then(
                new ProfileVisited(ProfileId::fromString('3')),
                new ProfileVisited(ProfileId::fromString('4')),
            );
    }
}
```
## Testing creation

When the aggregate does not exist yet, omit `given()` and return the freshly created aggregate from the `when()` closure. The test case collects the events from the returned aggregate.

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
:::warning
Return the aggregate only when there are no given events. Combining `given()` with a closure that also returns an aggregate raises an `AggregateAlreadySet` error, because the test case would not know which instance to use.
:::

## Expecting exceptions

Use `expectsException()` and `expectsExceptionMessage()` to assert that an action fails. You can use either one on its own or both together.

```php
use Patchlevel\EventSourcing\PhpUnit\Test\AggregateRootTestCase;

final class ProfileTest extends AggregateRootTestCase
{
    protected function aggregateClass(): string
    {
        return Profile::class;
    }

    public function testThrowsOnInvalidAction(): void
    {
        $this
            ->given(
                new ProfileCreated(
                    ProfileId::fromString('1'),
                    Email::fromString('hq@patchlevel.de'),
                ),
            )
            ->when(static fn (Profile $profile) => $profile->throwException())
            ->expectsException(ProfileError::class)
            ->expectsExceptionMessage('throwing so that you can catch it!');
    }
}
```
:::note
`expectsExceptionMessage()` matches if the thrown message is or contains the given string, so you can assert on a meaningful fragment instead of the full text.
:::

## Asserting aggregate state

Sometimes you want to check the aggregate's state, not only its events. Pass a closure to `then()` and it receives the aggregate after all events have been applied. You can mix expected events and closures freely, the event order is preserved regardless of where the closures sit.

```php
use Patchlevel\EventSourcing\PhpUnit\Test\AggregateRootTestCase;

final class ProfileTest extends AggregateRootTestCase
{
    protected function aggregateClass(): string
    {
        return Profile::class;
    }

    public function testStateAfterVisit(): void
    {
        $this
            ->given(
                new ProfileCreated(
                    ProfileId::fromString('1'),
                    Email::fromString('hq@patchlevel.de'),
                ),
            )
            ->when(static fn (Profile $profile) => $profile->visit(ProfileId::fromString('2')))
            ->then(
                new ProfileVisited(ProfileId::fromString('2')),
                static fn (Profile $profile) => self::assertSame('1', $profile->id()->toString()),
            );
    }
}
```
:::warning
When `then()` receives only closures and no event objects, it asserts that zero events were recorded. Always list the events you expect alongside your state assertions.
:::

## Command bus syntax

If your aggregate handles commands with the `#[Handle]` attribute, you can pass the command object straight to `when()`. The test case finds the matching handler and invokes it, whether it is a static factory or an instance method.

```php
use Patchlevel\EventSourcing\PhpUnit\Test\AggregateRootTestCase;

final class ProfileTest extends AggregateRootTestCase
{
    protected function aggregateClass(): string
    {
        return Profile::class;
    }

    public function testCreateViaCommand(): void
    {
        $this
            ->when(new CreateProfile(
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
When the handler needs more than the command, pass the extra arguments after the command. They are forwarded to the handler method in order, which is handy for injecting dependencies or values.

```php
use Patchlevel\EventSourcing\PhpUnit\Test\AggregateRootTestCase;

final class ProfileTest extends AggregateRootTestCase
{
    protected function aggregateClass(): string
    {
        return Profile::class;
    }

    public function testVisitViaCommandWithToken(): void
    {
        $this
            ->given(
                new ProfileCreated(
                    ProfileId::fromString('1'),
                    Email::fromString('hq@patchlevel.de'),
                ),
            )
            ->when(new VisitProfile(ProfileId::fromString('2')), 'a-token')
            ->then(new ProfileVisited(ProfileId::fromString('2'), 'a-token'));
    }
}
```
:::tip
The command bus and the `#[Handle]` attribute are part of the [event-sourcing](https://patchlevel.dev/docs/event-sourcing/latest) library. More about the [command bus](https://patchlevel.dev/docs/event-sourcing/latest) lives in its documentation.
:::

## Common errors

The test case guards against incomplete tests with dedicated errors, all extending `AggregateTestError`:

* `NoWhenProvided` is raised when a test forgets to call `when()`, because nothing would actually be exercised.
* `NoAggregateCreated` is raised when neither given events nor a returned aggregate produced an instance to assert on.
* `AggregateAlreadySet` is raised when given events and a returned aggregate are combined.

## Learn more

* [How to test subscribers](testing-subscribers.md)
* [How to get started](getting-started.md)
