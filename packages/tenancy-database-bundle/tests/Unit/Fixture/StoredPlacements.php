<?php

declare(strict_types=1);

namespace Phil\TenancyDatabaseBundle\Tests\Unit\Fixture;

use Override;
use Phil\TenancyBundle\Domain\Placement\Placement;
use Phil\TenancyBundle\Domain\ValueObject\TenantId;
use Phil\TenancyDatabaseBundle\Application\Contract\PlacementStore;

/**
 * A placement that can change, in memory.
 */
final class StoredPlacements implements PlacementStore
{
    /** @var array<string, Placement> */
    private array $placements = [];

    /**
     * @param array<string, string> $dedicated
     */
    public function __construct(array $dedicated = [])
    {
        foreach ($dedicated as $tenant => $database) {
            $this->placements[$tenant] = Placement::dedicated($database);
        }
    }

    #[Override]
    public function placementOf(TenantId $tenant): Placement
    {
        return $this->placements[$tenant->value] ?? Placement::shared();
    }

    #[Override]
    public function place(TenantId $tenant, Placement $placement): void
    {
        $this->placements[$tenant->value] = $placement;
    }
}
