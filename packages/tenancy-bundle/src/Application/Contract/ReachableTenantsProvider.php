<?php

declare(strict_types=1);

namespace Phil\TenancyBundle\Application\Contract;

use Phil\TenancyBundle\Domain\ValueObject\ReachableTenants;

/**
 * The tenants the current account can act in, read from the entitlements the application holds in cache.
 *
 * The adapter must fail closed: entitlements that can no longer be served resolve to
 * {@see ReachableTenants::none()}, not to the last set seen.
 */
interface ReachableTenantsProvider
{
    public function reachable(): ReachableTenants;
}
