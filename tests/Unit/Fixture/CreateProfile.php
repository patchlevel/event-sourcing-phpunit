<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\PhpUnit\Tests\Unit\Fixture;

final readonly class CreateProfile
{
    public function __construct(
        public ProfileId $id,
        public Email $email,
    ) {
    }
}
