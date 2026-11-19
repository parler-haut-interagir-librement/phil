<?php

declare(strict_types=1);

namespace Phil\Habits\Tests\Unit\Dbal;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Tools\DsnParser;
use Override;
use Phil\Habits\Contract\CheckInStore;
use Phil\Habits\Contract\HabitStore;
use Phil\Habits\Dbal\DbalCheckInStore;
use Phil\Habits\Dbal\DbalHabitStore;
use Phil\Habits\Tests\Unit\StoreTestCase;

/**
 * The contract of the stores on the database of a URL, their tables dropped and created again from their own schema
 * before each test.
 */
abstract class DbalStoreTestCase extends StoreTestCase
{
    protected ?Connection $connection = null;

    /**
     * The URL of the database, or `null` to skip the test.
     */
    abstract protected function url(): ?string;

    #[Override]
    protected function setUp(): void
    {
        $url = $this->url();

        if (null === $url) {
            self::markTestSkipped('Set the URL of the server to run this test against it.');
        }

        $this->connection = DriverManager::getConnection(new DsnParser(['mysql' => 'pdo_mysql', 'postgresql' => 'pdo_pgsql', 'sqlite' => 'pdo_sqlite'])->parse($url));
        $schemaManager = $this->connection->createSchemaManager();

        foreach ([DbalHabitStore::TABLE, DbalCheckInStore::TABLE] as $table) {
            if ($schemaManager->tablesExist([$table])) {
                $this->connection->executeStatement('DROP TABLE '.$table);
            }
        }

        $schema = new DbalCheckInStore($this->connection)->configureSchema(new DbalHabitStore($this->connection)->configureSchema(Schema::editor()->create()));

        foreach ($schema->toSql($this->connection->getDatabasePlatform()) as $statement) {
            $this->connection->executeStatement($statement);
        }
    }

    #[Override]
    protected function tearDown(): void
    {
        $this->connection?->close();
    }

    #[Override]
    protected function habitStore(): HabitStore
    {
        return new DbalHabitStore($this->connection());
    }

    #[Override]
    protected function checkInStore(): CheckInStore
    {
        return new DbalCheckInStore($this->connection());
    }

    protected function connection(): Connection
    {
        $this->assertInstanceOf(Connection::class, $this->connection);

        return $this->connection;
    }
}
