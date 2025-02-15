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

You can also provide multiple given events and expect multiple events:

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
                new ProfileVisited(ProfileId::fromString('2')),
            )
            ->when(
                static function (Profile $profile) {
                    $profile->visitProfile(ProfileId::fromString('3'));
                    $profile->visitProfile(ProfileId::fromString('4'));
                }
            )
            ->then(
                new ProfileVisited(ProfileId::fromString('3')),
                new ProfileVisited(ProfileId::fromString('4')),
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

For testing a subscriber there is a utility class which you can use. Using `SubscriberUtilities` will provide you a
bunch of dx features which makes the testing easier. First, you will need to provide the utility class the subscriptions
you will want to test, this is done when initialiszing the class. After that, you can call these 3 methods:
`executeSetup`, `executeRun` and `executeTeardown`. These methods will be calling the right methods which are defined
via the attributes. For our example we are taking as simplified subscriber:

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
        
        $util = new SubscriberUtilities($subscriber);
        $util->executeSetup();
        $util->executeRun(
            new ProfileCreated(
                ProfileId::fromString('1'),
                Email::fromString('hq@patchlevel.de'),
            )
        );
       $util->executeTeardown();
     
        self::assertSame(3, $subscriber->count);
    }
}
```

This Util class can be used for integration or unit tests.