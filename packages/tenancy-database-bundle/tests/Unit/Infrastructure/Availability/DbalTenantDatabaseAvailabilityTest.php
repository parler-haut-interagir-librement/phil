<?php

declare(strict_types=1);

namespace Phil\TenancyDatabaseBundle\Tests\Unit\Infrastructure\Availability;

use Phil\TenancyBundle\Domain\Placement\Placement;
use Phil\TenancyBundle\Domain\ValueObject\TenantId;
use Phil\TenancyDatabaseBundle\Infrastructure\Availability\DbalTenantDatabaseAvailability;
use Phil\TenancyDatabaseBundle\Tests\Unit\Fixture\StoreDatabase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(DbalTenantDatabaseAvailability::class)]
final class DbalTenantDatabaseAvailabilityTest extends TestCase
{
    public function testADatabaseIsSuspendedUntilItIsResumed(): void
    {
        $connection = StoreDatabase::connection();
        $availability = new DbalTenantDatabaseAvailability($connection);
        $retail = Placement::dedicated('tenant_retail');

        self::assertFalse($availability->isDatabaseSuspended($retail));

        $availability->suspendDatabase($retail);
        $availability->suspendDatabase($retail);
        self::assertTrue($availability->isDatabaseSuspended($retail));
        self::assertTrue(new DbalTenantDatabaseAvailability($connection)->isDatabaseSuspended($retail));
        self::assertFalse($availability->isDatabaseSuspended(Placement::dedicated('tenant_wholesale')));
        self::assertFalse($availability->isDatabaseSuspended(Placement::shared()));
        self::assertSame(
            [['id' => hash('sha256', 'database:tenant_retail'), 'kind' => 'database', 'subject' => 'tenant_retail']],
            $connection->fetchAllAssociative('SELECT id, kind, subject FROM phil_tenancy_suspension'),
        );

        $availability->resumeDatabase($retail);
        self::assertFalse($availability->isDatabaseSuspended($retail));
        self::assertSame([], $connection->fetchAllAssociative('SELECT id FROM phil_tenancy_suspension'));
    }

    public function testTheSharedDatabaseIsNotADedicatedOneNamedShared(): void
    {
        $connection = StoreDatabase::connection();
        $availability = new DbalTenantDatabaseAvailability($connection);
        $availability->suspendDatabase(Placement::dedicated('shared'));

        self::assertFalse($availability->isDatabaseSuspended(Placement::shared()));

        $availability->suspendDatabase(Placement::shared());
        $availability->resumeDatabase(Placement::dedicated('shared'));
        self::assertTrue($availability->isDatabaseSuspended(Placement::shared()));
        self::assertFalse($availability->isDatabaseSuspended(Placement::dedicated('shared')));
        self::assertSame(
            [['id' => hash('sha256', 'shared'), 'kind' => 'shared', 'subject' => null]],
            $connection->fetchAllAssociative('SELECT id, kind, subject FROM phil_tenancy_suspension'),
        );

        $availability->resumeDatabase(Placement::shared());
        self::assertFalse($availability->isDatabaseSuspended(Placement::shared()));
    }

    public function testATenantIsSuspendedUntilItIsResumed(): void
    {
        $connection = StoreDatabase::connection();
        $availability = new DbalTenantDatabaseAvailability($connection);
        $tenant = TenantId::fromString('Shop/{retail}@north');

        $availability->suspendTenant($tenant);
        $availability->suspendTenant($tenant);
        self::assertTrue($availability->isTenantSuspended($tenant));
        self::assertFalse($availability->isTenantSuspended(TenantId::fromString('shop/{retail}@north')));
        self::assertFalse($availability->isTenantSuspended(TenantId::fromString('wholesale')));
        self::assertSame(
            [['id' => hash('sha256', 'tenant:Shop/{retail}@north'), 'kind' => 'tenant', 'subject' => 'Shop/{retail}@north']],
            $connection->fetchAllAssociative('SELECT id, kind, subject FROM phil_tenancy_suspension'),
        );

        $availability->resumeTenant($tenant);
        self::assertFalse($availability->isTenantSuspended($tenant));
    }

    public function testATenantAndADatabaseOfTheSameNameAreApart(): void
    {
        $availability = new DbalTenantDatabaseAvailability(StoreDatabase::connection());
        $availability->suspendTenant(TenantId::fromString('retail'));

        self::assertFalse($availability->isDatabaseSuspended(Placement::dedicated('retail')));

        $availability->suspendDatabase(Placement::dedicated('retail'));
        $availability->resumeTenant(TenantId::fromString('retail'));
        self::assertTrue($availability->isDatabaseSuspended(Placement::dedicated('retail')));
        self::assertFalse($availability->isTenantSuspended(TenantId::fromString('retail')));
    }
}
