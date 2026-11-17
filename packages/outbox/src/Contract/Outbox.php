<?php

declare(strict_types=1);

namespace Phil\Outbox\Contract;

use Phil\Outbox\Exception\InvalidRecord;
use Phil\Outbox\Exception\UnknownEventType;
use Phil\Outbox\RecordId;

/**
 * Where a package records what happened, in the same transaction as the state it describes: the relay tells the
 * listeners afterwards, at least once, even when the process stops between the write and the event.
 */
interface Outbox
{
    /**
     * Records an event of the allowlist, to be relayed once the transaction it runs in commits.
     *
     * @param string                  $type           the event type, one a registered codec rebuilds
     * @param array<array-key, mixed> $payload        the identifiers the codec rebuilds the event from, by name
     * @param string|null             $orderingKey    the aggregate whose records are relayed in the order they were
     *                                                recorded, one after the other; none when null
     * @param string|null             $idempotencyKey a key that records the event once for its type: recording it
     *                                                again adds nothing and gives back the first record's identifier
     *
     * @return RecordId the identifier the listeners receive, stable across deliveries
     *
     * @throws UnknownEventType when no codec rebuilds the type
     * @throws InvalidRecord    when the type, the payload or a key is out of bounds
     */
    public function record(string $type, array $payload = [], ?string $orderingKey = null, ?string $idempotencyKey = null): RecordId;
}
