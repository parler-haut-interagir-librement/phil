<?php

declare(strict_types=1);

namespace Phil\DoctrineBehavioursBundle\Tests\Unit\Infrastructure\Doctrine;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Event\LoadClassMetadataEventArgs;
use Doctrine\ORM\Events;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\ORMSetup;
use Doctrine\ORM\Tools\SchemaTool;
use Phil\DoctrineBehaviours\Version\RecordVersion;
use Phil\DoctrineBehavioursBundle\Infrastructure\Doctrine\BinaryKeysListener;
use Phil\DoctrineBehavioursBundle\Tests\Functional\App\Entity\Article;
use Phil\DoctrineBehavioursBundle\Tests\Functional\App\Entity\Faq;
use Phil\DoctrineBehavioursBundle\Tests\Functional\App\Entity\FaqTranslation;
use Phil\DoctrineBehavioursBundle\Tests\Functional\App\Entity\Notice;
use Phil\DoctrineBehavioursBundle\Tests\Functional\App\Entity\Page;
use Phil\DoctrineBehavioursBundle\Tests\Functional\App\Entity\Step;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * The schema the ORM generates for entities that take the behaviours: their keys in `utf8mb4_bin` on MySQL and MariaDB,
 * the default collation on SQLite; PostgreSQL, whose schema tool reads the server, is tested in the Integration suite.
 */
#[CoversClass(BinaryKeysListener::class)]
final class BinaryKeysListenerTest extends TestCase
{
    /**
     * The entities of the test application, which take every behaviour, and the versions of the library.
     */
    public const array CLASSES = [Article::class, Faq::class, FaqTranslation::class, Notice::class, Page::class, Step::class, RecordVersion::class];

    public function testTheKeysAreBinaryOnMySqlAndMariaDb(): void
    {
        foreach (['8.4.0', '11.4.0-MariaDB'] as $version) {
            self::assertSame([
                'article' => ['slug'],
                'faq' => [],
                'faq_translation' => ['locale'],
                'notice' => ['id', 'owner_id', 'slug'],
                'page' => ['created_by', 'tree_path', 'updated_by'],
                'phil_record_version' => ['created_by', 'subject_id', 'subject_table', 'updated_by'],
                'step' => [],
            ], $this->binaryColumns($this->schema(DriverManager::getConnection(['driver' => 'pdo_mysql', 'serverVersion' => $version]))), $version);
        }
    }

    public function testTheKeysKeepTheDefaultCollationElsewhere(): void
    {
        foreach ($this->schema(DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]))->getTables() as $table) {
            foreach ($table->getColumns() as $column) {
                self::assertNull($column->getCollation(), $column->getObjectName()->toString());
            }
        }
    }

    public function testAFieldMappedUnderAnotherTypeOrNotMappedIsLeftAsItIs(): void
    {
        $metadata = new ClassMetadata(Notice::class);
        $metadata->mapField(['fieldName' => 'id', 'type' => Types::INTEGER]);
        $metadata->mapField(['fieldName' => 'slug', 'type' => Types::TEXT]);
        $metadata->mapField(['fieldName' => 'ownerId', 'type' => Types::GUID]);

        new BinaryKeysListener()->loadClassMetadata(new LoadClassMetadataEventArgs($metadata, $this->entityManager(DriverManager::getConnection(['driver' => 'pdo_mysql', 'serverVersion' => '8.4.0']))));

        self::assertNull($metadata->getFieldMapping('id')->options);
        self::assertNull($metadata->getFieldMapping('slug')->options);
        self::assertSame(['charset' => 'utf8mb4', 'collation' => 'utf8mb4_bin'], $metadata->getFieldMapping('ownerId')->options);
    }

    private function entityManager(Connection $connection): EntityManager
    {
        $config = ORMSetup::createAttributeMetadataConfig([\dirname(__DIR__, 3).'/Functional/App/Entity'], true);
        $config->enableNativeLazyObjects(true);

        $entityManager = new EntityManager($connection, $config);
        $entityManager->getEventManager()->addEventListener(Events::loadClassMetadata, new BinaryKeysListener());

        return $entityManager;
    }

    private function schema(Connection $connection): Schema
    {
        $entityManager = $this->entityManager($connection);

        return new SchemaTool($entityManager)->getSchemaFromMetadata(array_map($entityManager->getClassMetadata(...), self::CLASSES));
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
