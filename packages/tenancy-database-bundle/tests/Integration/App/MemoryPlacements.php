<?php

declare(strict_types=1);

namespace Phil\TenancyDatabaseBundle\Tests\Integration\App;

use Override;
use Phil\TenancyBundle\Domain\Placement\Placement;
use Phil\TenancyBundle\Domain\ValueObject\TenantId;
use Phil\TenancyDatabaseBundle\Application\Contract\PlacedTenants;
use Phil\TenancyDatabaseBundle\Application\Contract\PlacementStore;
use RuntimeException;

/**
 * Placements kept in memory, from the database of each dedicated tenant: what an application keeps in a table of its
 * shared database. A store that fails on its next `place()` stands in for a store that is down.
 */
final class MemoryPlacements implements PlacementStore, PlacedTenants
{
    /** @var array<string, Placement> */
    private array $placements = [];

    private bool $failNext = false;

    /**
     * @param array<string, string> $dedicated
     */
    public function __construct(array $dedicated = [])
    {
        foreach ($dedicated as $tenant => $database) {
            $this->placements[$tenant] = Placement::dedicated($database);
        }
    }

    public function failOnNextPlace(): void
    {
        $this->failNext = true;
    }

    #[Override]
    public function placementOf(TenantId $tenant): Placement
    {
        return $this->placements[$tenant->value] ?? Placement::shared();
    }

    #[Override]
    public function place(TenantId $tenant, Placement $placement): void
    {
        if ($this->failNext) {
            $this->failNext = false;

            throw new RuntimeException('The placement store is down.');
        }

        $this->placements[$tenant->value] = $placement;
    }

    #[Override]
    public function placedTenants(): iterable
    {
        foreach (array_keys($this->placements) as $tenant) {
            yield TenantId::fromString($tenant);
        }
    }
}
