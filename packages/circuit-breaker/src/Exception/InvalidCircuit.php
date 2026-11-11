<?php

declare(strict_types=1);

namespace Phil\CircuitBreaker\Exception;

use InvalidArgumentException;

/**
 * A circuit key, a policy or a set of circuits was given a value it cannot work with.
 */
final class InvalidCircuit extends InvalidArgumentException implements CircuitBreakerException
{
    public static function because(string $reason): self
    {
        return new self($reason);
    }
}
