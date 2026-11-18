<?php

declare(strict_types=1);

namespace Phil\NotificationsBundle\Tests\Integration;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Tools\DsnParser;
use Override;
use Phil\NotificationsBundle\Infrastructure\Doctrine\NotificationTables;
use PHPUnit\Framework\TestCase;

/**
 * The tables on a real server read from the environment: the preferences of `member:Ann`, `member:ann`, `member:ANN` and `member:ánn` are four rows.
 */
abstract class ServerNotificationTablesCollationTestCase extends TestCase
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
            ['settings' => '{}', 'recipient' => 'member:Ann'],
            ['settings' => '{}', 'recipient' => 'member:ann'],
            ['settings' => '{}', 'recipient' => 'member:ANN'],
            ['settings' => '{}', 'recipient' => 'member:ánn'],
        ] as $row) {
            $connection->insert(NotificationTables::PREFERENCES, $row);
        }

        foreach (['member:Ann', 'member:ann', 'member:ANN', 'member:ánn'] as $value) {
            self::assertSame([$value], $connection->fetchFirstColumn('SELECT recipient FROM '.NotificationTables::PREFERENCES.' WHERE recipient = ?', [$value]), $value);
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
        $schema = NotificationTables::configure(Schema::editor()->create(), true, true, $connection->getDatabasePlatform());

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
