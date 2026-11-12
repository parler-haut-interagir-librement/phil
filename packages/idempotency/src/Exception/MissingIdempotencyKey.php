<?php

declare(strict_types=1);

namespace Phil\Idempotency\Exception;

use RuntimeException;

/**
 * A request that must carry an `Idempotency-Key` header arrived without one: the `400` of the contract.
 */
final class MissingIdempotencyKey extends RuntimeException implements IdempotencyException
{
    public static function onRequest(): self
    {
        return new self('This request requires an Idempotency-Key header.');
    }
}
