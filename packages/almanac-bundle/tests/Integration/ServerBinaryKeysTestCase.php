<?php

declare(strict_types=1);

namespace Phil\AlmanacBundle\Tests\Integration;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Tools\DsnParser;
use Doctrine\DBAL\Types\Type;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Events;
use Doctrine\ORM\ORMSetup;
use Doctrine\ORM\Tools\SchemaTool;
use Override;
use Phil\AlmanacBundle\Infrastructure\Doctrine\BinaryKeysListener;
use PHPUnit\Framework\TestCase;
use Symfony\Bridge\Doctrine\Types\UuidType;

/**
 * The table the ORM generates on a real server read from the environment: the keys `harvest`, `Harvest`, `HARVEST`
 * and `harvést` are four dates under the unique key, each found by its own key only, and a key written twice is
 * still refused.
 */
abstract class ServerBinaryKeysTestCase extends TestCase
{
    /**
     * Keys that differ only by case or by an accent.
     */
    protected const array KEYS = ['harvest', 'Harvest', 'HARVEST', 'harvést'];

    private ?Connection $connection = null;

    /**
     * The variable of the server's URL.
     */
    abstract protected function variable(): string;

    #[Override]
    protected function setUp(): void
    {
        // The type DoctrineBundle registers for the identifiers of symfony/uid.
        if (!Type::hasType(UuidType::NAME)) {
            Type::addType(UuidType::NAME, UuidType::class);
        }
    }

    public function testKeysThatDifferByCaseOrAccentAreKeptApart(): void
    {
        $connection = $this->connection();
        $this->createTables($this->schema(true));

        foreach (self::KEYS as $i => $key) {
            $this->insert($i, $key);
        }

        foreach (self::KEYS as $key) {
            self::assertSame([$key], $connection->fetchFirstColumn('SELECT date_key FROM phil_almanac_date WHERE date_key = ?', [$key]), $key);
        }
    }

    public function testTheSameKeyIsStillRefusedTwice(): void
    {
        $this->createTables($this->schema(true));
        $this->insert(0, 'harvest');

        $this->expectException(UniqueConstraintViolationException::class);
        $this->insert(1, 'harvest');
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
     * The schema of the bundle's table, with the listener as the bundle registers it, or without it as before.
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
     * The bundle's table, created afresh: only that one, since the server is shared with the tests of other packages.
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

    private function insert(int $i, string $key): void
    {
        $this->connection()->insert('phil_almanac_date', [
            'id' => \sprintf('00000000-0000-7000-8000-%012d', $i),
            'date_key' => $key,
            'title' => 'Harvest',
            'rule' => 'march 1',
            'days' => 1,
            'all_day' => true,
            'tags' => '[]',
        ], ['id' => 'uuid', 'all_day' => 'boolean']);
    }
}
