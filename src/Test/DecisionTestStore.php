<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\PhpUnit\Test;

use Patchlevel\EventSourcing\DecisionModel\DecisionFactory;
use Patchlevel\EventSourcing\DecisionModel\EventAppender;
use Patchlevel\EventSourcing\DecisionModel\StoreEventAppender;
use Patchlevel\EventSourcing\Message\Message;
use Patchlevel\EventSourcing\Serializer\AttributeEventTagExtractor;
use Patchlevel\EventSourcing\Store\AppendCondition;
use Patchlevel\EventSourcing\Store\Header\StreamNameHeader;
use Patchlevel\EventSourcing\Store\Header\TagsHeader;
use Patchlevel\EventSourcing\Store\InMemoryStore;
use Patchlevel\EventSourcing\Store\Query;

use function array_map;

/**
 * The event store of a decision test. Events are tagged the same way as in production, so a projection only sees
 * them if its tag filter really matches. Everything the code under test appends goes through the real append condition.
 *
 * @internal
 *
 * @experimental
 */
final class DecisionTestStore implements EventAppender
{
    private const DEFAULT_STREAM_NAME = 'main';

    private readonly InMemoryStore $store;
    private readonly EventAppender $eventAppender;
    private readonly AttributeEventTagExtractor $eventTagExtractor;

    /** @var array<Message> */
    private array $concurrentMessages = [];

    /** @var list<Message> */
    private array $appendedMessages = [];

    public function __construct()
    {
        $this->store = new InMemoryStore();
        $this->eventAppender = new StoreEventAppender($this->store, defaultStreamName: self::DEFAULT_STREAM_NAME);
        $this->eventTagExtractor = new AttributeEventTagExtractor();
    }

    public function decisionFactory(): DecisionFactory
    {
        return new DecisionFactory($this->store, $this);
    }

    public function given(object ...$events): void
    {
        $this->store->append($this->messages($events));
    }

    /** The events are appended right before the next append, so after the decision has read its state. */
    public function concurrently(object ...$events): void
    {
        $this->concurrentMessages = $this->messages($events);
    }

    /** @param list<object> $events */
    public function append(
        array $events,
        AppendCondition|null $appendCondition = null,
        string|null $streamName = null,
    ): void {
        $this->store->append($this->concurrentMessages);
        $this->concurrentMessages = [];

        $head = $this->store->head();

        $this->eventAppender->append($events, $appendCondition, $streamName);

        $this->appendedMessages = [
            ...$this->appendedMessages,
            ...$this->store->query(new Query(), $head + 1),
        ];
    }

    /** @return list<Message> */
    public function appendedMessages(): array
    {
        return $this->appendedMessages;
    }

    /**
     * @param array<object> $events
     *
     * @return array<Message>
     */
    private function messages(array $events): array
    {
        return array_map($this->message(...), $events);
    }

    private function message(object $event): Message
    {
        $message = $event instanceof Message ? $event : Message::create($event);

        if (!$message->hasHeader(StreamNameHeader::class)) {
            $message = $message->withHeader(new StreamNameHeader(self::DEFAULT_STREAM_NAME));
        }

        if ($message->hasHeader(TagsHeader::class)) {
            return $message;
        }

        return $message->withHeader(new TagsHeader($this->eventTagExtractor->extract($message->event())));
    }
}
