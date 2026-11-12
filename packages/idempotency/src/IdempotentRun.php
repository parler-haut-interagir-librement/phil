<?php

declare(strict_types=1);

namespace Phil\Idempotency;

/**
 * What `IdempotencyGuard::run()` returns: the response, and whether it was replayed rather than produced now.
 */
final readonly class IdempotentRun
{
    public function __construct(
        public StoredResponse $response,
        public bool $replayed,
    ) {
    }
}
