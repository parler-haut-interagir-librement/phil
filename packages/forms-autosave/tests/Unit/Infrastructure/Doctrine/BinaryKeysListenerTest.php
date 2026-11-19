<?php

declare(strict_types=1);

namespace Phil\FormsAutosave\Tests\Unit\Infrastructure\Doctrine;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Events;
use Doctrine\ORM\ORMSetup;
use Doctrine\ORM\Tools\SchemaTool;
use Phil\FormsAutosave\Domain\Model\Draft;
use Phil\FormsAutosave\Infrastructure\Doctrine\BinaryKeysListener;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * The schema the ORM generates: the primary key in `utf8mb4_bin` on MySQL and MariaDB, the default collation on
 * SQLite, which compares bytes and keeps keys that differ only by case or accent apart; PostgreSQL, whose schema tool
 * reads the server, is tested in the Integration suite.
 */
#[CoversClass(BinaryKeysListener::class)]
final class BinaryKeysListenerTest extends TestCase
{
    public function testTheKeysAreBinaryOnMySqlAndMariaDb(): void
    {
        foreach (['8.4.0', '11.4.0-MariaDB'] as $version) {
            self::assertSame([Draft::TABLE => ['draft_key', 'owner']], $this->binaryColumns($this->schema(DriverManager::getConnection(['driver' => 'pdo_mysql', 'serverVersion' => $version]))), $version);
        }
    }

    public function testTheKeysKeepTheDefaultCollationOnSqliteWhichKeepsKeysApart(): void
    {
        $connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);

        foreach ($this->schema($connection)->getTables() as $table) {
            foreach ($table->getColumns() as $column) {
                self::assertNull($column->getCollation(), $column->getObjectName()->toString());
            }

            $connection->createSchemaManager()->createTable($table);
        }

        $keys = ['abcdefghijklmnop', 'Abcdefghijklmnop', 'ABCDEFGHIJKLMNOP', 'ábcdefghijklmnop'];

        foreach ($keys as $key) {
            $connection->insert(Draft::TABLE, ['owner' => str_repeat('a', 64), 'draft_key' => $key, 'revision' => 1, 'payload' => $key, 'saved_at' => '2026-11-19 10:00:00', 'expires_at' => '2026-11-20 10:00:00']);
        }

        foreach ($keys as $key) {
            self::assertSame([$key], $connection->fetchFirstColumn('SELECT payload FROM '.Draft::TABLE.' WHERE draft_key = ?', [$key]), $key);
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
