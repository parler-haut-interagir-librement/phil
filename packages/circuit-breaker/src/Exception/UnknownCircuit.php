<?php

declare(strict_types=1);

namespace Phil\CircuitBreaker\Exception;

use Phil\CircuitBreaker\CircuitKey;
use RuntimeException;

/**
 * A circuit breaker was asked for a key no circuit is declared for. A call to a third party never gets an
 * undeclared circuit by default: its policy is a decision, written with the call.
 */
final class UnknownCircuit extends RuntimeException implements CircuitBreakerException
{
    public static function keyed(CircuitKey $key): self
    {
        return new self(\sprintf('No circuit is declared for the key "%s".', $key->value));
    }
}
