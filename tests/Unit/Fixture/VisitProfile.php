<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\PhpUnit\Tests\Unit\Fixture;

final readonly class VisitProfile
{
    public function __construct(
        public ProfileId $id,
    ) {
    }
}
