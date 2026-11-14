<?php

declare(strict_types=1);

namespace Phil\TenancyBundle\Tests\Functional\App;

use Phil\TenancyBundle\Application\Contract\ReachableTenantsProvider;
use Phil\TenancyBundle\Domain\ValueObject\ReachableTenants;
use Phil\TenancyBundle\Domain\ValueObject\TenantId;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * The account's entitlements, a comma-separated list in `X-Reachable-Tenants`. Without the header, none: the
 * adapter fails closed.
 */
final readonly class HeaderReachableTenants implements ReachableTenantsProvider
{
    public function __construct(
        private RequestStack $requests,
    ) {
    }

    public function reachable(): ReachableTenants
    {
        $header = $this->requests->getMainRequest()?->headers->get('X-Reachable-Tenants');

        if (null === $header || '' === $header) {
            return ReachableTenants::none();
        }

        return ReachableTenants::of(...array_map(TenantId::fromString(...), explode(',', $header)));
    }
}
