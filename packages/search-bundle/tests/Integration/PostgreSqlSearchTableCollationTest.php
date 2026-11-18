<?php

declare(strict_types=1);

namespace Phil\SearchBundle\Tests\Integration;

use Override;
use Phil\SearchBundle\Infrastructure\Doctrine\SearchTable;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * The tables on PostgreSQL 17, read from `PHIL_TEST_POSTGRESQL_URL`, whose default collation is deterministic: the
 * columns keep it and still tell the keys apart.
 */
#[CoversClass(SearchTable::class)]
final class PostgreSqlSearchTableCollationTest extends ServerSearchTableCollationTestCase
{
    #[Override]
    protected function variable(): string
    {
        return 'PHIL_TEST_POSTGRESQL_URL';
    }
}
