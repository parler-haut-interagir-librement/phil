<?php

declare(strict_types=1);

namespace Phil\Outbox;

use Override;
use Phil\Outbox\Contract\Outbox;
use Phil\Outbox\Contract\OutboxStore;
use Phil\Outbox\Exception\UnknownEventType;
use Psr\Clock\ClockInterface;

/**
 * The outbox on a store: a record is checked against the allowlist of the codecs and the bounds of a record, dated by
 * the clock and added to the store, in the transaction the caller runs.
 */
final readonly class StoreOutbox implements Outbox
{
    public function __construct(
        private OutboxStore $store,
        private Codecs $codecs,
        private ClockInterface $clock = new SystemClock(),
    ) {
    }

    #[Override]
    public function record(string $type, array $payload = [], ?string $orderingKey = null, ?string $idempotencyKey = null): RecordId
    {
        if (!$this->codecs->has($type)) {
            throw UnknownEventType::named($type);
        }

        return $this->store->add(OutboxRecord::pending(RecordId::generate(), $type, Payload::of($payload), $orderingKey, $idempotencyKey, $this->clock->now()));
    }
}
