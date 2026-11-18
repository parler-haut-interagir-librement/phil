<?php

declare(strict_types=1);

namespace Phil\ReferencesBundle\Tests\Integration;

use Override;
use Phil\ReferencesBundle\Infrastructure\Doctrine\UsageTable;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * The tables on PostgreSQL 17, read from `PHIL_TEST_POSTGRESQL_URL`, whose default collation is deterministic: the
 * columns keep it and still tell the keys apart.
 */
#[CoversClass(UsageTable::class)]
final class PostgreSqlUsageTableCollationTest extends ServerUsageTableCollationTestCase
{
    #[Override]
    protected function variable(): string
    {
        return 'PHIL_TEST_POSTGRESQL_URL';
    }
}
