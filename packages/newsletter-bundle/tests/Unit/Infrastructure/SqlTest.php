<?php

declare(strict_types=1);

namespace Phil\NewsletterBundle\Tests\Unit\Infrastructure;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Platforms\MariaDBPlatform;
use Doctrine\DBAL\Platforms\OraclePlatform;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Platforms\SQLitePlatform;
use LogicException;
use Phil\NewsletterBundle\Infrastructure\Dbal\Sql;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

#[CoversClass(Sql::class)]
final class SqlTest extends TestCase
{
    public function testEachDialectLeavesARowOfTheSameKeyAlone(): void
    {
        self::assertTrue(Sql::insertUnlessPresent($this->connection(new PostgreSQLPlatform(), 'INSERT INTO t (a, b) VALUES (?, ?) ON CONFLICT (a, b) DO NOTHING', [1, 'x'], 1), 't', ['a' => 1, 'b' => 'x'], ['a', 'b']));
        self::assertTrue(Sql::insertUnlessPresent($this->connection(new MariaDBPlatform(), 'INSERT IGNORE INTO t (a, b) VALUES (?, ?)', [1, 'x'], 1), 't', ['a' => 1, 'b' => 'x'], ['a']));
        self::assertTrue(Sql::insertUnlessPresent($this->connection(new SQLitePlatform(), 'INSERT OR IGNORE INTO t (a, b) VALUES (?, ?)', [1, 'x'], 1), 't', ['a' => 1, 'b' => 'x'], ['a']));
        self::assertTrue(Sql::insertUnlessPresent($this->connection(new SQLitePlatform(), 'INSERT OR IGNORE INTO t (a) VALUES (?)', [1], '1'), 't', ['a' => 1], ['a']));
        self::assertFalse(Sql::insertUnlessPresent($this->connection(new SQLitePlatform(), 'INSERT OR IGNORE INTO t (a) VALUES (?)', [1], 0), 't', ['a' => 1], ['a']));
    }

    public function testAnotherServerIsRefused(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessageIsOrContains('The newsletter runs on PostgreSQL, MySQL, MariaDB and SQLite; not on '.OraclePlatform::class.'.');

        $connection = self::createStub(Connection::class);
        $connection->method('getDatabasePlatform')->willReturn(new OraclePlatform());

        Sql::insertUnlessPresent($connection, 't', ['a' => 1], ['a']);
    }

    public function testARowInsertedIsNotUpdated(): void
    {
        $connection = $this->connection(new SQLitePlatform(), 'INSERT OR IGNORE INTO t (a, b) VALUES (?, ?)', [1, 'x'], 1);
        $connection->expects(self::never())->method('update');

        Sql::upsert($connection, 't', ['a' => 1, 'b' => 'x'], ['a']);
    }

    public function testARowThatIsThereIsUpdatedWithoutItsKey(): void
    {
        $connection = $this->connection(new SQLitePlatform(), 'INSERT OR IGNORE INTO t (a, b, c) VALUES (?, ?, ?)', [1, 'x', 'y'], 0);
        $connection->expects(self::once())->method('update')->with('t', ['c' => 'y'], ['a' => 1, 'b' => 'x']);

        Sql::upsert($connection, 't', ['a' => 1, 'b' => 'x', 'c' => 'y'], ['a', 'b']);
    }

    /**
     * @param list<int|string>   $parameters
     * @param int|numeric-string $affected
     */
    private function connection(AbstractPlatform $platform, string $sql, array $parameters, int|string $affected): Connection&MockObject
    {
        $connection = $this->createMock(Connection::class);
        $connection->method('getDatabasePlatform')->willReturn($platform);
        $connection->expects(self::atMost(1))->method('executeStatement')->with($sql, $parameters)->willReturn($affected);

        return $connection;
    }
}
