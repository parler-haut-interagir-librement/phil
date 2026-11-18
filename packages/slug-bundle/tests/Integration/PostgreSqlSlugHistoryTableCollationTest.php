<?php

declare(strict_types=1);

namespace Phil\SlugBundle\Tests\Integration;

use Override;
use Phil\SlugBundle\Infrastructure\Doctrine\SlugHistoryTable;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * The tables on PostgreSQL 17, read from `PHIL_TEST_POSTGRESQL_URL`, whose default collation is deterministic: the
 * columns keep it and still tell the keys apart.
 */
#[CoversClass(SlugHistoryTable::class)]
final class PostgreSqlSlugHistoryTableCollationTest extends ServerSlugHistoryTableCollationTestCase
{
    #[Override]
    protected function variable(): string
    {
        return 'PHIL_TEST_POSTGRESQL_URL';
    }
}
