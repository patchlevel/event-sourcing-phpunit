[![Mutation testing badge](https://img.shields.io/endpoint?style=flat&url=https%3A%2F%2Fbadge-api.stryker-mutator.io%2Fgithub.com%2Fpatchlevel%2Fevent-sourcing-phpunit%2F1.0.x)](https://dashboard.stryker-mutator.io/reports/github.com/patchlevel/event-sourcing-phpunit/1.0.x)
[![Latest Stable Version](https://poser.pugx.org/patchlevel/event-sourcing-phpunit/v)](//packagist.org/packages/patchlevel/event-sourcing-phpunit)
[![License](https://poser.pugx.org/patchlevel/event-sourcing-phpunit/license)](//packagist.org/packages/patchlevel/event-sourcing-phpunit)

# Event Sourcing PHPUnit

"Test your event-sourcing aggregates and subscribers with a clear given / when / then notation."

## Features

* A [given / when / then test case](https://patchlevel.dev/docs/event-sourcing-phpunit/latest/testing-aggregates) for aggregate behaviour
* A `when` that also [dispatches commands](https://patchlevel.dev/docs/event-sourcing-phpunit/latest/testing-aggregates) through your `#[Handle]` methods
* [Aggregate state assertions](https://patchlevel.dev/docs/event-sourcing-phpunit/latest/testing-aggregates) with closures
* A [subscriber utility](https://patchlevel.dev/docs/event-sourcing-phpunit/latest/testing-subscribers) for setup, run and teardown
* and much more...

## Installation

```bash
composer require --dev patchlevel/event-sourcing-phpunit
```

## Documentation

* Latest [Docs](https://patchlevel.dev/docs/event-sourcing-phpunit/latest)
* Related [Blog](https://patchlevel.dev/blog)

## Integration

* [event-sourcing](https://github.com/patchlevel/event-sourcing)

## Contributing

We are open to contributions as long as they are in line with
our [BC-Policy](https://patchlevel.dev/our-backward-compatibility-promise).

Also note that the `composer.lock` is always generated with the newest supported PHP version as this is the version our tools run in the CI.
