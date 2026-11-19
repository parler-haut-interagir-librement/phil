<?php

declare(strict_types=1);

namespace Phil\DirectoryBundle\Tests\Integration;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Tools\DsnParser;
use Doctrine\DBAL\Types\Type;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Events;
use Doctrine\ORM\ORMSetup;
use Doctrine\ORM\Tools\SchemaTool;
use Override;
use Phil\DirectoryBundle\Domain\ValueObject\ListingId;
use Phil\DirectoryBundle\Infrastructure\Doctrine\BinaryKeysListener;
use Phil\DirectoryBundle\Infrastructure\Doctrine\ListingIdType;
use PHPUnit\Framework\TestCase;

/**
 * The listing table the ORM generates on a real server read from the environment: the listings of the authors `ann`,
 * `Ann`, `ANN` and `ánn`, in the genres of the same names, are each found by their own author and genre only.
 */
abstract class ServerBinaryKeysTestCase extends TestCase
{
    /**
     * Values that differ only by case or by an accent.
     */
    protected const array VALUES = ['ann', 'Ann', 'ANN', 'ánn'];

    private ?Connection $connection = null;

    /**
     * The variable of the server's URL.
     */
    abstract protected function variable(): string;

    public function testValuesThatDifferByCaseOrAccentAreKeptApart(): void
    {
        $connection = $this->connection();
        $this->createTables($this->schema(true));

        foreach (self::VALUES as $value) {
            $connection->insert('phil_directory_listing', ['id' => ListingId::generate(), 'genre' => $value, 'author' => $value, 'editors' => '[]', 'indexing' => 'listed', 'site_listed' => true], ['id' => ListingIdType::NAME, 'site_listed' => 'boolean']);
        }

        foreach (self::VALUES as $value) {
            self::assertSame([$value], $connection->fetchFirstColumn('SELECT author FROM phil_directory_listing WHERE author = ?', [$value]), $value);
            self::assertSame([$value], $connection->fetchFirstColumn('SELECT genre FROM phil_directory_listing WHERE genre = ?', [$value]), $value);
        }
    }

    #[Override]
    protected function setUp(): void
    {
        if (!Type::hasType(ListingIdType::NAME)) {
            Type::addType(ListingIdType::NAME, ListingIdType::class);
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
     * The schema of the listing table, with the listener as the bundle registers it, or without it as before.
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
     * The listing table, created afresh: only that one, since the server is shared with the tests of other packages.
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
