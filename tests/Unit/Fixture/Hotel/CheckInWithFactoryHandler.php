<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\PhpUnit\Tests\Unit\Fixture\Hotel;

use Patchlevel\EventSourcing\DecisionModel\DecisionFactory;

final class CheckInWithFactoryHandler
{
    public function __construct(
        private readonly DecisionFactory $decisionFactory,
    ) {
    }

    public function __invoke(CheckIn $command): void
    {
        $decision = $this->decisionFactory->begin();

        if (!$decision->read(new HotelExists($command->hotelId))) {
            throw new HotelError('Hotel does not exist');
        }

        $decision->append([new GuestIsCheckedIn($command->hotelId, $command->guestName)]);
    }
}
