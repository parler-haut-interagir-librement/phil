<?php

declare(strict_types=1);

namespace Phil\Idempotency\Exception;

use RuntimeException;

/**
 * The first request with this key is still running. An HTTP API answers `409` with a `Retry-After` of
 * `$retryAfter` seconds: the time left before the reservation expires, at least 1.
 */
final class RequestInProgress extends RuntimeException implements IdempotencyException
{
    public readonly int $retryAfter;

    public function __construct(int $retryAfter)
    {
        parent::__construct('A request with this idempotency key is in progress.');
        $this->retryAfter = max(1, $retryAfter);
    }

    public static function retryAfter(int $seconds): self
    {
        return new self($seconds);
    }
}
