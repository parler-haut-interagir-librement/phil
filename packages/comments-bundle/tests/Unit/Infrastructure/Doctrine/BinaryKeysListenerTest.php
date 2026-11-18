<?php

declare(strict_types=1);

namespace Phil\CommentsBundle\Tests\Unit\Infrastructure\Doctrine;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Events;
use Doctrine\ORM\ORMSetup;
use Doctrine\ORM\Tools\SchemaTool;
use Phil\CommentsBundle\Infrastructure\Doctrine\BinaryKeysListener;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * The schema the ORM generates: the identifiers in `utf8mb4_bin` on MySQL and MariaDB, the default collation on SQLite;
 * PostgreSQL, whose schema tool reads the server, is tested in the Integration suite.
 */
#[CoversClass(BinaryKeysListener::class)]
final class BinaryKeysListenerTest extends TestCase
{
    public function testTheIdentifiersAreBinaryOnMySqlAndMariaDb(): void
    {
        foreach (['8.4.0', '11.4.0-MariaDB'] as $version) {
            self::assertSame([
                'phil_comment' => ['author', 'id', 'thread'],
                'phil_comment_thread' => ['author', 'id', 'resolved_by', 'subject'],
            ], $this->binaryColumns($this->schema(DriverManager::getConnection(['driver' => 'pdo_mysql', 'serverVersion' => $version]))), $version);
        }
    }

    public function testTheIdentifiersKeepTheDefaultCollationElsewhere(): void
    {
        foreach ([DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true])] as $connection) {
            foreach ($this->schema($connection)->getTables() as $table) {
                foreach ($table->getColumns() as $column) {
                    self::assertNull($column->getCollation(), $column->getObjectName()->toString());
                }
            }
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
