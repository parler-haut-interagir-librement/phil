<?php

declare(strict_types=1);

namespace Phil\TrashBundle\Tests\Unit\Domain;

use DateTimeImmutable;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Mapping\Driver\AttributeDriver;
use Doctrine\Persistence\Mapping\RuntimeReflectionService;
use Phil\Resource\ResourceReference;
use Phil\TrashBundle\Domain\Model\TrashItem;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * The columns an application's migrations create for the trash.
 */
#[CoversClass(TrashItem::class)]
final class MappingTest extends TestCase
{
    public function testTheTable(): void
    {
        $metadata = new ClassMetadata(TrashItem::class);
        $metadata->initializeReflection(new RuntimeReflectionService());
        new AttributeDriver([\dirname(__DIR__, 3).'/src/Domain/Model'])->loadMetadataForClass(TrashItem::class, $metadata);
        $columns = [];

        foreach ($metadata->fieldMappings as $field => $mapping) {
            $columns[$field] = [$mapping->columnName, $mapping->type, $mapping->length, $mapping->nullable, $mapping->options['fixed'] ?? null];
        }

        self::assertSame(['phil_trash_item', ['id'], [
            'id' => ['id', 'string', 36, false, true],
            'resourceKey' => ['resource_key', 'string', 64, false, null],
            'resourceId' => ['resource_id', 'string', 255, false, null],
            'title' => ['title', 'string', 255, false, null],
            'payload' => ['payload', 'json', null, false, null],
            'parent' => ['parent', 'string', 320, true, null],
            'trashedAt' => ['trashed_at', 'datetime_immutable', null, false, null],
            'trashedBy' => ['trashed_by', 'string', 255, true, null],
            'purgeAfter' => ['purge_after', 'datetime_immutable', null, false, null],
        ]], [$metadata->getTableName(), $metadata->getIdentifierFieldNames(), $columns]);
        self::assertSame([['columns' => ['resource_key', 'resource_id']], ['columns' => ['purge_after']]], array_values($metadata->table['indexes'] ?? []));

        $at = new DateTimeImmutable();
        self::assertSame(36, \strlen(TrashItem::of(ResourceReference::of('page', '1'), 'x', [], null, $at, null, $at)->id));
    }
}
