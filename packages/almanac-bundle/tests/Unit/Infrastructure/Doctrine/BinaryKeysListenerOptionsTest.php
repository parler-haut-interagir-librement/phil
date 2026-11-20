<?php

declare(strict_types=1);

namespace Phil\AlmanacBundle\Tests\Unit\Infrastructure\Doctrine;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Event\LoadClassMetadataEventArgs;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\ORMSetup;
use Phil\AlmanacBundle\Infrastructure\Doctrine\BinaryKeysListener;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use stdClass;

/**
 * The options a field already carries stay, whatever the platform: the collation is added to them, never put in their
 * place; an entity the listener does not know is left as it is.
 */
#[CoversClass(BinaryKeysListener::class)]
final class BinaryKeysListenerOptionsTest extends TestCase
{
    public function testTheOptionsOfAFieldStayWithTheCollationAddedOnMySql(): void
    {
        foreach (BinaryKeysListener::FIELDS as $class => $fields) {
            $metadata = $this->metadata($class, $fields);
            $this->listen($metadata, DriverManager::getConnection(['driver' => 'pdo_mysql', 'serverVersion' => '8.4.0']));

            foreach ($fields as $field) {
                self::assertSame(['comment' => 'kept', 'charset' => 'utf8mb4', 'collation' => 'utf8mb4_bin'], $metadata->getFieldMapping($field)->options, $class.'::'.$field);
            }
        }
    }

    public function testTheOptionsStayAsTheyAreElsewhere(): void
    {
        foreach (BinaryKeysListener::FIELDS as $class => $fields) {
            $metadata = $this->metadata($class, $fields);
            $this->listen($metadata, DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]));

            foreach ($fields as $field) {
                self::assertSame(['comment' => 'kept'], $metadata->getFieldMapping($field)->options, $class.'::'.$field);
            }
        }
    }

    public function testAnotherEntityIsLeftAsItIs(): void
    {
        $metadata = $this->metadata(stdClass::class, ['key']);
        $this->listen($metadata, DriverManager::getConnection(['driver' => 'pdo_mysql', 'serverVersion' => '8.4.0']));

        self::assertSame(['comment' => 'kept'], $metadata->getFieldMapping('key')->options);
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

        foreach ($fields as $field) {
            $metadata->mapField(['fieldName' => $field, 'type' => 'string', 'options' => ['comment' => 'kept']]);
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
