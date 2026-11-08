# Testing Projections

A [decision](testing-decisions.md) gets its state from small projections, and each one depends on its tag filter and its apply methods. `ProjectionTestCase` tests a projection on its own: you describe the events that happened and assert the state you expect. The events run through an in-memory store, so tag filters, event filters and checkpoints behave like in production.

:::experimental
The dynamic consistency boundary is still experimental in the event-sourcing library and so are these test helpers. They may change in a minor release.
:::

## Given, when, then

Extend `ProjectionTestCase`. `given()` takes the events that already happened, `when()` takes the projection to read and `then()` takes the state you expect. The examples use the `EmailIsTaken` projection from [testing decisions](testing-decisions.md).

```php
use Patchlevel\EventSourcing\PhpUnit\Test\ProjectionTestCase;

final class EmailIsTakenTest extends ProjectionTestCase
{
    public function testEmailIsFree(): void
    {
        $this
            ->when(new EmailIsTaken(Email::fromString('hq@patchlevel.de')))
            ->then(false);
    }

    public function testEmailIsTaken(): void
    {
        $this
            ->given(new ProfileCreated(
                ProfileId::fromString('1'),
                Email::fromString('hq@patchlevel.de'),
            ))
            ->when(new EmailIsTaken(Email::fromString('hq@patchlevel.de')))
            ->then(true);
    }

    public function testIgnoresOtherEmails(): void
    {
        $this
            ->given(new ProfileCreated(
                ProfileId::fromString('1'),
                Email::fromString('info@patchlevel.de'),
            ))
            ->when(new EmailIsTaken(Email::fromString('hq@patchlevel.de')))
            ->then(false);
    }
}
```
:::note
Scalars and `null` are compared strictly, so `then(1)` does not pass for the state `true`. Objects and arrays are compared with `assertEquals`, so value objects inside your state are matched by value, not by identity.
:::

:::tip
Always add a test with an event outside of the tag filter. It is the cheapest way to catch a filter that is too wide.
:::

## Reading several projections

Pass an array of projections to read them at once, like `Decision::read()` does. The state has the same keys.

```php
use Patchlevel\EventSourcing\PhpUnit\Test\ProjectionTestCase;

final class EmailIsTakenTest extends ProjectionTestCase
{
    public function testSeveralEmails(): void
    {
        $this
            ->given(new ProfileCreated(
                ProfileId::fromString('1'),
                Email::fromString('hq@patchlevel.de'),
            ))
            ->when([
                'hq' => new EmailIsTaken(Email::fromString('hq@patchlevel.de')),
                'info' => new EmailIsTaken(Email::fromString('info@patchlevel.de')),
            ])
            ->then(['hq' => true, 'info' => false]);
    }
}
```
## Asserting with a closure

For a state that is awkward to compare as a whole, pass a closure to `then()`. It receives the state and can run any PHPUnit assertion.

```php
use Patchlevel\EventSourcing\PhpUnit\Test\ProjectionTestCase;

final class EmailIsTakenTest extends ProjectionTestCase
{
    public function testWithClosure(): void
    {
        $this
            ->when(new EmailIsTaken(Email::fromString('hq@patchlevel.de')))
            ->then(static fn (bool $state) => self::assertFalse($state));
    }
}
```
## Common errors

The test case raises a dedicated error for tests that would not assert anything:

* `NoWhenProvided` is raised when a test forgets to call `when()`.
* `NoThenProvided` is raised when a test forgets to call `then()`.
* `WhenAlreadyCalled` is raised when `given()` or a second `when()` follow a `when()`.

## Learn more

* [How to test decisions](testing-decisions.md)
* [How to test subscribers](testing-subscribers.md)
* [How to get started](getting-started.md)
