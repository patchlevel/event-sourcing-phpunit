<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\PhpUnit\Tests\Unit\Fixture\Hotel;

use Patchlevel\EventSourcing\Attribute\Apply;
use Patchlevel\EventSourcing\Projection\BasicProjection;

/** @extends BasicProjection<list<string>> */
final class GuestNames extends BasicProjection
{
    public function __construct(
        private readonly HotelId $hotelId,
    ) {
    }

    /** @return list<string> */
    public function initialState(): array
    {
        return [];
    }

    /** @return list<string> */
    protected function tagFilter(): array
    {
        return ['hotel:' . $this->hotelId->toString()];
    }

    /**
     * @param list<string> $state
     *
     * @return list<string>
     */
    #[Apply]
    public function applyGuestIsCheckedIn(array $state, GuestIsCheckedIn $event): array
    {
        return [...$state, $event->guestName];
    }
}
