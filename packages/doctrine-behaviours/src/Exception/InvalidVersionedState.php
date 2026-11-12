<?php

declare(strict_types=1);

namespace Phil\DoctrineBehaviours\Exception;

use LogicException;

/**
 * A version keeps mapped fields, the only changes that can fork it.
 */
final class InvalidVersionedState extends LogicException
{
    public static function unmapped(string $class, string $field): self
    {
        return new self(\sprintf('%s::versionedState() names "%s", which the entity does not map: a version keeps mapped fields and associations, by their names.', $class, $field));
    }
}
