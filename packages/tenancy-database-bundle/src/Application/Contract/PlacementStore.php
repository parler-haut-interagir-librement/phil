<?php

declare(strict_types=1);

namespace Phil\TenancyDatabaseBundle\Application\Contract;

use Phil\TenancyBundle\Application\Contract\TenantPlacement;
use Phil\TenancyBundle\Domain\Placement\Placement;
use Phil\TenancyBundle\Domain\ValueObject\TenantId;

/**
 * A placement that can change: the move of a tenant from one database to another records the new one here once its
 * rows are copied and verified, and records the old one again when the move is rolled back.
 */
interface PlacementStore extends TenantPlacement
{
    /**
     * Durable once it returns: the next `placementOf()`, in any process, reads the new placement.
     */
    public function place(TenantId $tenant, Placement $placement): void;
}
