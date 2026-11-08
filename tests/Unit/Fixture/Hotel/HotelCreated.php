<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\PhpUnit\Tests\Unit\Fixture\Hotel;

use Patchlevel\EventSourcing\Attribute\Event;
use Patchlevel\EventSourcing\Attribute\EventTag;

#[Event('hotel.created')]
final class HotelCreated
{
    public function __construct(
        #[EventTag(prefix: 'hotel')]
        public readonly HotelId $hotelId,
    ) {
    }
}
