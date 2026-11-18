<?php

declare(strict_types=1);

namespace Phil\ModerationBundle\Tests\Integration;

use Override;
use Phil\ModerationBundle\Infrastructure\Doctrine\ModerationTables;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * The tables on MySQL 8.4, read from `PHIL_TEST_MYSQL_URL`, whose default collation folds case and accents: the keys
 * take `utf8mb4_bin`.
 */
#[CoversClass(ModerationTables::class)]
final class MySqlModerationTablesCollationTest extends ServerModerationTablesCollationTestCase
{
    public function testTheKeysAreBinaryOnTheServer(): void
    {
        $connection = $this->connection();

        foreach ([
            ModerationTables::REPORTS => ['decision_id', 'id', 'reporter', 'target_id', 'target_type'],
            ModerationTables::DECISIONS => ['author', 'id', 'moderator', 'target_id', 'target_type'],
            ModerationTables::NOTES => ['author', 'id', 'member'],
            ModerationTables::SANDBOX => ['member'],
            ModerationTables::STATEMENTS => ['decision_id', 'id', 'recipient', 'target_id', 'target_type'],
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
