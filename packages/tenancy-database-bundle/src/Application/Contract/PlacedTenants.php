<?php

declare(strict_types=1);

namespace Phil\TenancyDatabaseBundle\Application\Contract;

use Phil\TenancyBundle\Domain\ValueObject\TenantId;

/**
 * The tenants whose placement the migrations must read: every tenant with a database of its own. A tenant in the
 * shared database can be left out, since the shared database is migrated in any case.
 *
 * Implemented by the same service as `TenantPlacement`.
 */
interface PlacedTenants
{
    /**
     * @return iterable<TenantId>
     */
    public function placedTenants(): iterable;
}
