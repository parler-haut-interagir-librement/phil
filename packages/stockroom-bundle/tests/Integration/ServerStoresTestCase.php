<?php

declare(strict_types=1);

namespace Phil\StockroomBundle\Tests\Integration;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Exception;
use Doctrine\DBAL\Tools\DsnParser;
use Override;
use Phil\Stockroom\Item;
use Phil\StockroomBundle\Infrastructure\Dbal\StockroomTables;
use Phil\StockroomBundle\Infrastructure\Dbal\StockroomTransactions;
use Phil\StockroomBundle\Tests\Unit\Fixture\Stores;
use Phil\StockroomBundle\Tests\Unit\Infrastructure\StoresTestCase;

/**
 * The contract of the stores on a real server, read from the environment, and the lock of a transaction seen from a
 * second connection.
 */
abstract class ServerStoresTestCase extends StoresTestCase
{
    /** @var list<Connection> */
    private array $connections = [];

    /**
     * The variable of the server's URL.
     */
    abstract protected function variable(): string;

    #[Override]
    protected function tearDown(): void
    {
        foreach ($this->connections as $connection) {
            $connection->close();
        }
    }

    #[Override]
    protected function stores(): Stores
    {
        return Stores::on($this->connect(), new StockroomTables('phil_stockroom_test_'));
    }

    public function testAnItemLockedByATransactionWaitsForIt(): void
    {
        $stores = $this->stores();
        $connection = $stores->connection;
        self::assertNotNull($connection);
        $stores->items->save(new Item('sword', 'SW-1', 'Foam sword'));
        $other = $this->connect();
        $this->shortLockWait($other);

        new StockroomTransactions($connection, $stores->tables)->run(static function () use ($other, $stores): void {
            try {
                $other->beginTransaction();
                $other->executeQuery(new StockroomTransactions($other, $stores->tables)->lockStatement($other->getDatabasePlatform(), 1), ['sword'])->fetchAllAssociative();
                self::fail('The row of the item was not locked.');
            } catch (Exception $exception) {
                self::assertNotSame('', $exception->getMessage());
            } finally {
                if ($other->isTransactionActive()) {
                    $other->rollBack();
                }
            }
        }, 'sword');
    }

    abstract protected function shortLockWait(Connection $connection): void;

    private function connect(): Connection
    {
        $url = getenv($this->variable());

        if (!\is_string($url) || '' === $url) {
            self::markTestSkipped(\sprintf('Set %s to run this test against a real server.', $this->variable()));
        }

        $connection = DriverManager::getConnection(new DsnParser(['mysql' => 'pdo_mysql', 'postgresql' => 'pdo_pgsql', 'postgres' => 'pdo_pgsql'])->parse($url));
        $this->connections[] = $connection;

        return $connection;
    }
}
