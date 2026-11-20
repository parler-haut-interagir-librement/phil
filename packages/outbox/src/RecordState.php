<?php

declare(strict_types=1);

namespace Phil\Outbox;

/**
 * Where a record stands: waiting for the relay, delivered to the listeners, skipped because what it names was erased,
 * or dead after its last attempt, kept to be inspected and tried again. A skipped record reached no listener; it is
 * settled like a delivered one, never holds back its ordering key, and is purged with the delivered records.
 */
enum RecordState: string
{
    case Pending = 'pending';
    case Delivered = 'delivered';
    case Skipped = 'skipped';
    case Dead = 'dead';

    /**
     * Whether the record is settled for good without a failure: delivered, or skipped. Neither holds back its ordering
     * key, and both are purged past their retention.
     */
    public function isSettled(): bool
    {
        return self::Delivered === $this || self::Skipped === $this;
    }
}
