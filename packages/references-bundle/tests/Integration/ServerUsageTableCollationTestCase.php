<?php

declare(strict_types=1);

namespace Phil\ReferencesBundle\Tests\Integration;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Tools\DsnParser;
use Override;
use Phil\ReferencesBundle\Infrastructure\Doctrine\UsageTable;
use PHPUnit\Framework\TestCase;

/**
 * The tables on a real server read from the environment: the usages of the sources `Doc-A`, `doc-a`, `DOC-A` and `döc-a` are read apart.
 */
abstract class ServerUsageTableCollationTestCase extends TestCase
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
            ['usage_key' => bin2hex(random_bytes(32)), 'source_kind' => 'page', 'field' => 'body', 'target_kind' => 'media', 'target_id' => '7', 'source_id' => 'Doc-A'],
            ['usage_key' => bin2hex(random_bytes(32)), 'source_kind' => 'page', 'field' => 'body', 'target_kind' => 'media', 'target_id' => '7', 'source_id' => 'doc-a'],
            ['usage_key' => bin2hex(random_bytes(32)), 'source_kind' => 'page', 'field' => 'body', 'target_kind' => 'media', 'target_id' => '7', 'source_id' => 'DOC-A'],
            ['usage_key' => bin2hex(random_bytes(32)), 'source_kind' => 'page', 'field' => 'body', 'target_kind' => 'media', 'target_id' => '7', 'source_id' => 'döc-a'],
        ] as $row) {
            $connection->insert(UsageTable::NAME, $row);
        }

        foreach (['Doc-A', 'doc-a', 'DOC-A', 'döc-a'] as $value) {
            self::assertSame([$value], $connection->fetchFirstColumn('SELECT source_id FROM '.UsageTable::NAME.' WHERE source_id = ?', [$value]), $value);
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
        $schema = UsageTable::configure(Schema::editor()->create(), $connection->getDatabasePlatform());

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
