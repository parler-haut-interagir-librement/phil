<?php

declare(strict_types=1);

namespace Phil\DoctrineBehaviours\Tests\Unit;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Configuration;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Event\LoadClassMetadataEventArgs;
use Doctrine\ORM\Events;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Mapping\Driver\AttributeDriver;
use Doctrine\ORM\Tools\SchemaTool;
use Phil\DoctrineBehaviours\BinaryKeysListener;
use Phil\DoctrineBehaviours\Tests\Unit\Fixtures\Entity\Article;
use Phil\DoctrineBehaviours\Tests\Unit\Fixtures\Entity\Bike;
use Phil\DoctrineBehaviours\Tests\Unit\Fixtures\Entity\Folder;
use Phil\DoctrineBehaviours\Tests\Unit\Fixtures\Entity\GadgetTranslation;
use Phil\DoctrineBehaviours\Tests\Unit\Fixtures\Entity\Kite;
use Phil\DoctrineBehaviours\Tests\Unit\Fixtures\Entity\Tag;
use Phil\DoctrineBehaviours\Tests\Unit\Fixtures\Entity\Ticket;
use Phil\DoctrineBehaviours\Version\RecordVersion;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * The library used without its bundle: the schema the ORM generates for entities that take the behaviours, with the
 * listener registered, has their keys in `utf8mb4_bin` on MySQL and MariaDB and the default collation on SQLite; the
 * options a field already carries stay, the collation added to them.
 */
#[CoversClass(BinaryKeysListener::class)]
final class BinaryKeysListenerTest extends TestCase
{
    /**
     * A class of each behaviour that writes a key, one that takes none, and the versions.
     */
    private const array CLASSES = [Article::class, Bike::class, Folder::class, GadgetTranslation::class, Kite::class, Tag::class, Ticket::class, RecordVersion::class];

    /**
     * The fields of each class, as the interfaces it implements give them.
     */
    private const array FIELDS = [
        Tag::class => ['slug'],
        GadgetTranslation::class => ['locale'],
        Bike::class => ['ownerId'],
        Article::class => ['createdBy', 'updatedBy'],
        Folder::class => ['treePath'],
        Ticket::class => ['id'],
        RecordVersion::class => ['createdBy', 'updatedBy', 'subjectTable', 'subjectId'],
    ];

    public function testTheKeysAreBinaryOnMySqlAndMariaDb(): void
    {
        foreach (['8.4.0', '11.4.0-MariaDB'] as $version) {
            self::assertSame([
                'article' => ['created_by', 'updated_by'],
                'bike' => ['owner_id'],
                'folder' => ['tree_path'],
                'gadget_translation' => ['locale'],
                'kite' => [],
                'phil_record_version' => ['created_by', 'subject_id', 'subject_table', 'updated_by'],
                'tag' => ['slug'],
                'ticket' => ['id'],
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
        $metadata = new ClassMetadata(Bike::class);
        $metadata->mapField(['fieldName' => 'id', 'type' => Types::INTEGER]);
        $metadata->mapField(['fieldName' => 'slug', 'type' => Types::TEXT]);
        $metadata->mapField(['fieldName' => 'ownerId', 'type' => Types::GUID]);

        $this->listen($metadata, DriverManager::getConnection(['driver' => 'pdo_mysql', 'serverVersion' => '8.4.0']));

        self::assertNull($metadata->getFieldMapping('id')->options);
        self::assertNull($metadata->getFieldMapping('slug')->options);
        self::assertSame(['charset' => 'utf8mb4', 'collation' => 'utf8mb4_bin'], $metadata->getFieldMapping('ownerId')->options);
    }

    public function testTheOptionsOfAFieldStayWithTheCollationAddedOnMySql(): void
    {
        foreach (self::FIELDS as $class => $fields) {
            $metadata = $this->metadata($class, $fields);
            $this->listen($metadata, DriverManager::getConnection(['driver' => 'pdo_mysql', 'serverVersion' => '8.4.0']));

            foreach ($fields as $i => $field) {
                $expected = ['charset' => 'utf8mb4', 'collation' => 'utf8mb4_bin'];

                self::assertSame(0 === $i ? ['comment' => 'kept', ...$expected] : $expected, $metadata->getFieldMapping($field)->options, $class.'::'.$field);
            }
        }
    }

    public function testTheOptionsStayAsTheyAreElsewhere(): void
    {
        foreach (self::FIELDS as $class => $fields) {
            $metadata = $this->metadata($class, $fields);
            $this->listen($metadata, DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]));

            foreach ($fields as $i => $field) {
                self::assertEquals(0 === $i ? ['comment' => 'kept'] : null, $metadata->getFieldMapping($field)->options, $class.'::'.$field);
            }
        }
    }

    public function testAClassThatTakesNoBehaviourIsLeftAsItIs(): void
    {
        $metadata = $this->metadata(self::class, ['slug']);
        $this->listen($metadata, DriverManager::getConnection(['driver' => 'pdo_mysql', 'serverVersion' => '8.4.0']));

        self::assertSame(['comment' => 'kept'], $metadata->getFieldMapping('slug')->options);
    }

    private function schema(Connection $connection): Schema
    {
        $config = $this->configuration([__DIR__.'/Fixtures/Entity', \dirname(__DIR__, 2).'/src/Version']);

        $entityManager = new EntityManager($connection, $config);
        $entityManager->getEventManager()->addEventListener(Events::loadClassMetadata, new BinaryKeysListener());

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

    /**
     * @param class-string $class
     * @param list<string> $fields
     *
     * @return ClassMetadata<object>
     */
    private function metadata(string $class, array $fields): ClassMetadata
    {
        $metadata = new ClassMetadata($class);

        foreach ($fields as $i => $field) {
            $metadata->mapField(['fieldName' => $field, 'type' => 'string', ...(0 === $i ? ['options' => ['comment' => 'kept']] : [])]);
        }

        return $metadata;
    }

    /**
     * @param ClassMetadata<object> $metadata
     */
    private function listen(ClassMetadata $metadata, Connection $connection): void
    {
        $config = $this->configuration([]);

        new BinaryKeysListener()->loadClassMetadata(new LoadClassMetadataEventArgs($metadata, new EntityManager($connection, $config)));
    }

    /**
     * @param list<string> $paths
     */
    private function configuration(array $paths): Configuration
    {
        $config = new Configuration();
        $config->setMetadataDriverImpl(new AttributeDriver($paths));
        $config->enableNativeLazyObjects(true);

        return $config;
    }
}
