<?php

declare(strict_types=1);

namespace Phil\MediaBundle\Tests\Integration;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Tools\DsnParser;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Events;
use Doctrine\ORM\ORMSetup;
use Doctrine\ORM\Tools\SchemaTool;
use Override;
use Phil\MediaBundle\Infrastructure\Doctrine\BinaryKeysListener;
use PHPUnit\Framework\TestCase;

/**
 * The tables the ORM generates on a real server read from the environment: the collections `Doc-A`, `doc-a`, `DOC-A` and `dóc-a` are four collections, each found by its own key only.
 */
abstract class ServerBinaryKeysTestCase extends TestCase
{
    /**
     * Keys that differ only by case or by an accent.
     */
    protected const array KEYS = ['Doc-A', 'doc-a', 'DOC-A', 'dóc-a'];

    private ?Connection $connection = null;

    /**
     * The variable of the server's URL.
     */
    abstract protected function variable(): string;

    public function testKeysThatDifferByCaseOrAccentAreKeptApart(): void
    {
        $connection = $this->connection();
        $this->createTables($this->schema(true));

        foreach (self::KEYS as $i => $value) {
            $connection->insert('phil_media_collection', ['id' => \sprintf('00000000-0000-7000-8000-%012d', $i), 'collection_key' => $value, 'name' => 'Doc'], []);
        }

        foreach (self::KEYS as $value) {
            self::assertSame([$value], $connection->fetchFirstColumn('SELECT collection_key FROM phil_media_collection WHERE collection_key = ?', [$value]), $value);
        }
    }

    #[Override]
    protected function tearDown(): void
    {
        $this->connection?->close();
        $this->connection = null;
    }

    protected function connection(): Connection
    {
        if ($this->connection instanceof Connection) {
            return $this->connection;
        }

        $url = getenv($this->variable());

        if (!\is_string($url) || '' === $url) {
            self::markTestSkipped(\sprintf('Set %s to run this test against a real server.', $this->variable()));
        }

        return $this->connection = DriverManager::getConnection(new DsnParser(['mysql' => 'pdo_mysql', 'postgresql' => 'pdo_pgsql', 'postgres' => 'pdo_pgsql'])->parse($url));
    }

    /**
     * The schema of the bundle's tables, with the listener as the bundle registers it, or without it as before.
     */
    protected function schema(bool $listening): Schema
    {
        $config = ORMSetup::createAttributeMetadataConfig([\dirname(__DIR__, 2).'/src/Domain/Model'], true);
        $config->enableNativeLazyObjects(true);

        $entityManager = new EntityManager($this->connection(), $config);

        if ($listening) {
            $entityManager->getEventManager()->addEventListener(Events::loadClassMetadata, new BinaryKeysListener());
        }

        return new SchemaTool($entityManager)->getSchemaFromMetadata(array_map($entityManager->getClassMetadata(...), array_keys(BinaryKeysListener::FIELDS)));
    }

    /**
     * The bundle's tables, created afresh: only those, since the server is shared with the tests of other packages.
     */
    protected function createTables(Schema $schema): void
    {
        $connection = $this->connection();
        $manager = $connection->createSchemaManager();

        foreach (array_reverse($schema->getTables()) as $table) {
            $connection->executeStatement('DROP TABLE IF EXISTS '.$table->getObjectName()->toString());
        }

        foreach ($schema->getTables() as $table) {
            $manager->createTable($table);
        }
    }
}
