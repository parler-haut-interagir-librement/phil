<?php

declare(strict_types=1);

namespace Phil\StockroomBundle\Tests\Integration;

use Doctrine\DBAL\Connection;
use Override;
use Phil\StockroomBundle\Infrastructure\Dbal\DbalBalances;
use Phil\StockroomBundle\Infrastructure\Dbal\DbalContainers;
use Phil\StockroomBundle\Infrastructure\Dbal\DbalItems;
use Phil\StockroomBundle\Infrastructure\Dbal\DbalJournal;
use Phil\StockroomBundle\Infrastructure\Dbal\DbalLoadLists;
use Phil\StockroomBundle\Infrastructure\Dbal\StockroomTables;
use Phil\StockroomBundle\Infrastructure\Dbal\StockroomTransactions;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

#[CoversClass(DbalContainers::class)]
#[CoversClass(DbalItems::class)]
#[CoversClass(DbalBalances::class)]
#[CoversClass(DbalJournal::class)]
#[CoversClass(DbalLoadLists::class)]
#[CoversClass(StockroomTables::class)]
#[CoversClass(StockroomTransactions::class)]
#[Group('mysql')]
final class MySqlStoresTest extends ServerStoresTestCase
{
    public function testEveryIdentifierIsBinaryOnTheServer(): void
    {
        $stores = $this->stores();
        $connection = $stores->connection;
        self::assertNotNull($connection);
        $declared = [];

        foreach ($stores->tables->definitions($connection->getDatabasePlatform()) as $table) {
            foreach ($table->getColumns() as $column) {
                if ('ascii_bin' === $column->getCollation()) {
                    $declared[] = $table->getObjectName()->toString().'.'.$column->getObjectName()->toString();
                }
            }
        }

        sort($declared);

        self::assertNotSame([], $declared);
        self::assertSame($declared, $connection->fetchFirstColumn(
            "SELECT CONCAT(TABLE_NAME, '.', COLUMN_NAME) AS name FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME LIKE 'phil\\_stockroom\\_test\\_%' AND COLLATION_NAME = 'ascii_bin' ORDER BY name",
        ));
    }

    #[Override]
    protected function variable(): string
    {
        return 'PHIL_TEST_MYSQL_URL';
    }

    #[Override]
    protected function shortLockWait(Connection $connection): void
    {
        $connection->executeStatement('SET SESSION innodb_lock_wait_timeout = 1');
    }
}
