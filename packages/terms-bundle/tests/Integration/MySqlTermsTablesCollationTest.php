<?php

declare(strict_types=1);

namespace Phil\TermsBundle\Tests\Integration;

use Override;
use Phil\TermsBundle\Infrastructure\Doctrine\TermsTables;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * The tables on MySQL 8.4, read from `PHIL_TEST_MYSQL_URL`, whose default collation folds case and accents: the keys
 * take `utf8mb4_bin`.
 */
#[CoversClass(TermsTables::class)]
final class MySqlTermsTablesCollationTest extends ServerTermsTablesCollationTestCase
{
    public function testTheKeysAreBinaryOnTheServer(): void
    {
        $connection = $this->connection();

        foreach ([
            TermsTables::VERSIONS => ['policy'],
            TermsTables::ACCEPTANCES => ['member', 'policy'],
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
