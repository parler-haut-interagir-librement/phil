<?php

declare(strict_types=1);

namespace Phil\LtiBundle\Tests\Unit\Fixture;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Phil\LtiBundle\Infrastructure\Dbal\LtiTables;

/**
 * A connection with the tables of the bundle: SQLite in memory, or the server a test names.
 */
final class Stores
{
    public static function sqlite(LtiTables $tables = new LtiTables()): Connection
    {
        return self::prepare(DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]), $tables);
    }

    public static function prepare(Connection $connection, LtiTables $tables): Connection
    {
        $manager = $connection->createSchemaManager();

        foreach (array_reverse($tables->names()) as $name) {
            if ($manager->tablesExist([$name])) {
                $connection->executeStatement('DROP TABLE '.$name);
            }
        }

        foreach ($connection->getDatabasePlatform()->getCreateTablesSQL($tables->definitions($connection->getDatabasePlatform())) as $statement) {
            $connection->executeStatement($statement);
        }

        return $connection;
    }
}
