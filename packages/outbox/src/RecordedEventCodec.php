<?php

declare(strict_types=1);

namespace Phil\Outbox;

use Override;
use Phil\Outbox\Contract\EventCodec;

/**
 * Rebuilds the records of the types it is given as `RecordedEvent`, without an event class of their own.
 */
final readonly class RecordedEventCodec implements EventCodec
{
    /**
     * @param list<string> $types
     */
    public function __construct(
        private array $types,
    ) {
    }

    #[Override]
    public function types(): array
    {
        return $this->types;
    }

    #[Override]
    public function decode(OutboxRecord $record, Payload $payload): RecordedEvent
    {
        return new RecordedEvent($record->id, $record->type, $payload, $record->orderingKey, $record->occurredAt, $record->attempts + 1);
    }
}
