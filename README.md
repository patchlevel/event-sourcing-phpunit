[![Mutation testing badge](https://img.shields.io/endpoint?style=flat&url=https%3A%2F%2Fbadge-api.stryker-mutator.io%2Fgithub.com%2Fpatchlevel%2Fevent-sourcing-phpunit%2F1.0.x)](https://dashboard.stryker-mutator.io/reports/github.com/patchlevel/event-sourcing-phpunit/1.0.x)
[![Latest Stable Version](https://poser.pugx.org/patchlevel/event-sourcing-phpunit/v)](//packagist.org/packages/patchlevel/event-sourcing-phpunit)
[![License](https://poser.pugx.org/patchlevel/event-sourcing-phpunit/license)](//packagist.org/packages/patchlevel/event-sourcing-phpunit)

# Testing utilities

With this library you can ease the testing for your [event-sourcing](https://github.com/patchlevel/event-sourcing)
project when using PHPUnit. It comes with utilities for aggregates and subscribers.

## Installation

```bash
composer require --dev patchlevel/event-sourcing-phpunit
```

## Given / When / Then

## Testing Aggregates

There is a special `TestCase` for aggregate tests which you can extend from. Extending from `AggregateRootTestCase`
enables you to use the given / when / then notation. This makes it very clear what the test is doing. When extending the
class you will need to implement a method which provides the FQCN of the aggregate you want to test.

```php
final class ProfileTest extends AggregateRootTestCase
{
    protected function aggregateClass(): string
    {
        return Profile::class;
    }
}
```

When this is done, you already can start testing your behaviour. For example testing that a event is recorded.

```php
final class ProfileTest extends AggregateRootTestCase
{ 
    // protected function aggregateClass(): string;
    
    public function testBehaviour(): void
    {
        $this
            ->given(
                new ProfileCreated(
                    ProfileId::fromString('1'),
                    Email::fromString('hq@patchlevel.de'),
                ),
            )
            ->when(static fn (Profile $profile) => $profile->visitProfile(ProfileId::fromString('2')))
            ->then(new ProfileVisited(ProfileId::fromString('2')));
    }
}
```

You can also test multiple calls and events:

```php
final class ProfileTest extends AggregateRootTestCase
{ 
    // protected function aggregateClass(): string;
    
    public function testBehaviour(): void
    {
        $this
            ->given(
                new ProfileCreated(
                    ProfileId::fromString('1'),
                    Email::fromString('hq@patchlevel.de'),
                ),
            )
            ->when(
                static fn (Profile $profile) => $profile->visitProfile(ProfileId::fromString('2')),
                static fn (Profile $profile) => $profile->visitProfile(ProfileId::fromString('2')),
                static fn (Profile $profile) => $profile->visitProfile(ProfileId::fromString('2')),
            )
            ->then(
                new ProfileVisited(ProfileId::fromString('2')),
                new ProfileVisited(ProfileId::fromString('2')),
                new ProfileVisited(ProfileId::fromString('2')),
            );
    }
}
```

You can also test the creation of the aggregate:

```php
final class ProfileTest extends AggregateRootTestCase
{ 
    // protected function aggregateClass(): string;
    
    public function testBehaviour(): void
    {
        $this
            ->when(static fn () => Profile::createProfile(ProfileId::fromString('1'), Email::fromString('hq@patchlevel.de')))
            ->then(new ProfileCreated(ProfileId::fromString('1'), Email::fromString('hq@patchlevel.de')));
    }
}
```

And expect an exception and the message of it:

```php
final class ProfileTest extends AggregateRootTestCase
{ 
    // protected function aggregateClass(): string;
    
    public function testBehaviour(): void
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

## Testing Subscriber

For testing a subscriber there is a trait which you can use. When using `SubscriberUtilities` you will also be provided
with a bunch of dx features which makes the testing easier. First, providing the events is the same with a `given`
method. After that, you can call `executeRun` which can take multiple subscribers, which will be provided with the
`given` events. The events will be mapped according the `#[Subscribe]` attribute. For our example we are taking as
simplified subscriber:

```php
use Patchlevel\EventSourcing\Attribute\Setup;
use Patchlevel\EventSourcing\Attribute\Subscribe;
use Patchlevel\EventSourcing\Attribute\Subscriber;
use Patchlevel\EventSourcing\Attribute\Teardown;

#[Subscriber('profile_subscriber', RunMode::FromBeginning)]
final class ProfileSubscriber
{
    public int $called = 0;

    #[Subscribe(ProfileCreated::class)]
    public function run(): void
    {
        $this->called++;
    }

    #[Setup]
    public function setup(): void
    {
        $this->called++;
    }

    #[Teardown]
    public function teardown(): void
    {
        $this->called++;
    }
}
```

With this, we can now write our test for it:

```php
use Patchlevel\EventSourcing\Attribute\Subscriber;
use Patchlevel\EventSourcing\Subscription\RunMode;
use Patchlevel\EventSourcing\PhpUnit\Test\SubscriberUtilities;

final class ProfileSubscriberTest extends TestCase
{
    use SubscriberUtilities;

    public function testProfileCreated(): void 
    {
        $subscriber = new ProfileSubscriber(/* inject deps, if needed */);
        
        $this
            ->given(
                new ProfileCreated(
                    ProfileId::fromString('1'),
                    Email::fromString('hq@patchlevel.de'),
                ),
            )
            ->executeRun($subscriber);
            
        self::assertSame(1, $subscriber->count);
    }
}
```

You can also test the setup and teardown methods:

```php
use Patchlevel\EventSourcing\Attribute\Subscriber;
use Patchlevel\EventSourcing\Subscription\RunMode;
use Patchlevel\EventSourcing\PhpUnit\Test\SubscriberUtilities;

final class ProfileSubscriberTest extends TestCase
{
    use SubscriberUtilities;

    public function testSetup(): void 
    {
        $subscriber = new ProfileSubscriber(/* inject deps, if needed */);
        
        $this->executeSetup($subscriber);
            
        self::assertSame(1, $subscriber->count);
    }

    public function testTeardown(): void 
    {
        $subscriber = new ProfileSubscriber(/* inject deps, if needed */);
        
        $this->executeTeardown($subscriber);
            
        self::assertSame(1, $subscriber->count);
    }
}
```

Of course, you can also execute the whole workflow in one test:

```php
use Patchlevel\EventSourcing\Attribute\Subscriber;
use Patchlevel\EventSourcing\Subscription\RunMode;
use Patchlevel\EventSourcing\PhpUnit\Test\SubscriberUtilities;

final class ProfileSubscriberTest extends TestCase
{
    use SubscriberUtilities;

    public function testProfileCreated(): void 
    {
        $subscriber = new ProfileSubscriber(/* inject deps, if needed */);
        
        $this
            ->given(
                new ProfileCreated(
                    ProfileId::fromString('1'),
                    Email::fromString('hq@patchlevel.de'),
                ),
            )
            ->executeSetup($subscriber)
            ->executeRun($subscriber)
            ->executeTeardown($subscriber);
            
        self::assertSame(3, $subscriber->count);
    }
}
```