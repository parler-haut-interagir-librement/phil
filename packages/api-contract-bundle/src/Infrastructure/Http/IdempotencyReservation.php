<?php

declare(strict_types=1);

namespace Phil\ApiContractBundle\Infrastructure\Http;

use Phil\Idempotency\IdempotencyKey;
use Phil\Idempotency\IdempotencyScope;

/**
 * The key a request reserved, kept on the request until its response is known, with the retention, in seconds, its
 * answer is kept for.
 */
final readonly class IdempotencyReservation
{
    public function __construct(
        public IdempotencyScope $scope,
        public IdempotencyKey $key,
        public int $ttl,
    ) {
    }
}
