<?php

declare(strict_types=1);

namespace Phil\TenancyDatabaseBundle\Infrastructure\Placement;

use Doctrine\DBAL\Connection;
use Override;
use Phil\DddBundle\Domain\Exception\InvalidValue;
use Phil\TenancyBundle\Domain\Placement\Placement;
use Phil\TenancyBundle\Domain\ValueObject\TenantId;
use Phil\TenancyDatabaseBundle\Application\Contract\PlacedTenants;
use Phil\TenancyDatabaseBundle\Application\Contract\PlacementStore;
use Phil\TenancyDatabaseBundle\Infrastructure\Doctrine\StoreTables;

/**
 * The placement of each tenant, in the table {@see StoreTables::PLACEMENTS} of the shared database: one row per tenant
 * placed, its database name, or `NULL` for the shared database. A tenant with no row is in the shared database.
 *
 * The connection must stay on the shared database whatever the active tenant: by default, a connection of its own
 * pinned there. Each `placementOf()` is one query on the primary key, and nothing is kept between two calls: a tenant
 * moved by another process is found in its new database by the next unit of work.
 *
 * A row is read back as a {@see Placement}: a database name that a hand-written row made invalid is refused with
 * {@see InvalidValue} rather than read as SQL, and the tenant is not served.
 */
final readonly class DbalPlacementStore implements PlacementStore, PlacedTenants
{
    public function __construct(
        private Connection $connection,
    ) {
    }

    /**
     * @throws InvalidValue when the row holds a name that is not a database name
     */
    #[Override]
    public function placementOf(TenantId $tenant): Placement
    {
        $database = $this->connection->fetchOne(\sprintf('SELECT database_name FROM %s WHERE id = ?', StoreTables::PLACEMENTS), [$this->id($tenant)]);

        return \is_string($database) ? Placement::dedicated($database) : Placement::shared();
    }

    #[Override]
    public function place(TenantId $tenant, Placement $placement): void
    {
        StoreTables::upsert(
            $this->connection,
            StoreTables::PLACEMENTS,
            ['id' => $this->id($tenant), 'tenant' => $tenant->value, 'database_name' => $placement->database],
            ['database_name'],
            'placed_at',
        );
    }

    /**
     * The tenants with a database of their own, in the order of their references.
     *
     * @throws InvalidValue when a row holds a value that is not a tenant reference
     */
    #[Override]
    public function placedTenants(): iterable
    {
        $tenants = $this->connection->iterateColumn(\sprintf('SELECT tenant FROM %s WHERE database_name IS NOT NULL ORDER BY tenant', StoreTables::PLACEMENTS));

        foreach ($tenants as $tenant) {
            yield TenantId::fromString(\is_string($tenant) ? $tenant : '');
        }
    }

    private function id(TenantId $tenant): string
    {
        return hash('sha256', $tenant->value);
    }
}
