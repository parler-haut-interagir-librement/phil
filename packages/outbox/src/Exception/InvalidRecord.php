<?php

declare(strict_types=1);

namespace Phil\Outbox\Exception;

use InvalidArgumentException;
use Throwable;

/**
 * A record the outbox refuses: a type, a key or a payload out of its bounds, or a payload read back that is not one.
 */
final class InvalidRecord extends InvalidArgumentException implements OutboxException
{
    public static function because(string $reason, ?Throwable $previous = null): self
    {
        return new self($reason, 0, $previous);
    }
}
