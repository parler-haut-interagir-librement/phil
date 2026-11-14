<?php

declare(strict_types=1);

namespace Phil\TenancyDatabaseBundle\Tests\Unit\Infrastructure\Availability;

use Phil\TenancyBundle\Domain\Placement\Placement;
use Phil\TenancyBundle\Domain\ValueObject\TenantId;
use Phil\TenancyDatabaseBundle\Infrastructure\Availability\CacheTenantDatabaseAvailability;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;

#[CoversClass(CacheTenantDatabaseAvailability::class)]
final class CacheTenantDatabaseAvailabilityTest extends TestCase
{
    public function testADatabaseIsSuspendedUntilItIsResumed(): void
    {
        $availability = new CacheTenantDatabaseAvailability(new ArrayAdapter());
        $retail = Placement::dedicated('tenant_retail');

        self::assertFalse($availability->isDatabaseSuspended($retail));

        $availability->suspendDatabase($retail);
        self::assertTrue($availability->isDatabaseSuspended($retail));
        self::assertFalse($availability->isDatabaseSuspended(Placement::dedicated('tenant_wholesale')));
        self::assertFalse($availability->isDatabaseSuspended(Placement::shared()));

        $availability->resumeDatabase($retail);
        self::assertFalse($availability->isDatabaseSuspended($retail));
    }

    public function testTheSharedDatabaseIsNotADedicatedOneNamedShared(): void
    {
        $availability = new CacheTenantDatabaseAvailability(new ArrayAdapter());
        $availability->suspendDatabase(Placement::dedicated('shared'));

        self::assertFalse($availability->isDatabaseSuspended(Placement::shared()));

        $availability->suspendDatabase(Placement::shared());
        $availability->resumeDatabase(Placement::dedicated('shared'));
        self::assertTrue($availability->isDatabaseSuspended(Placement::shared()));
    }

    public function testATenantIsSuspendedUntilItIsResumed(): void
    {
        $pool = new ArrayAdapter();
        $availability = new CacheTenantDatabaseAvailability($pool);
        // A reference with characters a cache key refuses.
        $tenant = TenantId::fromString('shop/{retail}@north');

        $availability->suspendTenant($tenant);
        self::assertTrue($availability->isTenantSuspended($tenant));
        self::assertFalse($availability->isTenantSuspended(TenantId::fromString('wholesale')));
        self::assertSame(['tenant.'.hash('sha256', 'shop/{retail}@north')], array_keys($pool->getValues()));

        $availability->resumeTenant($tenant);
        self::assertFalse($availability->isTenantSuspended($tenant));
    }

    public function testASuspensionIsSharedByEveryInstanceOnThePool(): void
    {
        $pool = new ArrayAdapter();
        new CacheTenantDatabaseAvailability($pool)->suspendDatabase(Placement::shared());

        self::assertTrue(new CacheTenantDatabaseAvailability($pool)->isDatabaseSuspended(Placement::shared()));
        self::assertSame(['database.shared' => 'b:1;'], $pool->getValues());
    }

    public function testADedicatedDatabaseHasAKeyOfItsName(): void
    {
        $pool = new ArrayAdapter();
        $availability = new CacheTenantDatabaseAvailability($pool);

        $availability->suspendDatabase(Placement::dedicated('tenant_retail'));
        $availability->suspendTenant(TenantId::fromString('wholesale'));

        // The pool keeps each entry serialized: `b:1;` is `true`.
        self::assertSame(['database.dedicated.tenant_retail' => 'b:1;', 'tenant.'.hash('sha256', 'wholesale') => 'b:1;'], $pool->getValues());
    }
}
