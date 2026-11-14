<?php

declare(strict_types=1);

namespace Phil\TenancyDatabaseBundle\Tests\Unit\Fixture;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Schema\Schema;
use Phil\TenancyDatabaseBundle\Infrastructure\Doctrine\StoreTables;

/**
 * An in-memory SQLite with the tables of the durable stores.
 */
final class StoreDatabase
{
    public static function connection(): Connection
    {
        $connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
        $connection->createSchemaManager()->createSchemaObjects(StoreTables::configure(Schema::editor()->create()));

        return $connection;
    }
}
