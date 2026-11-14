<?php

declare(strict_types=1);

namespace Phil\PrivacyBundle\Domain\Declaration;

use ReflectionClass;
use ReflectionProperty;

/**
 * The properties of a class and of its parents, each read on the class that declares it.
 *
 * **Parents are walked explicitly.** Reflection does not return a parent's private properties from the child,
 * and a field declared privately on a base model is still a field of every subclass. The order is by class,
 * parents first, then by declaration, so what is built from it is stable from one run to the next.
 */
final class Lineage
{
    /**
     * @param class-string $class
     *
     * @return list<ReflectionProperty>
     */
    public static function properties(string $class): array
    {
        $lineage = [];

        for ($reflection = new ReflectionClass($class); false !== $reflection; $reflection = $reflection->getParentClass()) {
            array_unshift($lineage, $reflection);
        }

        $properties = [];

        foreach ($lineage as $reflection) {
            foreach ($reflection->getProperties() as $property) {
                if ($property->getDeclaringClass()->getName() === $reflection->getName()) {
                    $properties[] = $property;
                }
            }
        }

        return $properties;
    }
}
