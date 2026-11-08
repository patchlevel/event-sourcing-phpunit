<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\PhpUnit\Tests\Unit\Fixture\Hotel;

final class CheckIn
{
    public function __construct(
        public readonly HotelId $hotelId,
        public readonly string $guestName,
    ) {
    }
}
