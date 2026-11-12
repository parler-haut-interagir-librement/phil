<?php

declare(strict_types=1);

namespace Phil\Idempotency\Exception;

use InvalidArgumentException;

/**
 * A key, a scope, a fingerprint, a stored response or a record that breaks its rule.
 */
final class InvalidIdempotencyValue extends InvalidArgumentException implements IdempotencyException
{
    public static function because(string $reason): self
    {
        return new self($reason);
    }
}
