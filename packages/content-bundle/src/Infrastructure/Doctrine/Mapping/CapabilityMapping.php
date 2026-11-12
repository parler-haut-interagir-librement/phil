<?php

declare(strict_types=1);

namespace Phil\ContentBundle\Infrastructure\Doctrine\Mapping;

use BackedEnum;
use Doctrine\ORM\Mapping\ClassMetadata;
use Phil\ContentBundle\Infrastructure\Doctrine\InvalidContentMapping;
use ReflectionClass;

/**
 * The columns of one capability, added to every mapped class that implements it.
 *
 * A capability trait carries no mapping, by design: the mapping lives here, in the infrastructure, and
 * `DimensionContentMappingListener` applies it when Doctrine loads a class that implements the capability.
 * The application maps a capability of its own by extending this class; the bundle tags every subclass
 * `phil_content.capability_mapping` by autoconfiguration.
 *
 * A field the class already maps, by its own attributes or by inheritance, is left as it is: the application
 * can map one field of a capability its own way, and the others are still added. A field is otherwise mapped
 * on the first mapped class that holds the property: a mapped superclass that uses the trait maps it for all
 * its subclasses, which inherit it. A mapped superclass that implements the
 * interface without the property is skipped, and its subclass that uses the trait maps it; an entity that
 * implements the interface without the property is refused.
 */
abstract class CapabilityMapping
{
    /**
     * @return class-string
     */
    abstract public function capability(): string;

    /**
     * The fields of the capability, each named after the trait's property. A backed enum property names its
     * class under `enumType`.
     *
     * @return list<array{fieldName: string, type: string, columnName: string, nullable?: bool, length?: int, enumType?: class-string<BackedEnum>}>
     */
    abstract protected function fields(): array;

    /**
     * @param ClassMetadata<object> $metadata a class that implements `capability()`
     */
    final public function map(ClassMetadata $metadata): void
    {
        foreach ($this->fields() as $field) {
            if ($metadata->hasField($field['fieldName'])) {
                continue;
            }

            $declaring = $this->declaringClass($metadata->getName(), $field['fieldName']);

            if (null === $declaring) {
                if ($metadata->isMappedSuperclass) {
                    continue;
                }

                throw InvalidContentMapping::because(\sprintf('"%s" implements %s but has no property "%s"; use the trait of the capability.', $metadata->getName(), $this->capability(), $field['fieldName']));
            }

            if ($declaring !== $metadata->getName()) {
                // A private property of an unmapped parent: Doctrine reaches it through the parent.
                $field['declared'] = $declaring;
            }

            $metadata->mapField($field);
        }
    }

    /**
     * The class that declares `$property`, looking through the parents for a private one.
     *
     * @param class-string $class
     *
     * @return class-string|null
     */
    private function declaringClass(string $class, string $property): ?string
    {
        for ($reflection = new ReflectionClass($class); false !== $reflection; $reflection = $reflection->getParentClass()) {
            if ($reflection->hasProperty($property)) {
                return $reflection->getProperty($property)->getDeclaringClass()->getName();
            }
        }

        return null;
    }
}
