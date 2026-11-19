<?php

declare(strict_types=1);

namespace Phil\DirectoryBundle\Tests\Unit\Infrastructure\Doctrine;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Event\LoadClassMetadataEventArgs;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\ORMSetup;
use Phil\DirectoryBundle\Domain\Model\ListingDimension;
use Phil\DirectoryBundle\Infrastructure\Doctrine\BinaryKeysListener;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use stdClass;

/**
 * The options a field already carries stay, whatever the platform: the collation is added to them, never put in their
 * place; `editedBy` takes it on a class that implements `EditedByCapable` once mapped, and only then; an entity the
 * listener does not know is left as it is.
 */
#[CoversClass(BinaryKeysListener::class)]
final class BinaryKeysListenerOptionsTest extends TestCase
{
    public function testTheOptionsOfAFieldStayWithTheCollationAddedOnMySql(): void
    {
        foreach (BinaryKeysListener::FIELDS as $class => $fields) {
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
        foreach (BinaryKeysListener::FIELDS as $class => $fields) {
            $metadata = $this->metadata($class, $fields);
            $this->listen($metadata, DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]));

            foreach ($fields as $i => $field) {
                self::assertEquals(0 === $i ? ['comment' => 'kept'] : null, $metadata->getFieldMapping($field)->options, $class.'::'.$field);
            }
        }
    }

    public function testWhoWroteAVersionTakesTheCollationOnMySqlOnly(): void
    {
        $metadata = $this->metadata(ListingDimension::class, BinaryKeysListener::EDITED_BY_FIELDS);
        $metadata->mapField(['fieldName' => 'localeKey', 'type' => 'string']);
        $this->listen($metadata, DriverManager::getConnection(['driver' => 'pdo_mysql', 'serverVersion' => '8.4.0']));

        self::assertSame(['comment' => 'kept', 'charset' => 'utf8mb4', 'collation' => 'utf8mb4_bin'], $metadata->getFieldMapping('editedBy')->options);
        self::assertNull($metadata->getFieldMapping('localeKey')->options, 'A key of content-bundle, which collates it.');

        $metadata = $this->metadata(ListingDimension::class, BinaryKeysListener::EDITED_BY_FIELDS);
        $this->listen($metadata, DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]));

        self::assertSame(['comment' => 'kept'], $metadata->getFieldMapping('editedBy')->options);
    }

    public function testAClassWhoseWriterIsNotMappedIsLeftAsItIs(): void
    {
        $metadata = $this->metadata(ListingDimension::class, ['localeKey']);
        $this->listen($metadata, DriverManager::getConnection(['driver' => 'pdo_mysql', 'serverVersion' => '8.4.0']));

        self::assertFalse($metadata->hasField('editedBy'));
        self::assertSame(['comment' => 'kept'], $metadata->getFieldMapping('localeKey')->options);
    }

    public function testAnotherEntityIsLeftAsItIs(): void
    {
        $metadata = $this->metadata(stdClass::class, ['key', 'editedBy']);
        $this->listen($metadata, DriverManager::getConnection(['driver' => 'pdo_mysql', 'serverVersion' => '8.4.0']));

        self::assertSame(['comment' => 'kept'], $metadata->getFieldMapping('key')->options);
        self::assertNull($metadata->getFieldMapping('editedBy')->options, 'Not a class that implements EditedByCapable.');
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
        $config = ORMSetup::createAttributeMetadataConfig([], true);
        $config->enableNativeLazyObjects(true);

        new BinaryKeysListener()->loadClassMetadata(new LoadClassMetadataEventArgs($metadata, new EntityManager($connection, $config)));
    }
}
