<?php

declare(strict_types=1);

namespace Phil\TenancyDatabaseBundle\Infrastructure\Availability;

use Phil\TenancyBundle\Application\Contract\TenantPlacement;
use Phil\TenancyBundle\Domain\ValueObject\TenantId;
use Phil\TenancyDatabaseBundle\Application\Contract\TenantDatabaseAvailability;
use Phil\TenancyDatabaseBundle\Domain\Exception\TenantDatabaseUnavailable;

/**
 * Whether a tenant can be served now: not while it is being moved, and not while its database is suspended after a
 * failed migration. The other tenants are served as usual.
 */
final readonly class TenantAvailabilityGuard
{
    public function __construct(
        private TenantPlacement $placement,
        private TenantDatabaseAvailability $availability,
    ) {
    }

    /**
     * @throws TenantDatabaseUnavailable when the tenant cannot be served now
     */
    public function check(TenantId $tenant): void
    {
        if ($this->availability->isTenantSuspended($tenant)) {
            throw TenantDatabaseUnavailable::suspendedTenant($tenant);
        }

        $placement = $this->placement->placementOf($tenant);

        if ($this->availability->isDatabaseSuspended($placement)) {
            throw TenantDatabaseUnavailable::suspendedDatabase($tenant, $placement);
        }
    }
}
