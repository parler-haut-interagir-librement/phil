<?php

declare(strict_types=1);

namespace Phil\TenancyDatabaseBundle\Tests\Integration;

use Override;
use Phil\TenancyDatabaseBundle\Infrastructure\Doctrine\StoreTables;
use Phil\TenancyDatabaseBundle\Infrastructure\Placement\DbalPlacementStore;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * The placements on MySQL 8.4, whose default collation folds case and accents, read from `PHIL_TEST_MYSQL_URL`.
 */
#[CoversClass(DbalPlacementStore::class)]
#[CoversClass(StoreTables::class)]
final class MySqlPlacementKeysTest extends ServerPlacementKeysTestCase
{
    #[Override]
    protected function variable(): string
    {
        return 'PHIL_TEST_MYSQL_URL';
    }
}
