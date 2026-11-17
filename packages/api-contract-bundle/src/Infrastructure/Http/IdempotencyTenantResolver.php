<?php

declare(strict_types=1);

namespace Phil\ApiContractBundle\Infrastructure\Http;

use Symfony\Component\HttpFoundation\Request;

/**
 * The tenant a request acts in, once the application has verified it, for the scope of its idempotency keys: two
 * tenants that share a store never share a key. Null outside any tenant.
 *
 * The value is the tenant's stable reference, never a host or a header the client chose.
 */
interface IdempotencyTenantResolver
{
    public function tenant(Request $request): ?string;
}
