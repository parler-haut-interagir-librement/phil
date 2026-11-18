<?php

declare(strict_types=1);

namespace Phil\ReferencesBundle\Tests\Integration;

use Override;
use Phil\ReferencesBundle\Infrastructure\Doctrine\UsageTable;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * The tables on MySQL 8.4, read from `PHIL_TEST_MYSQL_URL`, whose default collation folds case and accents: the keys
 * take `utf8mb4_bin`.
 */
#[CoversClass(UsageTable::class)]
final class MySqlUsageTableCollationTest extends ServerUsageTableCollationTestCase
{
    public function testTheKeysAreBinaryOnTheServer(): void
    {
        $connection = $this->connection();

        foreach ([
            UsageTable::NAME => ['field', 'source_id', 'source_kind', 'target_id', 'target_kind', 'usage_key'],
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
