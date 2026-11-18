<?php

declare(strict_types=1);

namespace Phil\CheckinBundle\Tests\Integration;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Tools\DsnParser;
use Override;
use Phil\CheckinBundle\Infrastructure\Doctrine\DoctrineCheckInLedger;
use PHPUnit\Framework\TestCase;

/**
 * The tables on a real server read from the environment: the credentials `Badge-7`, `badge-7`, `BADGE-7` and `bädge-7` each take the sequence 1.
 */
abstract class ServerCheckInLedgerCollationTestCase extends TestCase
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
            ['id' => bin2hex(random_bytes(16)), 'sequence' => 1, 'checkpoint' => 'door', 'direction' => 'in', 'outcome' => 'admitted', 'device' => 'd1', 'offline' => 0, 'scanned_at' => 1, 'recorded_at' => 1, 'credential_id' => 'Badge-7'],
            ['id' => bin2hex(random_bytes(16)), 'sequence' => 1, 'checkpoint' => 'door', 'direction' => 'in', 'outcome' => 'admitted', 'device' => 'd1', 'offline' => 0, 'scanned_at' => 1, 'recorded_at' => 1, 'credential_id' => 'badge-7'],
            ['id' => bin2hex(random_bytes(16)), 'sequence' => 1, 'checkpoint' => 'door', 'direction' => 'in', 'outcome' => 'admitted', 'device' => 'd1', 'offline' => 0, 'scanned_at' => 1, 'recorded_at' => 1, 'credential_id' => 'BADGE-7'],
            ['id' => bin2hex(random_bytes(16)), 'sequence' => 1, 'checkpoint' => 'door', 'direction' => 'in', 'outcome' => 'admitted', 'device' => 'd1', 'offline' => 0, 'scanned_at' => 1, 'recorded_at' => 1, 'credential_id' => 'bädge-7'],
        ] as $row) {
            $connection->insert(DoctrineCheckInLedger::TABLE, $row);
        }

        foreach (['Badge-7', 'badge-7', 'BADGE-7', 'bädge-7'] as $value) {
            self::assertSame([$value], $connection->fetchFirstColumn('SELECT credential_id FROM '.DoctrineCheckInLedger::TABLE.' WHERE credential_id = ?', [$value]), $value);
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
        $schema = new DoctrineCheckInLedger($connection)->configureSchema(Schema::editor()->create());

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
