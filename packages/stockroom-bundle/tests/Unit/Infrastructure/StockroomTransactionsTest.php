<?php

declare(strict_types=1);

namespace Phil\StockroomBundle\Tests\Unit\Infrastructure;

use Doctrine\DBAL\Configuration;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Platforms\MySQLPlatform;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Platforms\SQLitePlatform;
use InvalidArgumentException;
use Phil\Stockroom\Item;
use Phil\Stockroom\Place;
use Phil\StockroomBundle\Infrastructure\Dbal\StockroomTables;
use Phil\StockroomBundle\Infrastructure\Dbal\StockroomTransactions;
use Phil\StockroomBundle\Tests\Unit\Fixture\RecordingMiddleware;
use Phil\StockroomBundle\Tests\Unit\Fixture\Stores;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;

#[CoversClass(StockroomTransactions::class)]
final class StockroomTransactionsTest extends TestCase
{
    public function testTheLockStatementOfEachDatabase(): void
    {
        $transactions = new StockroomTransactions(null, new StockroomTables('props_'));

        self::assertSame('SELECT id FROM props_item WHERE id IN (?, ?) ORDER BY id FOR UPDATE', $transactions->lockStatement(new MySQLPlatform(), 2));
        self::assertSame('SELECT id FROM props_item WHERE id IN (?) ORDER BY id FOR UPDATE', $transactions->lockStatement(new PostgreSQLPlatform(), 1));
        self::assertSame('SELECT id FROM props_item WHERE id IN (?, ?, ?) ORDER BY id', $transactions->lockStatement(new SQLitePlatform(), 3));
        self::assertSame('SELECT id FROM props_item WHERE id IN (?) ORDER BY id FOR UPDATE', $transactions->lockStatement(new MySQLPlatform(), 0));
    }

    public function testTheWorkIsCommittedItsItemsLockedOnceInTheirOrder(): void
    {
        $recording = new RecordingMiddleware();
        $stores = Stores::on(DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true], new Configuration()->setMiddlewares([$recording])));
        $connection = $stores->connection;
        self::assertNotNull($connection);
        $transactions = new StockroomTransactions($connection, $stores->tables);
        $recording->statements = [];

        $result = $transactions->run(static function () use ($stores): string {
            $stores->balances->set('sword', Place::container('shelf'), 3);

            return 'done';
        }, 'sword', 'axe', 'sword', 'Bow');

        self::assertSame('done', $result);
        self::assertSame([['SELECT id FROM phil_stockroom_item WHERE id IN (?, ?, ?) ORDER BY id', ['Bow', 'axe', 'sword']]], $recording->startingWith('SELECT id FROM'));
        self::assertSame(3, $stores->balances->quantity('sword', Place::container('shelf')));
        self::assertFalse($connection->isTransactionActive());

        $recording->statements = [];
        self::assertSame(7, $transactions->run(static fn (): int => 7));
        self::assertSame([], $recording->startingWith('SELECT id FROM'));
    }

    public function testAWorkThatFailsLeavesNothing(): void
    {
        $stores = Stores::sqlite();
        $connection = $stores->connection;
        self::assertNotNull($connection);
        $transactions = new StockroomTransactions($connection, $stores->tables);

        try {
            $transactions->run(static function () use ($stores): never {
                $stores->items->save(new Item('sword', 'SW-1', 'Foam sword'));

                throw new RuntimeException('Refused.');
            }, 'sword');
        } catch (RuntimeException $runtimeException) {
            self::assertSame('Refused.', $runtimeException->getMessage());
        }

        self::assertNull($stores->items->get('sword'));
        self::assertFalse($connection->isTransactionActive());
    }

    public function testAWorkJoinsTheTransactionOfTheCaller(): void
    {
        $stores = Stores::sqlite();
        $connection = $stores->connection;
        self::assertNotNull($connection);
        $transactions = new StockroomTransactions($connection, $stores->tables);

        $connection->beginTransaction();
        $transactions->run(static function () use ($stores): void {
            $stores->items->save(new Item('sword', 'SW-1', 'Foam sword'));
        }, 'sword');
        self::assertTrue($connection->isTransactionActive());
        $connection->rollBack();

        self::assertNull($stores->items->get('sword'));
    }

    public function testWithoutAConnectionTheWorkRuns(): void
    {
        $transactions = new StockroomTransactions(null, new StockroomTables());

        self::assertSame('ran', $transactions->run(static fn (): string => 'ran', 'sword'));
        self::assertFalse($transactions->uses(DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true])));
    }

    public function testTheConnectionItUses(): void
    {
        $connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
        $transactions = new StockroomTransactions($connection, new StockroomTables());

        self::assertTrue($transactions->uses($connection));
        self::assertFalse($transactions->uses(DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true])));
    }

    public function testATransactionLocksSoManyItemsAtMost(): void
    {
        $transactions = new StockroomTransactions(null, new StockroomTables());
        $items = array_map(static fn (int $n): string => 'item-'.$n, range(1, StockroomTransactions::MAX_ITEMS));

        self::assertSame('ran', $transactions->run(static fn (): string => 'ran', ...$items, ...$items));

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageIsOrContains('A transaction of the stockroom locks at most 500 items.');
        $transactions->run(static fn (): string => 'ran', ...[...$items, 'one-more']);
    }
}
