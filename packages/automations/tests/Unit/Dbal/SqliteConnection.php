<?php

declare(strict_types=1);

namespace Phil\Automations\Tests\Unit\Dbal;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Schema\Schema;

/**
 * An SQLite database in memory, and the migration of its schema.
 */
final class SqliteConnection
{
    public static function memory(): Connection
    {
        return DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
    }

    /**
     * @param callable(Schema): Schema $configure
     */
    public static function migrate(Connection $connection, callable $configure): void
    {
        $manager = $connection->createSchemaManager();
        $manager->migrateSchema($configure($manager->introspectSchema()));
    }
}
