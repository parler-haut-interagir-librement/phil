<?php

declare(strict_types=1);

namespace Phil\Outbox;

use DateTimeImmutable;

/**
 * The event `RecordedEventCodec` rebuilds: the record as it is, for listeners that read its identifiers themselves.
 * `$id` stays the same across deliveries, which tells a listener that it handled the record already.
 */
final readonly class RecordedEvent
{
    /**
     * @param int $attempt the attempt this delivery is, 1 for the first
     */
    public function __construct(
        public RecordId $id,
        public string $type,
        public Payload $payload,
        public ?string $orderingKey,
        public DateTimeImmutable $occurredAt,
        public int $attempt,
    ) {
    }
}
