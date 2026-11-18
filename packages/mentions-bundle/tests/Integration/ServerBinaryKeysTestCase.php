<?php

declare(strict_types=1);

namespace Phil\MentionsBundle\Tests\Integration;

use Doctrine\Common\EventManager;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Tools\DsnParser;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Events;
use Doctrine\ORM\ORMSetup;
use Doctrine\ORM\Tools\SchemaTool;
use Override;
use Phil\MentionsBundle\Infrastructure\Doctrine\BinaryKeysListener;
use PHPUnit\Framework\TestCase;

/**
 * The tables the ORM generates on a real server read from the environment: the mentions of `member:Ann`, `member:ann`, `member:ANN` and `member:ánn` in one subject are four rows.
 */
abstract class ServerBinaryKeysTestCase extends TestCase
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
            ['id' => bin2hex(random_bytes(18)), 'subject' => 'post:1', 'author' => 'member:bob', 'created_at' => '2026-11-17 10:00:00', 'member' => 'member:Ann'],
            ['id' => bin2hex(random_bytes(18)), 'subject' => 'post:1', 'author' => 'member:bob', 'created_at' => '2026-11-17 10:00:00', 'member' => 'member:ann'],
            ['id' => bin2hex(random_bytes(18)), 'subject' => 'post:1', 'author' => 'member:bob', 'created_at' => '2026-11-17 10:00:00', 'member' => 'member:ANN'],
            ['id' => bin2hex(random_bytes(18)), 'subject' => 'post:1', 'author' => 'member:bob', 'created_at' => '2026-11-17 10:00:00', 'member' => 'member:ánn'],
        ] as $row) {
            $connection->insert('phil_mention', $row);
        }

        foreach (['member:Ann', 'member:ann', 'member:ANN', 'member:ánn'] as $value) {
            self::assertSame([$value], $connection->fetchFirstColumn('SELECT member FROM phil_mention WHERE member = ?', [$value]), $value);
        }
    }

    #[Override]
    protected function tearDown(): void
    {
        $this->connection?->close();
        $this->connection = null;
    }

    /**
     * The bundle's tables, created afresh from the ORM's schema: only those, since the server is shared with the tests of
     * other packages.
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
        $config = ORMSetup::createAttributeMetadataConfig([\dirname(__DIR__, 3).'/src/Domain/Model'], true);
        $config->enableNativeLazyObjects(true);

        $events = new EventManager();
        $events->addEventListener(Events::loadClassMetadata, new BinaryKeysListener());

        $entityManager = new EntityManager($connection, $config, $events);
        $schema = new SchemaTool($entityManager)->getSchemaFromMetadata(array_map($entityManager->getClassMetadata(...), array_keys(BinaryKeysListener::FIELDS)));
        $manager = $connection->createSchemaManager();

        foreach ($schema->getTables() as $table) {
            $connection->executeStatement('DROP TABLE IF EXISTS '.$table->getObjectName()->toString());
        }

        foreach ($schema->getTables() as $table) {
            $manager->createTable($table);
        }

        return $connection;
    }
}
