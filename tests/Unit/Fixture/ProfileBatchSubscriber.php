<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\PhpUnit\Tests\Unit\Fixture;

use ArrayObject;
use Patchlevel\EventSourcing\Attribute\BatchBegin;
use Patchlevel\EventSourcing\Attribute\BatchFlush;
use Patchlevel\EventSourcing\Attribute\BatchRollback;
use Patchlevel\EventSourcing\Attribute\BatchState;
use Patchlevel\EventSourcing\Attribute\Projector;
use Patchlevel\EventSourcing\Attribute\Subscribe;

use function array_values;

#[Projector('profile_batch')]
final class ProfileBatchSubscriber
{
    public int $begun = 0;

    /** @var list<list<string>> */
    public array $flushed = [];

    /** @var list<list<string>> */
    public array $rolledBack = [];

    /** @return ArrayObject<int, string> */
    #[BatchBegin]
    public function begin(): ArrayObject
    {
        $this->begun++;

        return new ArrayObject();
    }

    /** @param ArrayObject<int, string> $batch */
    #[Subscribe(ProfileCreated::class)]
    public function run(
        ProfileCreated $event,
        #[BatchState]
        ArrayObject $batch,
    ): void {
        if ($event->profileId->toString() === 'fail') {
            throw new ProfileError('failed');
        }

        $batch->append($event->profileId->toString());
    }

    /** @param ArrayObject<int, string> $batch */
    #[BatchFlush]
    public function flush(ArrayObject $batch): void
    {
        $this->flushed[] = array_values($batch->getArrayCopy());
    }

    /** @param ArrayObject<int, string> $batch */
    #[BatchRollback]
    public function rollback(ArrayObject $batch): void
    {
        $this->rolledBack[] = array_values($batch->getArrayCopy());
    }
}
