<?php

declare(strict_types=1);

namespace Phil\StockroomBundle\Tests\Unit\Fixture;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Phil\Stockroom\Contract\Balances;
use Phil\Stockroom\Contract\Containers;
use Phil\Stockroom\Contract\Items;
use Phil\Stockroom\Contract\Journal;
use Phil\Stockroom\Contract\LoadLists;
use Phil\Stockroom\Memory\InMemoryBalances;
use Phil\Stockroom\Memory\InMemoryContainers;
use Phil\Stockroom\Memory\InMemoryItems;
use Phil\Stockroom\Memory\InMemoryJournal;
use Phil\Stockroom\Memory\InMemoryLoadLists;
use Phil\StockroomBundle\Infrastructure\Dbal\DbalBalances;
use Phil\StockroomBundle\Infrastructure\Dbal\DbalContainers;
use Phil\StockroomBundle\Infrastructure\Dbal\DbalItems;
use Phil\StockroomBundle\Infrastructure\Dbal\DbalJournal;
use Phil\StockroomBundle\Infrastructure\Dbal\DbalLoadLists;
use Phil\StockroomBundle\Infrastructure\Dbal\StockroomTables;

/**
 * The five stores, in memory or in the tables of a connection, so that one test holds both to the same contract.
 */
final readonly class Stores
{
    public function __construct(
        public Containers $containers,
        public Items $items,
        public Balances $balances,
        public Journal $journal,
        public LoadLists $loadLists,
        public ?Connection $connection = null,
        public StockroomTables $tables = new StockroomTables(),
    ) {
    }

    public static function memory(): self
    {
        return new self(new InMemoryContainers(), new InMemoryItems(), new InMemoryBalances(), new InMemoryJournal(), new InMemoryLoadLists());
    }

    /**
     * The stores on SQLite in memory, their tables created.
     */
    public static function sqlite(StockroomTables $tables = new StockroomTables()): self
    {
        return self::on(DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]), $tables);
    }

    /**
     * The stores on a connection, their tables dropped if they are there and created again.
     */
    public static function on(Connection $connection, StockroomTables $tables = new StockroomTables()): self
    {
        $manager = $connection->createSchemaManager();

        foreach (array_reverse($tables->names()) as $name) {
            if ($manager->tableExists($name)) {
                $manager->dropTable($name);
            }
        }

        foreach ($tables->definitions($connection->getDatabasePlatform()) as $table) {
            $manager->createTable($table);
        }

        return new self(
            new DbalContainers($connection, $tables),
            new DbalItems($connection, $tables),
            new DbalBalances($connection, $tables),
            new DbalJournal($connection, $tables),
            new DbalLoadLists($connection, $tables),
            $connection,
            $tables,
        );
    }
}
