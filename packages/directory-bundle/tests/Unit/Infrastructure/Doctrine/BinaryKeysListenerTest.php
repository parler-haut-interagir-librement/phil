<?php

declare(strict_types=1);

namespace Phil\DirectoryBundle\Tests\Unit\Infrastructure\Doctrine;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Types\Type;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Events;
use Doctrine\ORM\ORMSetup;
use Doctrine\ORM\Tools\SchemaTool;
use Override;
use Phil\DirectoryBundle\Domain\ValueObject\ListingId;
use Phil\DirectoryBundle\Infrastructure\Doctrine\BinaryKeysListener;
use Phil\DirectoryBundle\Infrastructure\Doctrine\ListingIdType;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * The schema the ORM generates: the genre and the author in `utf8mb4_bin` on MySQL and MariaDB, the default collation
 * on SQLite, which compares bytes and keeps values that differ only by case or accent apart; PostgreSQL, whose schema
 * tool reads the server, is tested in the Integration suite.
 */
#[CoversClass(BinaryKeysListener::class)]
final class BinaryKeysListenerTest extends TestCase
{
    #[Override]
    protected function setUp(): void
    {
        if (!Type::hasType(ListingIdType::NAME)) {
            Type::addType(ListingIdType::NAME, ListingIdType::class);
        }
    }

    public function testTheGenreAndTheAuthorAreBinaryOnMySqlAndMariaDb(): void
    {
        foreach (['8.4.0', '11.4.0-MariaDB'] as $version) {
            self::assertSame(['phil_directory_listing' => ['author', 'genre']], $this->binaryColumns($this->schema(DriverManager::getConnection(['driver' => 'pdo_mysql', 'serverVersion' => $version]))), $version);
        }
    }

    public function testTheyKeepTheDefaultCollationOnSqliteWhichKeepsValuesApart(): void
    {
        $connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);

        foreach ($this->schema($connection)->getTables() as $table) {
            foreach ($table->getColumns() as $column) {
                self::assertNull($column->getCollation(), $column->getObjectName()->toString());
            }

            $connection->createSchemaManager()->createTable($table);
        }

        $values = ['ann', 'Ann', 'ANN', 'ánn'];

        foreach ($values as $value) {
            $connection->insert('phil_directory_listing', ['id' => ListingId::generate(), 'genre' => $value, 'author' => $value, 'editors' => '[]', 'indexing' => 'listed', 'site_listed' => true], ['id' => ListingIdType::NAME, 'site_listed' => 'boolean']);
        }

        foreach ($values as $value) {
            self::assertSame([$value], $connection->fetchFirstColumn('SELECT author FROM phil_directory_listing WHERE author = ?', [$value]), $value);
            self::assertSame([$value], $connection->fetchFirstColumn('SELECT genre FROM phil_directory_listing WHERE genre = ?', [$value]), $value);
        }
    }

    private function schema(Connection $connection): Schema
    {
        $config = ORMSetup::createAttributeMetadataConfig([\dirname(__DIR__, 4).'/src/Domain/Model'], true);
        $config->enableNativeLazyObjects(true);

        $entityManager = new EntityManager($connection, $config);
        $entityManager->getEventManager()->addEventListener(Events::loadClassMetadata, new BinaryKeysListener());

        return new SchemaTool($entityManager)->getSchemaFromMetadata(array_map($entityManager->getClassMetadata(...), array_keys(BinaryKeysListener::FIELDS)));
    }

    /**
     * @return array<string, list<string>>
     */
    private function binaryColumns(Schema $schema): array
    {
        $binary = [];

        foreach ($schema->getTables() as $table) {
            $columns = [];

            foreach ($table->getColumns() as $column) {
                if ('utf8mb4_bin' === $column->getCollation()) {
                    self::assertSame('utf8mb4', $column->getCharset());
                    $columns[] = $column->getObjectName()->toString();
                }
            }

            sort($columns);
            $binary[$table->getObjectName()->toString()] = $columns;
        }

        ksort($binary);

        return $binary;
    }
}
