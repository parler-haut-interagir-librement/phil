<?php

declare(strict_types=1);

namespace Phil\SettingsBundle\Tests\Integration;

use Override;
use Phil\SettingsBundle\Infrastructure\Doctrine\SettingTables;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * The tables on MySQL 8.4, read from `PHIL_TEST_MYSQL_URL`, whose default collation folds case and accents: the keys
 * take `utf8mb4_bin`.
 */
#[CoversClass(SettingTables::class)]
final class MySqlSettingTablesCollationTest extends ServerSettingTablesCollationTestCase
{
    public function testTheKeysAreBinaryOnTheServer(): void
    {
        $connection = $this->connection();

        foreach ([
            SettingTables::SETTINGS => ['scope', 'setting_key', 'subject'],
            SettingTables::MIGRATIONS => ['version'],
        ] as $table => $columns) {
            self::assertSame($columns, $connection->fetchFirstColumn(
                "SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLLATION_NAME = 'utf8mb4_bin' ORDER BY COLUMN_NAME",
                [$table],
            ), $table);
        }
    }

    #[Override]
    protected function variable(): string
    {
        return 'PHIL_TEST_MYSQL_URL';
    }
}
