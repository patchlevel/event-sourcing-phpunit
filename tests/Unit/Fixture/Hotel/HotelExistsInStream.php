<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\PhpUnit\Tests\Unit\Fixture\Hotel;

use Patchlevel\EventSourcing\Attribute\Apply;
use Patchlevel\EventSourcing\Projection\BasicProjection;

/** @extends BasicProjection<bool> */
final class HotelExistsInStream extends BasicProjection
{
    public function __construct(
        private readonly HotelId $hotelId,
        private readonly string $stream,
    ) {
    }

    public function initialState(): bool
    {
        return false;
    }

    /** @return list<string> */
    protected function tagFilter(): array
    {
        return ['hotel:' . $this->hotelId->toString()];
    }

    protected function streamName(): string
    {
        return $this->stream;
    }

    #[Apply]
    public function applyHotelCreated(bool $state, HotelCreated $event): bool
    {
        return true;
    }
}
