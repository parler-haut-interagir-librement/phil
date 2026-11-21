<?php

declare(strict_types=1);

namespace Phil\Outbox\Contract;

use Phil\Outbox\Exception\ForgottenRecord;
use Phil\Outbox\Exception\UndeliverableRecord;
use Phil\Outbox\OutboxRecord;
use Phil\Outbox\Payload;

/**
 * Rebuilds the events of some types from their records, for the relay to dispatch: the allowlist of the outbox is the
 * set of types its codecs rebuild. A codec reads the identifiers of the payload and may read the rest again from where
 * its package keeps it; it never runs PHP's `unserialize()`.
 */
interface EventCodec
{
    /**
     * The event types this codec rebuilds.
     *
     * @return list<string>
     */
    public function types(): array;

    /**
     * The event of a record, which the relay dispatches to the PSR-14 listeners. A listener reads the record's
     * identifier from the event when the codec puts it there.
     *
     * @throws UndeliverableRecord when the record can never become an event: the record goes to the dead records at once
     * @throws ForgottenRecord     when what the record names was erased on purpose: the record is skipped, never dead
     */
    public function decode(OutboxRecord $record, Payload $payload): object;
}
