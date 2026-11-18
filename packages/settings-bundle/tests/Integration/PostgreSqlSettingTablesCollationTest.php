<?php

declare(strict_types=1);

namespace Phil\SettingsBundle\Tests\Integration;

use Override;
use Phil\SettingsBundle\Infrastructure\Doctrine\SettingTables;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * The tables on PostgreSQL 17, read from `PHIL_TEST_POSTGRESQL_URL`, whose default collation is deterministic: the
 * columns keep it and still tell the keys apart.
 */
#[CoversClass(SettingTables::class)]
final class PostgreSqlSettingTablesCollationTest extends ServerSettingTablesCollationTestCase
{
    #[Override]
    protected function variable(): string
    {
        return 'PHIL_TEST_POSTGRESQL_URL';
    }
}
