<?php

declare(strict_types=1);

namespace Phil\ContentBundle\Tests\Unit\Infrastructure\Doctrine;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Event\LoadClassMetadataEventArgs;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\ORMSetup;
use Phil\ContentBundle\Infrastructure\Doctrine\BinaryKeysListener;
use Phil\ContentBundle\Tests\Unit\Infrastructure\Doctrine\Fixture\ArticleDimension;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * The options a field already carries stay, whatever the platform: the collation is added to them, never put in their place,
 * on the bundle's entities and on the application's rows.
 */
#[CoversClass(BinaryKeysListener::class)]
final class BinaryKeysListenerOptionsTest extends TestCase
{
    public function testTheOptionsOfAFieldStayWithTheCollationAddedOnMySql(): void
    {
        foreach ([...BinaryKeysListener::FIELDS, ArticleDimension::class => BinaryKeysListener::DIMENSION_FIELDS] as $class => $fields) {
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
        foreach ([...BinaryKeysListener::FIELDS, ArticleDimension::class => BinaryKeysListener::DIMENSION_FIELDS] as $class => $fields) {
            $metadata = $this->metadata($class, $fields);
            $this->listen($metadata, DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]));

            foreach ($fields as $i => $field) {
                self::assertEquals(0 === $i ? ['comment' => 'kept'] : null, $metadata->getFieldMapping($field)->options, $class.'::'.$field);
            }
        }
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
