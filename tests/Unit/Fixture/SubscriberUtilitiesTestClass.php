<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\PhpUnit\Tests\Unit\Fixture;

use Patchlevel\EventSourcing\PhpUnit\Test\SubscriberUtilities;
use Patchlevel\EventSourcing\Subscription\Subscriber\MetadataSubscriberAccessor;

final class SubscriberUtilitiesTestClass
{
    use SubscriberUtilities;

    /** @return array<object> */
    public function getGivenEvents(): array
    {
        return $this->givenEvents;
    }

    /** @return iterable<MetadataSubscriberAccessor<object>>|null */
    public function getSubscriberAccessors(): iterable|null
    {
        return $this->subscriberAccessors;
    }
}
