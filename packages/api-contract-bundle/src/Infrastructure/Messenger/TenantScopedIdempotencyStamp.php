<?php

declare(strict_types=1);

namespace Phil\ApiContractBundle\Infrastructure\Messenger;

use Symfony\Component\Messenger\Stamp\StampInterface;

/**
 * The tenant whose reference {@see TenantIdempotencyScopeMiddleware} put into the scope of the envelope's
 * `IdempotencyStamp`: a retry or a redelivery of the envelope keeps its scope as it is, instead of scoping it twice.
 * It holds the tenant's reference, an identifier, and nothing else.
 */
final readonly class TenantScopedIdempotencyStamp implements StampInterface
{
    public function __construct(
        public string $tenant,
    ) {
    }
}
