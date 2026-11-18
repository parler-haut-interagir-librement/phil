<?php

declare(strict_types=1);

namespace Phil\ModerationBundle\Tests\Integration;

use Override;
use Phil\ModerationBundle\Infrastructure\Doctrine\ModerationTables;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * The tables on PostgreSQL 17, read from `PHIL_TEST_POSTGRESQL_URL`, whose default collation is deterministic: the
 * columns keep it and still tell the keys apart.
 */
#[CoversClass(ModerationTables::class)]
final class PostgreSqlModerationTablesCollationTest extends ServerModerationTablesCollationTestCase
{
    #[Override]
    protected function variable(): string
    {
        return 'PHIL_TEST_POSTGRESQL_URL';
    }
}
