<?php

declare(strict_types=1);

namespace Phil\ApiContractBundle\Infrastructure\Http;

use Override;
use Phil\TenancyBundle\Application\TenantContext;
use Symfony\Component\HttpFoundation\Request;

/**
 * The tenant `phil/tenancy-bundle` resolved for the request, after checking that the account may act in it; null
 * without the bundle, or outside any tenant.
 */
final readonly class ActiveTenantResolver implements IdempotencyTenantResolver
{
    public function __construct(
        private ?TenantContext $tenants = null,
    ) {
    }

    #[Override]
    public function tenant(Request $request): ?string
    {
        return $this->tenants?->current()?->value;
    }
}
