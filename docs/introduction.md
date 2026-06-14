# Event Sourcing PHPUnit

Testing utilities that make it easy to test your [event-sourcing](https://patchlevel.dev/docs/event-sourcing/latest) code with PHPUnit. It ships a given / when / then test case for aggregates and a helper that drives subscribers through their lifecycle, so your tests describe behaviour instead of wiring.

## Features

* A [given / when / then test case](testing-aggregates.md) for aggregate behaviour
* A `when` that also [dispatches commands](testing-aggregates.md) through your `#[Handle]` methods
* [Aggregate state assertions](testing-aggregates.md) with closures
* A [subscriber utility](testing-subscribers.md) for setup, run and teardown
* and much more...

## Installation

```bash
composer require --dev patchlevel/event-sourcing-phpunit
```
## Integration

* [event-sourcing](https://patchlevel.dev/docs/event-sourcing/latest)

:::tip
New here? Follow the [getting started](getting-started.md) guide to write your first test.
:::
