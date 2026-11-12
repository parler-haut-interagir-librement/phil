<?php

declare(strict_types=1);

namespace Phil\MediaBundle\Tests\Unit\Domain;

use DateTimeImmutable;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Mapping\Driver\AttributeDriver;
use Doctrine\ORM\Mapping\ManyToOneAssociationMapping;
use Doctrine\Persistence\Mapping\RuntimeReflectionService;
use Phil\MediaBundle\Domain\Model\Media;
use Phil\MediaBundle\Domain\Model\MediaCollection;
use Phil\MediaBundle\Domain\Model\MediaVersion;
use Phil\MediaBundle\Domain\ValueObject\FileInfo;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * The columns an application's migrations create for the media library.
 */
#[CoversClass(Media::class)]
#[CoversClass(MediaCollection::class)]
#[CoversClass(MediaVersion::class)]
final class MappingTest extends TestCase
{
    public function testTheMediaTable(): void
    {
        $metadata = $this->metadata(Media::class);

        self::assertSame(['phil_media', [
            'id' => ['id', 'string', 36, false, true],
            'version' => ['version', 'integer', null, false, null],
            'filename' => ['filename', 'string', 255, false, null],
            'mimeType' => ['mime_type', 'string', 100, false, null],
            'size' => ['size', 'integer', null, false, null],
            'width' => ['width', 'integer', null, true, null],
            'height' => ['height', 'integer', null, true, null],
            'storageKey' => ['storage_key', 'string', 400, false, null],
            'title' => ['title', 'string', 255, true, null],
            'alt' => ['alt', 'string', 500, true, null],
            'credit' => ['credit', 'string', 255, true, null],
            'license' => ['license', 'string', 64, false, null],
            'aiDisclosure' => ['ai_disclosure', 'string', 16, false, null],
            'digitalSourceType' => ['digital_source_type', 'string', 64, true, null],
            'focalX' => ['focal_x', 'float', null, false, null],
            'focalY' => ['focal_y', 'float', null, false, null],
            'updatedAt' => ['updated_at', 'datetime_immutable', null, false, null],
            'placeholder' => ['placeholder', 'text', null, true, null],
        ]], [$metadata->getTableName(), $this->columns($metadata)]);
        self::assertSame([['name' => 'collection', 'referencedColumnName' => 'id', 'nullable' => true, 'onDelete' => 'SET NULL']], $this->joinColumns($metadata, 'collection'));
    }

    public function testTheVersionAndCollectionTables(): void
    {
        $version = $this->metadata(MediaVersion::class);

        self::assertSame(['phil_media_version', [
            'id' => ['id', 'string', 36, false, true],
            'number' => ['number', 'integer', null, false, null],
            'filename' => ['filename', 'string', 255, false, null],
            'mimeType' => ['mime_type', 'string', 100, false, null],
            'size' => ['size', 'integer', null, false, null],
            'storageKey' => ['storage_key', 'string', 400, false, null],
            'replacedAt' => ['replaced_at', 'datetime_immutable', null, false, null],
        ]], [$version->getTableName(), $this->columns($version)]);
        self::assertSame([['name' => 'media', 'referencedColumnName' => 'id', 'nullable' => false, 'onDelete' => 'CASCADE']], $this->joinColumns($version, 'media'));
        self::assertSame([['columns' => ['media', 'number']]], array_values($version->table['uniqueConstraints'] ?? []));

        $collection = $this->metadata(MediaCollection::class);
        self::assertSame(['phil_media_collection', [
            'id' => ['id', 'string', 36, false, true],
            'key' => ['collection_key', 'string', 64, false, null],
            'name' => ['name', 'string', 150, false, null],
        ]], [$collection->getTableName(), $this->columns($collection)]);
        self::assertSame([['columns' => ['collection_key']]], array_values($collection->table['uniqueConstraints'] ?? []));
    }

    public function testTheIdentifiersFitTheirColumns(): void
    {
        $media = Media::upload(FileInfo::of('a.png', 'image/png', 1), MediaCollection::create('a', 'A'), new DateTimeImmutable());

        self::assertSame([36, 36, 36], [\strlen($media->id), \strlen((string) $media->collection()?->id), \strlen($media->replace(FileInfo::of('b.png', 'image/png', 1), new DateTimeImmutable())->id)]);
    }

    /**
     * @param class-string $class
     *
     * @return ClassMetadata<object>
     */
    private function metadata(string $class): ClassMetadata
    {
        $metadata = new ClassMetadata($class);
        $metadata->initializeReflection(new RuntimeReflectionService());
        new AttributeDriver([\dirname(__DIR__, 3).'/src/Domain/Model'])->loadMetadataForClass($class, $metadata);

        return $metadata;
    }

    /**
     * @param ClassMetadata<object> $metadata
     *
     * @return array<string, list<mixed>>
     */
    private function columns(ClassMetadata $metadata): array
    {
        $columns = [];

        foreach ($metadata->fieldMappings as $field => $mapping) {
            $columns[$field] = [$mapping->columnName, $mapping->type, $mapping->length, $mapping->nullable, $mapping->options['fixed'] ?? null];
        }

        return $columns;
    }

    /**
     * @param ClassMetadata<object> $metadata
     *
     * @return list<array<string, mixed>>
     */
    private function joinColumns(ClassMetadata $metadata, string $field): array
    {
        $columns = [];

        $mapping = $metadata->getAssociationMapping($field);
        self::assertInstanceOf(ManyToOneAssociationMapping::class, $mapping);

        foreach ($mapping->joinColumns as $column) {
            $columns[] = ['name' => $column->name, 'referencedColumnName' => $column->referencedColumnName, 'nullable' => $column->nullable, 'onDelete' => $column->onDelete];
        }

        return $columns;
    }
}
