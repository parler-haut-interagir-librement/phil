<?php

declare(strict_types=1);

namespace Phil\PrivacyBundle\Domain\Declaration;

use Phil\PrivacyBundle\Domain\Attribute\Anonymize;

/**
 * Reads the `#[Anonymize]` declarations of a class, its parents included, in the order of {@see Lineage}.
 */
final class DeclaredAnonymizations
{
    /**
     * @param class-string $class
     *
     * @return array<string, AnonymizedField> by property name
     */
    public static function of(string $class): array
    {
        $fields = [];

        foreach (Lineage::properties($class) as $property) {
            foreach ($property->getAttributes(Anonymize::class) as $attribute) {
                $fields[$property->getName()] = new AnonymizedField($property->getDeclaringClass()->getName(), $property->getName(), $attribute->newInstance());
            }
        }

        return $fields;
    }
}
