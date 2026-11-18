<?php

declare(strict_types=1);

namespace Phil\AccessCodes\Tests\Integration;

use Override;
use Phil\AccessCodes\Dbal\DbalCodeStore;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * The tables on PostgreSQL 17, read from `PHIL_TEST_POSTGRESQL_URL`, whose default collation is deterministic: the
 * columns keep it and still tell the keys apart.
 */
#[CoversClass(DbalCodeStore::class)]
final class PostgreSqlCodeStoreCollationTest extends ServerCodeStoreCollationTestCase
{
    #[Override]
    protected function variable(): string
    {
        return 'PHIL_TEST_POSTGRESQL_URL';
    }
}
