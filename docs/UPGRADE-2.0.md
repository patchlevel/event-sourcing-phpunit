
---

## searchable: false

# Upgrade 2.0

## Requirements

### PHP

The minimum PHP version is now 8.4.

### Event Sourcing

This version requires `patchlevel/event-sourcing` 4.x, support for 3.x has been removed.
Your aggregates, events and subscribers have to be migrated as well,
follow the [event-sourcing upgrade guide](https://github.com/patchlevel/event-sourcing/blob/4.0.x/docs/UPGRADE-4.0.md) for that.

## Subscriber Tests

### Custom ArgumentResolver

The third constructor argument of `SubscriberUtilities` still takes your argument resolvers,
but event-sourcing 4.x changed the `ArgumentResolver` interface.
`resolve` receives an `ArgumentResolverContext` instead of the `Message`, and `ArgumentMetadata::$type` is now a `Symfony\Component\TypeInfo\Type`.

before:

```php
use Patchlevel\EventSourcing\Message\Message;
use Patchlevel\EventSourcing\Metadata\Subscriber\ArgumentMetadata;
use Patchlevel\EventSourcing\Subscription\Subscriber\ArgumentResolver\ArgumentResolver;

final class TokenResolver implements ArgumentResolver
{
    public function resolve(ArgumentMetadata $argument, Message $message): mixed
    {
        return $message->header(TokenHeader::class);
    }

    public function support(ArgumentMetadata $argument, string $eventClass): bool
    {
        return $argument->type === TokenHeader::class;
    }
}
```
after:

```php
use Patchlevel\EventSourcing\Metadata\Subscriber\ArgumentMetadata;
use Patchlevel\EventSourcing\Subscription\Subscriber\ArgumentResolver\ArgumentResolver;
use Patchlevel\EventSourcing\Subscription\Subscriber\ArgumentResolver\ArgumentResolverContext;

final class TokenResolver implements ArgumentResolver
{
    public function resolve(ArgumentMetadata $argument, ArgumentResolverContext $context): mixed
    {
        return $context->message->header(TokenHeader::class);
    }

    public function support(ArgumentMetadata $argument, string $eventClass): bool
    {
        return $argument->type->isIdentifiedBy(TokenHeader::class);
    }
}
```
### Batching

The `BatchableSubscriber` interface has been removed in event-sourcing 4.x in favor of the batch attributes.
`executeRun()` now drives the batch for you, so remove your manual calls. It begins the batch, passes the state
to the parameters marked with `#[BatchState]`, flushes it and rolls it back if a subscribe method throws.

before:

```php
use Patchlevel\EventSourcing\PhpUnit\Test\SubscriberUtilities;

$subscriber = new ProfileBatchCounter();

$util = new SubscriberUtilities($subscriber);

$subscriber->beginBatch();
$util->executeRun(new ProfileCreated(ProfileId::fromString('1'), Email::fromString('hq@patchlevel.de')));
$subscriber->commitBatch();
```
after:

```php
use Patchlevel\EventSourcing\PhpUnit\Test\SubscriberUtilities;

$subscriber = new ProfileBatchCounter();

$util = new SubscriberUtilities($subscriber);

$util->executeRun(new ProfileCreated(ProfileId::fromString('1'), Email::fromString('hq@patchlevel.de')));
```