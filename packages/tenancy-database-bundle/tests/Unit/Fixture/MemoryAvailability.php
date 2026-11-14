<?php

declare(strict_types=1);

namespace Phil\TenancyDatabaseBundle\Tests\Unit\Fixture;

use Override;
use Phil\TenancyBundle\Domain\Placement\Placement;
use Phil\TenancyBundle\Domain\ValueObject\TenantId;
use Phil\TenancyDatabaseBundle\Application\Contract\TenantDatabaseAvailability;

/**
 * The suspended databases and tenants, in memory.
 */
final class MemoryAvailability implements TenantDatabaseAvailability
{
    /** @var array<string, true> */
    public array $databases = [];

    /** @var array<string, true> */
    public array $tenants = [];

    #[Override]
    public function suspendDatabase(Placement $database): void
    {
        $this->databases[$database->toString()] = true;
    }

    #[Override]
    public function resumeDatabase(Placement $database): void
    {
        unset($this->databases[$database->toString()]);
    }

    #[Override]
    public function isDatabaseSuspended(Placement $database): bool
    {
        return isset($this->databases[$database->toString()]);
    }

    #[Override]
    public function suspendTenant(TenantId $tenant): void
    {
        $this->tenants[$tenant->value] = true;
    }

    #[Override]
    public function resumeTenant(TenantId $tenant): void
    {
        unset($this->tenants[$tenant->value]);
    }

    #[Override]
    public function isTenantSuspended(TenantId $tenant): bool
    {
        return isset($this->tenants[$tenant->value]);
    }
}
