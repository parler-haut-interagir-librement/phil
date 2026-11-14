<?php

declare(strict_types=1);

namespace Phil\TenancyDatabaseBundle\Infrastructure\Availability;

use Override;
use Phil\TenancyBundle\Domain\Placement\Placement;
use Phil\TenancyBundle\Domain\ValueObject\TenantId;
use Phil\TenancyDatabaseBundle\Application\Contract\TenantDatabaseAvailability;
use Psr\Cache\CacheItemPoolInterface;

/**
 * The suspended databases and tenants, kept in a cache pool every process shares and that no tenant scopes:
 * `phil_tenancy_database.availability`, built on `cache.app` and listed among the global pools of
 * `phil/tenancy-bundle`, with `availability: cache`. It is not durable: an entry has no lifetime, but a pool that
 * evicts entries under pressure, or a cache cleared on deployment, forgets a suspension, and the tenants of a database
 * whose migration failed are served again. The default, {@see DbalTenantDatabaseAvailability}, keeps them in a table.
 *
 * A key holds no tenant reference in clear: it is the hash of the reference, so any reference makes a valid key.
 */
final readonly class CacheTenantDatabaseAvailability implements TenantDatabaseAvailability
{
    public function __construct(
        private CacheItemPoolInterface $pool,
    ) {
    }

    #[Override]
    public function suspendDatabase(Placement $database): void
    {
        $this->suspend($this->databaseKey($database));
    }

    #[Override]
    public function resumeDatabase(Placement $database): void
    {
        $this->pool->deleteItem($this->databaseKey($database));
    }

    #[Override]
    public function isDatabaseSuspended(Placement $database): bool
    {
        return $this->pool->hasItem($this->databaseKey($database));
    }

    #[Override]
    public function suspendTenant(TenantId $tenant): void
    {
        $this->suspend($this->tenantKey($tenant));
    }

    #[Override]
    public function resumeTenant(TenantId $tenant): void
    {
        $this->pool->deleteItem($this->tenantKey($tenant));
    }

    #[Override]
    public function isTenantSuspended(TenantId $tenant): bool
    {
        return $this->pool->hasItem($this->tenantKey($tenant));
    }

    private function suspend(string $key): void
    {
        $this->pool->save($this->pool->getItem($key)->set(true));
    }

    private function databaseKey(Placement $database): string
    {
        // `shared` is not a dedicated name: a database named "shared" gets its own key.
        return null === $database->database ? 'database.shared' : 'database.dedicated.'.$database->database;
    }

    private function tenantKey(TenantId $tenant): string
    {
        return 'tenant.'.hash('sha256', $tenant->value);
    }
}
