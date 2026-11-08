<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\PhpUnit\Tests\Unit\Fixture\Hotel;

use Patchlevel\EventSourcing\Attribute\Apply;
use Patchlevel\EventSourcing\Projection\BasicProjection;

/** @extends BasicProjection<bool> */
final class GuestAlreadyCheckedIn extends BasicProjection
{
    public function __construct(
        private readonly HotelId $hotelId,
        private readonly string $guestName,
    ) {
    }

    public function initialState(): bool
    {
        return false;
    }

    /** @return list<string> */
    protected function tagFilter(): array
    {
        return ['hotel:' . $this->hotelId->toString(), 'guest:' . $this->guestName];
    }

    #[Apply]
    public function applyGuestIsCheckedIn(bool $state, GuestIsCheckedIn $event): bool
    {
        return true;
    }
}
