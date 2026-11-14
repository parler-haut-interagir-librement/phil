<?php

declare(strict_types=1);

namespace Phil\PrivacyBundle\Domain\Declaration;

use Phil\PrivacyBundle\Domain\Attribute\PersonalData;
use Phil\PrivacyBundle\Domain\Retention\RetentionSchedule;
use ReflectionProperty;

/**
 * Reads the `#[PersonalData]` declarations of a class, its parents included, in the order of {@see Lineage}:
 * a personal field declared privately on a base model is still a personal field of every subclass, and an
 * inventory built from the result is stable from one run to the next.
 */
final class DeclaredPersonalFields
{
    /**
     * @param class-string $class
     *
     * @return list<PersonalField>
     */
    public static function of(string $class): array
    {
        $fields = [];

        foreach (Lineage::properties($class) as $property) {
            $declaration = self::declarationOf($property);

            if ($declaration instanceof PersonalData) {
                $fields[] = new PersonalField($property->getDeclaringClass()->getName(), $property->getName(), $declaration->category, $declaration->retention);
            }
        }

        return $fields;
    }

    /**
     * The fields no retention rule covers yet: what a test of the inventory fails on.
     *
     * Without a schedule, a field is uncovered when it cites no rule. With one, it is also uncovered when the rule
     * it cites is not in the schedule: a misspelt or removed identifier covers nothing.
     *
     * @param class-string $class
     *
     * @return list<PersonalField>
     */
    public static function uncoveredIn(string $class, ?RetentionSchedule $schedule = null): array
    {
        return array_values(array_filter(
            self::of($class),
            static fn (PersonalField $field): bool => $schedule instanceof RetentionSchedule ? !$field->isCoveredBy($schedule) : !$field->isCoveredByRetention(),
        ));
    }

    private static function declarationOf(ReflectionProperty $property): ?PersonalData
    {
        $attributes = $property->getAttributes(PersonalData::class);

        return [] === $attributes ? null : $attributes[0]->newInstance();
    }
}
