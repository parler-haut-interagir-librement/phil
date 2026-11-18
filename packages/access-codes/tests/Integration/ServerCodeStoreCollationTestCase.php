<?php

declare(strict_types=1);

namespace Phil\AccessCodes\Tests\Integration;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Tools\DsnParser;
use Override;
use Phil\AccessCodes\Dbal\DbalCodeStore;
use PHPUnit\Framework\TestCase;

/**
 * The tables on a real server read from the environment: the batches `Spring`, `spring`, `SPRING` and `spríng` are four rows.
 */
abstract class ServerCodeStoreCollationTestCase extends TestCase
{
    private ?Connection $connection = null;

    /**
     * The variable of the server's URL.
     */
    abstract protected function variable(): string;

    public function testKeysThatDifferByCaseOrAccentAreKeptApart(): void
    {
        $connection = $this->connection();

        foreach ([
            ['name' => 'A batch', 'size' => 1, 'created_at' => 1, 'id' => 'Spring'],
            ['name' => 'A batch', 'size' => 1, 'created_at' => 1, 'id' => 'spring'],
            ['name' => 'A batch', 'size' => 1, 'created_at' => 1, 'id' => 'SPRING'],
            ['name' => 'A batch', 'size' => 1, 'created_at' => 1, 'id' => 'spríng'],
        ] as $row) {
            $connection->insert(DbalCodeStore::BATCH_TABLE, $row);
        }

        foreach (['Spring', 'spring', 'SPRING', 'spríng'] as $value) {
            self::assertSame([$value], $connection->fetchFirstColumn('SELECT id FROM '.DbalCodeStore::BATCH_TABLE.' WHERE id = ?', [$value]), $value);
        }
    }

    #[Override]
    protected function tearDown(): void
    {
        $this->connection?->close();
        $this->connection = null;
    }

    /**
     * The package's tables, created afresh: only those, since the server is shared with the tests of other packages.
     */
    protected function connection(): Connection
    {
        if ($this->connection instanceof Connection) {
            return $this->connection;
        }

        $url = getenv($this->variable());

        if (!\is_string($url) || '' === $url) {
            self::markTestSkipped(\sprintf('Set %s to run this test against a real server.', $this->variable()));
        }

        $connection = DriverManager::getConnection(new DsnParser(['mysql' => 'pdo_mysql', 'postgresql' => 'pdo_pgsql', 'postgres' => 'pdo_pgsql'])->parse($url));
        $this->connection = $connection;
        $schema = new DbalCodeStore($connection)->configureSchema(Schema::editor()->create());

        foreach ($schema->getTables() as $table) {
            $connection->executeStatement('DROP TABLE IF EXISTS '.$table->getObjectName()->toString());
        }

        $manager = $connection->createSchemaManager();

        foreach ($schema->getTables() as $table) {
            $manager->createTable($table);
        }

        return $connection;
    }
}
