<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\PhpUnit\Tests\Unit\Fixture\Hotel;

use Patchlevel\EventSourcing\Identifier\CustomIdBehaviour;
use Patchlevel\EventSourcing\Identifier\Identifier;

final class HotelId implements Identifier
{
    use CustomIdBehaviour;
}
