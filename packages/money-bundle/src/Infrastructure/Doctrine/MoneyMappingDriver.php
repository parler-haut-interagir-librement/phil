<?php

declare(strict_types=1);

namespace Phil\MoneyBundle\Infrastructure\Doctrine;

use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Mapping\Driver\AttributeDriver;
use Doctrine\ORM\Mapping\MappingException;
use Doctrine\Persistence\Mapping\ClassMetadata as PersistenceClassMetadata;
use Doctrine\Persistence\Mapping\Driver\MappingDriver;
use Doctrine\Persistence\Mapping\RuntimeReflectionService;
use Override;
use Phil\Money\Money;

/**
 * Maps `Phil\Money\Money` with the attributes of `MoneyEmbeddable`: the attribute driver reads the mirror,
 * and its field mappings are copied onto the metadata of `Money`. The bundle adds it to the driver chain of the
 * default entity manager for that one class.
 */
final readonly class MoneyMappingDriver implements MappingDriver
{
    private AttributeDriver $attributes;

    public function __construct()
    {
        $this->attributes = new AttributeDriver([]);
    }

    #[Override]
    public function loadMetadataForClass(string $className, PersistenceClassMetadata $metadata): void
    {
        if (Money::class !== $className || !$metadata instanceof ClassMetadata) {
            throw MappingException::classIsNotAValidEntityOrMappedSuperClass($className);
        }

        $mirror = new ClassMetadata(MoneyEmbeddable::class);
        $mirror->initializeReflection(new RuntimeReflectionService());

        $this->attributes->loadMetadataForClass(MoneyEmbeddable::class, $mirror);

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
        return [Money::class];
    }

    #[Override]
    public function isTransient(string $className): bool
    {
        return Money::class !== $className;
    }
}
