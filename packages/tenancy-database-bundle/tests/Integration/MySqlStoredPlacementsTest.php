<?php

declare(strict_types=1);

namespace Phil\TenancyDatabaseBundle\Tests\Integration;

use Override;
use Phil\TenancyDatabaseBundle\Infrastructure\Availability\DbalTenantDatabaseAvailability;
use Phil\TenancyDatabaseBundle\Infrastructure\Doctrine\PinnedConnection;
use Phil\TenancyDatabaseBundle\Infrastructure\Doctrine\StoreSchemaListener;
use Phil\TenancyDatabaseBundle\Infrastructure\Doctrine\StoreTables;
use Phil\TenancyDatabaseBundle\Infrastructure\Placement\DbalPlacementStore;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * The placements and the suspensions in the tables of the shared database, on the server of
 * {@see MySqlTenantDatabasesTest}.
 */
#[CoversClass(DbalPlacementStore::class)]
#[CoversClass(DbalTenantDatabaseAvailability::class)]
#[CoversClass(StoreTables::class)]
#[CoversClass(StoreSchemaListener::class)]
#[CoversClass(PinnedConnection::class)]
#[Group('mysql')]
final class MySqlStoredPlacementsTest extends TenantDatabasesTestCase
{
    use StoredPlacementsTests;

    #[Override]
    protected static function storedPlacements(): bool
    {
        return true;
    }

    #[Override]
    protected static function variables(): array
    {
        return ['PHIL_TENANCY_DATABASE_TEST_MYSQL_URL', 'PHIL_TEST_MYSQL_URL'];
    }
}
