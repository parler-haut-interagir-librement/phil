<?php

declare(strict_types=1);

namespace Phil\TenancyDatabaseBundle\Tests\Unit\Infrastructure\Availability;

use Phil\TenancyBundle\Domain\Placement\Placement;
use Phil\TenancyBundle\Domain\ValueObject\TenantId;
use Phil\TenancyDatabaseBundle\Domain\Exception\TenantDatabaseUnavailable;
use Phil\TenancyDatabaseBundle\Infrastructure\Availability\TenantAvailabilityGuard;
use Phil\TenancyDatabaseBundle\Tests\Unit\Fixture\FixedPlacements;
use Phil\TenancyDatabaseBundle\Tests\Unit\Fixture\MemoryAvailability;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(TenantAvailabilityGuard::class)]
final class TenantAvailabilityGuardTest extends TestCase
{
    public function testATenantWhoseDatabaseIsAvailableIsServed(): void
    {
        $this->expectNotToPerformAssertions();

        new TenantAvailabilityGuard(new FixedPlacements(), new MemoryAvailability())->check(TenantId::fromString('retail'));
    }

    public function testATenantBeingMovedIsRefused(): void
    {
        $availability = new MemoryAvailability();
        $availability->suspendTenant(TenantId::fromString('retail'));

        $this->expectException(TenantDatabaseUnavailable::class);
        $this->expectExceptionMessageIsOrContains('The tenant "retail" is being moved to another database.');

        new TenantAvailabilityGuard(new FixedPlacements(), $availability)->check(TenantId::fromString('retail'));
    }

    public function testATenantWhoseDatabaseIsSuspendedIsRefused(): void
    {
        $availability = new MemoryAvailability();
        $availability->suspendDatabase(Placement::dedicated('tenant_wholesale'));

        $guard = new TenantAvailabilityGuard(new FixedPlacements(['wholesale' => 'tenant_wholesale']), $availability);
        $guard->check(TenantId::fromString('retail'));

        $this->expectException(TenantDatabaseUnavailable::class);
        $this->expectExceptionMessageIsOrContains('The database "tenant_wholesale" of the tenant "wholesale" failed its last migration.');

        $guard->check(TenantId::fromString('wholesale'));
    }
}
