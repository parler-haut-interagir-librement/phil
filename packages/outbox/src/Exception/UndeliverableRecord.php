<?php

declare(strict_types=1);

namespace Phil\Outbox\Exception;

use RuntimeException;
use Throwable;

/**
 * Thrown by a codec when a record can never become an event, such as when the state it names is gone for good: the
 * relay moves the record to the dead records at once instead of trying it again.
 */
final class UndeliverableRecord extends RuntimeException implements OutboxException
{
    public static function because(string $reason, ?Throwable $previous = null): self
    {
        return new self($reason, 0, $previous);
    }
}
