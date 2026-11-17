<?php

declare(strict_types=1);

namespace Phil\OutboxBundle\Tests\Unit\Fixture;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Phil\OutboxBundle\Infrastructure\Doctrine\OutboxTable;

/**
 * The connections the tests run on: SQLite in memory, with the outbox table created.
 */
final class Databases
{
    private function __construct()
    {
    }

    public static function sqlite(OutboxTable $table = new OutboxTable()): Connection
    {
        $connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
        self::create($connection, $table);

        return $connection;
    }

    public static function create(Connection $connection, OutboxTable $table = new OutboxTable()): void
    {
        $platform = $connection->getDatabasePlatform();

        foreach ($platform->getCreateTableSQL($table->definition($platform)) as $statement) {
            $connection->executeStatement($statement);
        }
    }

    public static function drop(Connection $connection, OutboxTable $table = new OutboxTable()): void
    {
        $connection->executeStatement('DROP TABLE IF EXISTS '.$table->name);
    }
}
