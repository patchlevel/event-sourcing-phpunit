<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\PhpUnit\Tests\Unit\Fixture;

use Patchlevel\EventSourcing\Aggregate\BasicAggregateRoot;
use Patchlevel\EventSourcing\Attribute\Aggregate;
use Patchlevel\EventSourcing\Attribute\Apply;
use Patchlevel\EventSourcing\Attribute\Handle;
use Patchlevel\EventSourcing\Attribute\Id;

#[Aggregate('profile')]
final class Profile extends BasicAggregateRoot
{
    #[Id]
    private ProfileId $id;
    private Email $email;
    private int $visits = 0;

    public function id(): ProfileId
    {
        return $this->id;
    }

    public function email(): Email
    {
        return $this->email;
    }

    #[Handle]
    public static function createProfile(CreateProfile $createProfile): self
    {
        $self = new self();
        $self->recordThat(new ProfileCreated($createProfile->id, $createProfile->email));

        return $self;
    }

    #[Handle]
    public function visitProfile(VisitProfile $visitProfile, string|null $token = null): void
    {
        $this->recordThat(new ProfileVisited($visitProfile->id, $token));
    }

    public function throwException(): void
    {
        throw new ProfileError('throwing so that you can catch it!');
    }

    #[Apply(ProfileCreated::class)]
    #[Apply(ProfileVisited::class)]
    protected function applyProfileCreated(ProfileCreated|ProfileVisited $event): void
    {
        if ($event instanceof ProfileCreated) {
            $this->id = $event->profileId;
            $this->email = $event->email;

            return;
        }

        $this->visits++;
    }
}
