<?php

declare(strict_types=1);

namespace Phil\UsageMeter\Tests\Integration;

use Override;
use Phil\UsageMeter\Dbal\DbalUsageStore;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * The DBAL store on PostgreSQL 17, read from `PHIL_TEST_POSTGRESQL_URL`.
 */
#[CoversClass(DbalUsageStore::class)]
final class PostgreSqlUsageStoreTest extends ServerUsageStoreTestCase
{
    #[Override]
    protected function variable(): string
    {
        return 'PHIL_TEST_POSTGRESQL_URL';
    }
}
