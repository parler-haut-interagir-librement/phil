<?php

declare(strict_types=1);

namespace Phil\SearchBundle\Tests\Integration;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Tools\DsnParser;
use Override;
use Phil\SearchBundle\Infrastructure\Doctrine\SearchTable;
use PHPUnit\Framework\TestCase;

/**
 * The tables on a real server read from the environment: the documents `Post-A`, `post-a`, `POST-A` and `pöst-a` of one index are four rows.
 */
abstract class ServerSearchTableCollationTestCase extends TestCase
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
            ['index_name' => 'posts', 'content' => 'words', 'filters' => '{}', 'document_id' => 'Post-A'],
            ['index_name' => 'posts', 'content' => 'words', 'filters' => '{}', 'document_id' => 'post-a'],
            ['index_name' => 'posts', 'content' => 'words', 'filters' => '{}', 'document_id' => 'POST-A'],
            ['index_name' => 'posts', 'content' => 'words', 'filters' => '{}', 'document_id' => 'pöst-a'],
        ] as $row) {
            $connection->insert(SearchTable::NAME, $row);
        }

        foreach (['Post-A', 'post-a', 'POST-A', 'pöst-a'] as $value) {
            self::assertSame([$value], $connection->fetchFirstColumn('SELECT document_id FROM '.SearchTable::NAME.' WHERE document_id = ?', [$value]), $value);
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
        $schema = SearchTable::configure(Schema::editor()->create(), $connection->getDatabasePlatform());

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
