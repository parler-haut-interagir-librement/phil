<?php

declare(strict_types=1);

namespace Phil\CommentsBundle\Tests\Integration;

use Override;
use Phil\CommentsBundle\Infrastructure\Doctrine\BinaryKeysListener;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * The tables on MySQL 8.4, read from `PHIL_TEST_MYSQL_URL`, whose default collation folds case and accents: the
 * identifiers take `utf8mb4_bin`.
 */
#[CoversClass(BinaryKeysListener::class)]
final class MySqlBinaryKeysTest extends ServerBinaryKeysTestCase
{
    public function testTheIdentifiersAreBinaryOnTheServer(): void
    {
        $connection = $this->connection();

        foreach ([
            'phil_comment' => ['author', 'id', 'thread'],
            'phil_comment_thread' => ['author', 'id', 'resolved_by', 'subject'],
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
