<?php

declare(strict_types=1);

namespace Phil\AlmanacBundle\Tests\Unit\Infrastructure\Doctrine;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Types\Type;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Events;
use Doctrine\ORM\ORMSetup;
use Doctrine\ORM\Tools\SchemaTool;
use Override;
use Phil\AlmanacBundle\Infrastructure\Doctrine\BinaryKeysListener;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Bridge\Doctrine\Types\UuidType;

/**
 * The schema the ORM generates: the key in `utf8mb4_bin` on MySQL and MariaDB, the default collation on SQLite, which
 * compares bytes and keeps keys that differ only by case or accent apart; PostgreSQL, whose schema tool reads the
 * server, is tested in the Integration suite.
 */
#[CoversClass(BinaryKeysListener::class)]
final class BinaryKeysListenerTest extends TestCase
{
    #[Override]
    protected function setUp(): void
    {
        // The type DoctrineBundle registers for the identifiers of symfony/uid.
        if (!Type::hasType(UuidType::NAME)) {
            Type::addType(UuidType::NAME, UuidType::class);
        }
    }

    public function testTheKeyIsBinaryOnMySqlAndMariaDb(): void
    {
        foreach (['8.4.0', '11.4.0-MariaDB'] as $version) {
            self::assertSame(['phil_almanac_date' => ['date_key']], $this->binaryColumns($this->schema(DriverManager::getConnection(['driver' => 'pdo_mysql', 'serverVersion' => $version]))), $version);
        }
    }

    public function testTheKeyKeepsTheDefaultCollationOnSqliteWhichKeepsKeysApart(): void
    {
        $connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
        $schema = $this->schema($connection);

        foreach ($schema->getTables() as $table) {
            foreach ($table->getColumns() as $column) {
                self::assertNull($column->getCollation(), $column->getObjectName()->toString());
            }

            $connection->createSchemaManager()->createTable($table);
        }

        $keys = ['harvest', 'Harvest', 'HARVEST', 'harvést'];

        foreach ($keys as $i => $key) {
            $connection->insert('phil_almanac_date', ['id' => \sprintf('%016d', $i), 'date_key' => $key, 'title' => 'Harvest', 'rule' => 'march 1', 'days' => 1, 'all_day' => true, 'tags' => '[]'], ['all_day' => 'boolean']);
        }

        foreach ($keys as $key) {
            self::assertSame([$key], $connection->fetchFirstColumn('SELECT date_key FROM phil_almanac_date WHERE date_key = ?', [$key]), $key);
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
