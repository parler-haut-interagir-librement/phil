<?php

declare(strict_types=1);

namespace Phil\GeoBundle\Infrastructure\Doctrine;

use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Mapping\Driver\AttributeDriver;
use Doctrine\ORM\Mapping\MappingException;
use Doctrine\Persistence\Mapping\ClassMetadata as PersistenceClassMetadata;
use Doctrine\Persistence\Mapping\Driver\MappingDriver;
use Doctrine\Persistence\Mapping\RuntimeReflectionService;
use Override;
use Phil\Geo\Coordinates;

/**
 * Maps `Phil\Geo\Coordinates` with the attributes of `CoordinatesEmbeddable`: the attribute driver reads the mirror,
 * and its field mappings are copied onto the metadata of `Coordinates`. The bundle adds it to the driver chain of the
 * default entity manager for that one class.
 */
final readonly class CoordinatesMappingDriver implements MappingDriver
{
    private AttributeDriver $attributes;

    public function __construct()
    {
        $this->attributes = new AttributeDriver([]);
    }

    #[Override]
    public function loadMetadataForClass(string $className, PersistenceClassMetadata $metadata): void
    {
        if (Coordinates::class !== $className || !$metadata instanceof ClassMetadata) {
            throw MappingException::classIsNotAValidEntityOrMappedSuperClass($className);
        }

        $mirror = new ClassMetadata(CoordinatesEmbeddable::class);
        $mirror->initializeReflection(new RuntimeReflectionService());

        $this->attributes->loadMetadataForClass(CoordinatesEmbeddable::class, $mirror);

        $metadata->isEmbeddedClass = $mirror->isEmbeddedClass;

        foreach ($mirror->fieldMappings as $field) {
            // A column of the mirror has a name and a type, and nothing else to carry over.
            $metadata->mapField(['fieldName' => $field->fieldName, 'type' => $field->type, 'columnName' => $field->columnName]);
        }
    }

    /**
     * @return list<class-string>
     */
    #[Override]
    public function getAllClassNames(): array
    {
        return [Coordinates::class];
    }

    #[Override]
    public function isTransient(string $className): bool
    {
        return Coordinates::class !== $className;
    }
}
