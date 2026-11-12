<?php

declare(strict_types=1);

namespace Phil\Idempotency\Exception;

use RuntimeException;

/**
 * The key was first used with another request: another method, target or payload. An HTTP API answers `422`.
 * Returning the first result would silently drop a corrected request.
 */
final class IdempotencyKeyReused extends RuntimeException implements IdempotencyException
{
    public static function withAnotherRequest(): self
    {
        return new self('This idempotency key was used with another request.');
    }
}
