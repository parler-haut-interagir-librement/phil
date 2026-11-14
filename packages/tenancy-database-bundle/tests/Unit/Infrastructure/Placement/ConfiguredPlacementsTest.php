<?php

declare(strict_types=1);

namespace Phil\TenancyDatabaseBundle\Tests\Unit\Infrastructure\Placement;

use Phil\DddBundle\Domain\Exception\InvalidValue;
use Phil\TenancyBundle\Domain\Placement\Placement;
use Phil\TenancyBundle\Domain\ValueObject\TenantId;
use Phil\TenancyDatabaseBundle\Infrastructure\Placement\ConfiguredPlacements;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ConfiguredPlacements::class)]
final class ConfiguredPlacementsTest extends TestCase
{
    public function testAListedTenantHasItsDatabaseAndEveryOtherIsShared(): void
    {
        $placements = new ConfiguredPlacements(['wholesale' => 'tenant_wholesale', '42' => 'tenant_42']);

        self::assertTrue($placements->placementOf(TenantId::fromString('wholesale'))->equals(Placement::dedicated('tenant_wholesale')));
        self::assertTrue($placements->placementOf(TenantId::fromString('42'))->equals(Placement::dedicated('tenant_42')), 'A numeric reference, which PHP keeps as an integer key.');
        self::assertTrue($placements->placementOf(TenantId::fromString('retail'))->isShared());
        self::assertSame(['wholesale', '42'], array_map(static fn (TenantId $tenant): string => $tenant->value, [...$placements->placedTenants()]));
    }

    public function testNoTenantIsListedByDefault(): void
    {
        $placements = new ConfiguredPlacements();

        self::assertSame([], [...$placements->placedTenants()]);
        self::assertTrue($placements->placementOf(TenantId::fromString('retail'))->isShared());
    }

    public function testAnInvalidDatabaseNameIsRefused(): void
    {
        $this->expectException(InvalidValue::class);

        new ConfiguredPlacements(['wholesale' => 'Tenant-Wholesale']);
    }

    public function testAnInvalidTenantReferenceIsRefused(): void
    {
        $this->expectException(InvalidValue::class);

        new ConfiguredPlacements([' wholesale' => 'tenant_wholesale']);
    }
}
