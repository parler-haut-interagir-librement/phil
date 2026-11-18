<?php

declare(strict_types=1);

namespace Phil\TermsBundle\Tests\Integration;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Tools\DsnParser;
use Override;
use Phil\TermsBundle\Infrastructure\Doctrine\TermsTables;
use PHPUnit\Framework\TestCase;

/**
 * The tables on a real server read from the environment: an acceptance by `Ann` is never one by `ann`, `ANN` or `ánn`.
 */
abstract class ServerTermsTablesCollationTestCase extends TestCase
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
            ['policy' => 'terms', 'version' => 1, 'accepted_at' => '2026-11-17 10:00:00', 'channel' => 'web', 'member' => 'Ann'],
            ['policy' => 'terms', 'version' => 1, 'accepted_at' => '2026-11-17 10:00:00', 'channel' => 'web', 'member' => 'ann'],
            ['policy' => 'terms', 'version' => 1, 'accepted_at' => '2026-11-17 10:00:00', 'channel' => 'web', 'member' => 'ANN'],
            ['policy' => 'terms', 'version' => 1, 'accepted_at' => '2026-11-17 10:00:00', 'channel' => 'web', 'member' => 'ánn'],
        ] as $row) {
            $connection->insert(TermsTables::ACCEPTANCES, $row);
        }

        foreach (['Ann', 'ann', 'ANN', 'ánn'] as $value) {
            self::assertSame([$value], $connection->fetchFirstColumn('SELECT member FROM '.TermsTables::ACCEPTANCES.' WHERE member = ?', [$value]), $value);
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
        $schema = TermsTables::configure(Schema::editor()->create(), $connection->getDatabasePlatform());

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
