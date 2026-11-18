<?php

declare(strict_types=1);

namespace Phil\SearchBundle\Tests\Integration;

use Override;
use Phil\SearchBundle\Infrastructure\Doctrine\SearchTable;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * The tables on MySQL 8.4, read from `PHIL_TEST_MYSQL_URL`, whose default collation folds case and accents: the keys
 * take `utf8mb4_bin`.
 */
#[CoversClass(SearchTable::class)]
final class MySqlSearchTableCollationTest extends ServerSearchTableCollationTestCase
{
    public function testTheKeysAreBinaryOnTheServer(): void
    {
        $connection = $this->connection();

        foreach ([
            SearchTable::NAME => ['document_id', 'index_name'],
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
