<?php

declare(strict_types=1);

namespace Phil\TenancyDatabaseBundle\Infrastructure\Placement;

use Override;
use Phil\DddBundle\Domain\Exception\InvalidValue;
use Phil\TenancyBundle\Application\Contract\TenantPlacement;
use Phil\TenancyBundle\Domain\Placement\Placement;
use Phil\TenancyBundle\Domain\ValueObject\TenantId;
use Phil\TenancyDatabaseBundle\Application\Contract\PlacedTenants;

/**
 * The placements of `phil_tenancy_database.dedicated`: the tenants listed there each have the database named beside
 * them, every other tenant is in the shared database. Read-only: a move needs a `PlacementStore`.
 */
final readonly class ConfiguredPlacements implements TenantPlacement, PlacedTenants
{
    /** @var array<array-key, Placement> the placements by tenant reference */
    private array $dedicated;

    /** @var list<TenantId> */
    private array $tenants;

    /**
     * @param array<array-key, string> $dedicated the database of each tenant, by tenant reference
     *
     * @throws InvalidValue when a key is not a tenant reference or a value is not a database name
     */
    public function __construct(array $dedicated = [])
    {
        $placements = [];
        $tenants = [];

        foreach ($dedicated as $reference => $database) {
            $tenant = TenantId::fromString((string) $reference);
            $placements[$tenant->value] = Placement::dedicated($database);
            $tenants[] = $tenant;
        }

        $this->dedicated = $placements;
        $this->tenants = $tenants;
    }

    #[Override]
    public function placementOf(TenantId $tenant): Placement
    {
        return $this->dedicated[$tenant->value] ?? Placement::shared();
    }

    #[Override]
    public function placedTenants(): iterable
    {
        yield from $this->tenants;
    }
}
