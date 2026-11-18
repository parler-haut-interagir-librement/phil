<?php

declare(strict_types=1);

namespace Phil\TenancyDatabaseBundle\Tests\Integration;

use Override;
use Phil\TenancyDatabaseBundle\Infrastructure\Doctrine\StoreTables;
use Phil\TenancyDatabaseBundle\Infrastructure\Placement\DbalPlacementStore;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * The placements on PostgreSQL 17, read from `PHIL_TEST_POSTGRESQL_URL`.
 */
#[CoversClass(DbalPlacementStore::class)]
#[CoversClass(StoreTables::class)]
final class PostgreSqlPlacementKeysTest extends ServerPlacementKeysTestCase
{
    #[Override]
    protected function variable(): string
    {
        return 'PHIL_TEST_POSTGRESQL_URL';
    }
}
