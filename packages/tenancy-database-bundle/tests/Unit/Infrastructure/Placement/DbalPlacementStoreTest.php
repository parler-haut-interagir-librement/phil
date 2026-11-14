<?php

declare(strict_types=1);

namespace Phil\TenancyDatabaseBundle\Tests\Unit\Infrastructure\Placement;

use Phil\DddBundle\Domain\Exception\InvalidValue;
use Phil\TenancyBundle\Domain\Placement\Placement;
use Phil\TenancyBundle\Domain\ValueObject\TenantId;
use Phil\TenancyDatabaseBundle\Infrastructure\Placement\DbalPlacementStore;
use Phil\TenancyDatabaseBundle\Tests\Unit\Fixture\StoreDatabase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(DbalPlacementStore::class)]
final class DbalPlacementStoreTest extends TestCase
{
    public function testATenantWithNoRowIsInTheSharedDatabase(): void
    {
        $store = new DbalPlacementStore(StoreDatabase::connection());

        self::assertTrue($store->placementOf(TenantId::fromString('retail'))->isShared());
        self::assertSame([], iterator_to_array($store->placedTenants()));
    }

    public function testAPlacementIsRecordedThenReplaced(): void
    {
        $connection = StoreDatabase::connection();
        $store = new DbalPlacementStore($connection);
        $retail = TenantId::fromString('retail');

        $store->place($retail, Placement::dedicated('tenant_retail'));
        self::assertTrue($store->placementOf($retail)->equals(Placement::dedicated('tenant_retail')));
        self::assertTrue(new DbalPlacementStore($connection)->placementOf($retail)->equals(Placement::dedicated('tenant_retail')));

        $store->place($retail, Placement::dedicated('tenant_moved'));
        self::assertTrue($store->placementOf($retail)->equals(Placement::dedicated('tenant_moved')));

        $store->place($retail, Placement::shared());
        self::assertTrue($store->placementOf($retail)->isShared());
        self::assertSame([], iterator_to_array($store->placedTenants()));

        self::assertSame(
            [['id' => hash('sha256', 'retail'), 'tenant' => 'retail', 'database_name' => null]],
            $connection->fetchAllAssociative('SELECT id, tenant, database_name FROM phil_tenancy_placement'),
        );
    }

    public function testThePlacedTenantsAreThoseWithADatabaseOfTheirOwnInTheOrderOfTheirReferences(): void
    {
        $store = new DbalPlacementStore(StoreDatabase::connection());
        $store->place(TenantId::fromString('wholesale'), Placement::dedicated('tenant_wholesale'));
        $store->place(TenantId::fromString('retail'), Placement::shared());
        $store->place(TenantId::fromString('north'), Placement::dedicated('tenant_north'));

        self::assertSame(['north', 'wholesale'], array_map(static fn (TenantId $tenant): string => $tenant->value, [...$store->placedTenants()]));
    }

    public function testReferencesAreComparedByteForByte(): void
    {
        $store = new DbalPlacementStore(StoreDatabase::connection());
        $store->place(TenantId::fromString('Rose'), Placement::dedicated('tenant_rose'));

        self::assertTrue($store->placementOf(TenantId::fromString('rose'))->isShared());
        self::assertTrue($store->placementOf(TenantId::fromString('Rose'))->isDedicated());
    }

    public function testARowWhoseDatabaseNameIsNotOneIsRefused(): void
    {
        $connection = StoreDatabase::connection();
        $connection->insert('phil_tenancy_placement', ['id' => hash('sha256', 'retail'), 'tenant' => 'retail', 'database_name' => 'x; DROP TABLE invoice']);

        $this->expectException(InvalidValue::class);

        new DbalPlacementStore($connection)->placementOf(TenantId::fromString('retail'));
    }

    public function testARowWhoseTenantIsNotAReferenceIsRefused(): void
    {
        $connection = StoreDatabase::connection();
        $connection->insert('phil_tenancy_placement', ['id' => 'x', 'tenant' => ' retail', 'database_name' => 'tenant_retail']);

        $this->expectException(InvalidValue::class);

        iterator_to_array(new DbalPlacementStore($connection)->placedTenants());
    }
}
