<?php

declare(strict_types=1);

namespace Phil\AccessCodes\Tests\Integration;

use Override;
use Phil\AccessCodes\Dbal\DbalCodeStore;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * The DBAL store on PostgreSQL 17, read from `PHIL_TEST_POSTGRESQL_URL`.
 */
#[CoversClass(DbalCodeStore::class)]
final class PostgreSqlCodeStoreTest extends ServerCodeStoreTestCase
{
    #[Override]
    protected function variable(): string
    {
        return 'PHIL_TEST_POSTGRESQL_URL';
    }
}
