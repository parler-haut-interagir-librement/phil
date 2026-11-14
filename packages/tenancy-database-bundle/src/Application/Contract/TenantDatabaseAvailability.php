<?php

declare(strict_types=1);

namespace Phil\TenancyDatabaseBundle\Application\Contract;

use Phil\TenancyBundle\Domain\Placement\Placement;
use Phil\TenancyBundle\Domain\ValueObject\TenantId;

/**
 * Which databases and tenants cannot be served for now: a database whose last migration failed, a tenant being moved.
 * Requests in them are answered with a `503`, messages are retried; commands still run, so that an operator can
 * migrate again.
 *
 * Shared by every process that serves the application: the web servers, the workers and the console.
 */
interface TenantDatabaseAvailability
{
    public function suspendDatabase(Placement $database): void;

    public function resumeDatabase(Placement $database): void;

    public function isDatabaseSuspended(Placement $database): bool;

    public function suspendTenant(TenantId $tenant): void;

    public function resumeTenant(TenantId $tenant): void;

    public function isTenantSuspended(TenantId $tenant): bool;
}
