<?php

declare(strict_types=1);

namespace Phil\OrganizationsBundle\Tests\Integration;

use Override;
use Phil\OrganizationsBundle\Infrastructure\Dbal\DbalOrganizationStore;
use Phil\OrganizationsBundle\Infrastructure\Dbal\DbalRows;
use Phil\OrganizationsBundle\Infrastructure\Dbal\OrganizationTables;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * The DBAL store on MySQL 8.4, read from `PHIL_TEST_MYSQL_URL`.
 */
#[CoversClass(DbalOrganizationStore::class)]
#[CoversClass(DbalRows::class)]
#[CoversClass(OrganizationTables::class)]
final class MySqlStoreTest extends ServerStoreTestCase
{
    #[Override]
    protected function variable(): string
    {
        return 'PHIL_TEST_MYSQL_URL';
    }
}
