<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\PhpUnit\Test;

use Patchlevel\EventSourcing\Metadata\Subscriber\BatchMetadata;
use stdClass;

/** @internal */
final class SubscriberBatch
{
    private int $count = 0;

    private function __construct(
        private readonly object $subscriber,
        private readonly BatchMetadata $metadata,
        public readonly object $state,
    ) {
    }

    public static function begin(object $subscriber, BatchMetadata $metadata): self
    {
        $beginMethod = $metadata->beginMethod;

        /** @var object|null $state */
        $state = $beginMethod !== null ? $subscriber->$beginMethod() : null;

        return new self($subscriber, $metadata, $state ?? new stdClass());
    }

    /** Counts a processed message and tells whether the batch has to be flushed now. */
    public function messageProcessed(): bool
    {
        $this->count++;

        if ($this->metadata->afterMessages !== null && $this->count >= $this->metadata->afterMessages) {
            return true;
        }

        $shouldFlushMethod = $this->metadata->shouldFlushMethod;

        return $shouldFlushMethod !== null && $this->subscriber->$shouldFlushMethod($this->state) === true;
    }

    public function flush(): void
    {
        $flushMethod = $this->metadata->flushMethod;

        $this->subscriber->$flushMethod($this->state);
    }

    public function rollback(): void
    {
        $rollbackMethod = $this->metadata->rollbackMethod;

        if ($rollbackMethod === null) {
            return;
        }

        $this->subscriber->$rollbackMethod($this->state);
    }
}
