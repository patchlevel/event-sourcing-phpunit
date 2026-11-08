<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\PhpUnit\Tests\Unit\Fixture\Hotel;

use Patchlevel\EventSourcing\Attribute\Handle;
use Patchlevel\EventSourcing\DecisionModel\Decision;
use Patchlevel\EventSourcing\PhpUnit\Tests\Unit\Fixture\Notifier;

use function sprintf;

final class CheckInHandler
{
    #[Handle]
    public function __invoke(CheckIn $command, Decision $decision, Notifier|null $notifier = null): void
    {
        if (!$decision->read(new HotelExists($command->hotelId))) {
            throw new HotelError('Hotel does not exist');
        }

        if ($decision->read(new GuestAlreadyCheckedIn($command->hotelId, $command->guestName))) {
            throw new HotelError(sprintf('Guest "%s" already checked in', $command->guestName));
        }

        $decision->append([new GuestIsCheckedIn($command->hotelId, $command->guestName)]);

        $notifier?->notify();
    }
}
