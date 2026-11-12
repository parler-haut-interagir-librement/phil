<?php

declare(strict_types=1);

namespace Phil\ApiContractBundle\Infrastructure\Http;

use Phil\Idempotency\IdempotencyScope;
use Symfony\Component\HttpFoundation\Request;

/**
 * Who sent a request, for its idempotency keys: two callers that pick the same key never share it.
 */
interface IdempotencyScopeResolver
{
    public function scope(Request $request): IdempotencyScope;
}
