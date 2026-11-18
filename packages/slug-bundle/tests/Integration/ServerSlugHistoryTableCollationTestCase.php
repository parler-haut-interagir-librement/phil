<?php

declare(strict_types=1);

namespace Phil\SlugBundle\Tests\Integration;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Tools\DsnParser;
use Override;
use Phil\SlugBundle\Infrastructure\Doctrine\SlugHistoryTable;
use PHPUnit\Framework\TestCase;

/**
 * The tables on a real server read from the environment: the same former slug in the scopes `Blog`, `blog`, `BLOG` and `blög` is four rows.
 */
abstract class ServerSlugHistoryTableCollationTestCase extends TestCase
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
            ['former_slug' => 'old-title', 'subject' => 'post-7', 'current_slug' => 'new-title', 'recorded_at' => '2026-11-17 10:00:00', 'scope' => 'Blog'],
            ['former_slug' => 'old-title', 'subject' => 'post-7', 'current_slug' => 'new-title', 'recorded_at' => '2026-11-17 10:00:00', 'scope' => 'blog'],
            ['former_slug' => 'old-title', 'subject' => 'post-7', 'current_slug' => 'new-title', 'recorded_at' => '2026-11-17 10:00:00', 'scope' => 'BLOG'],
            ['former_slug' => 'old-title', 'subject' => 'post-7', 'current_slug' => 'new-title', 'recorded_at' => '2026-11-17 10:00:00', 'scope' => 'blög'],
        ] as $row) {
            $connection->insert(SlugHistoryTable::NAME, $row);
        }

        foreach (['Blog', 'blog', 'BLOG', 'blög'] as $value) {
            self::assertSame([$value], $connection->fetchFirstColumn('SELECT scope FROM '.SlugHistoryTable::NAME.' WHERE scope = ?', [$value]), $value);
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
        $schema = SlugHistoryTable::configure(Schema::editor()->create(), $connection->getDatabasePlatform());

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
