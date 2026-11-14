<?php

declare(strict_types=1);

namespace Phil\MentionsBundle\Tests\Unit\Infrastructure;

use DateTimeImmutable;
use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\ORMSetup;
use Phil\MentionsBundle\Domain\Model\RecordedMention;
use Phil\Resource\ResourceReference;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * The columns the application's migrations create, as the mapping declares them on the entity.
 */
#[CoversClass(RecordedMention::class)]
final class MappingTest extends TestCase
{
    public function testTheTable(): void
    {
        $config = ORMSetup::createAttributeMetadataConfig([\dirname(__DIR__, 3).'/src/Domain/Model'], true);
        $config->enableNativeLazyObjects(true);

        $entityManager = new EntityManager(DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]), $config);
        self::assertSame('m-1', RecordedMention::record('m-1', ResourceReference::of('c', '1'), ResourceReference::of('u', '1'), ResourceReference::of('u', '2'), new DateTimeImmutable())->id);

        $metadata = $entityManager->getClassMetadata(RecordedMention::class);
        $columns = [];

        foreach ($metadata->getFieldNames() as $field) {
            $mapping = $metadata->getFieldMapping($field);
            $columns[$field] = [$mapping->columnName, $mapping->type, $mapping->length, (bool) $mapping->nullable];
        }

        self::assertSame('phil_mention', $metadata->getTableName());
        self::assertSame(['id'], $metadata->getIdentifierFieldNames());
        self::assertSame([
            'id' => ['id', 'string', 36, false],
            'subject' => ['subject', 'string', 320, false],
            'member' => ['member', 'string', 320, false],
            'author' => ['author', 'string', 320, false],
            'createdAt' => ['created_at', 'datetime_immutable', null, false],
        ], $columns);
        self::assertSame(['phil_mention_subject_member' => ['columns' => ['subject', 'member']]], $metadata->table['uniqueConstraints'] ?? null);
        self::assertSame(['phil_mention_member' => ['columns' => ['member']]], $metadata->table['indexes'] ?? null);
    }
}
