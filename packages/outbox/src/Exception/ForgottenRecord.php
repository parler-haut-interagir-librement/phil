<?php

declare(strict_types=1);

namespace Phil\Outbox\Exception;

use RuntimeException;

/**
 * Thrown by a codec when the state a record names was erased on purpose, such as a request closed and purged after a
 * person asked to be forgotten: the relay marks the record skipped, dispatches nothing, and never counts it as dead.
 * A skipped record is purged with the delivered records.
 */
final class ForgottenRecord extends RuntimeException implements OutboxException
{
    /**
     * @param string $reason what was erased, named by types and identifiers, never by a value of the application
     */
    public static function because(string $reason): self
    {
        return new self($reason);
    }
}
