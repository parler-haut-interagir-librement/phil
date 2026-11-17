<?php

declare(strict_types=1);

namespace Phil\LtiBundle\Tests\Unit\Fixture;

use Doctrine\DBAL\Configuration;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Phil\LtiBundle\Infrastructure\Dbal\LtiTables;

/**
 * A connection with the tables of the bundle: SQLite in memory, or the server a test names.
 */
final class Stores
{
    public static function sqlite(LtiTables $tables = new LtiTables(), ?Interleaving $interleaving = null): Connection
    {
        return self::prepare(DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true], self::configuration($interleaving)), $tables);
    }

    /**
     * The configuration of a connection, with the interleaving of writes a test sets.
     */
    public static function configuration(?Interleaving $interleaving): Configuration
    {
        return new Configuration()->setMiddlewares($interleaving instanceof Interleaving ? [$interleaving] : []);
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
