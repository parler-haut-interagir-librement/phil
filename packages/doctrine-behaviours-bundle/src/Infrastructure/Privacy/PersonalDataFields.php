<?php

declare(strict_types=1);

namespace Phil\DoctrineBehavioursBundle\Infrastructure\Privacy;

use Override;
use Phil\DoctrineBehaviours\Contract\PersonalFields;
use Phil\PrivacyBundle\Domain\Declaration\DeclaredPersonalFields;
use Phil\PrivacyBundle\Domain\Declaration\PersonalField;

/**
 * The personal fields of a class are its properties declared `#[PersonalData]`, its parents' included, as
 * phil/privacy-bundle reads them: what the versions leave out, seal or redact.
 */
final readonly class PersonalDataFields implements PersonalFields
{
    #[Override]
    public function of(string $class): array
    {
        return array_values(array_unique(array_map(static fn (PersonalField $field): string => $field->property, DeclaredPersonalFields::of($class))));
    }
}
