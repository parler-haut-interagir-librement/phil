<?php

declare(strict_types=1);

namespace Phil\DoctrineBehaviours\Exception;

use LogicException;

/**
 * A version keeps mapped fields, the only changes that can fork it; a sealed field takes a cipher to seal and open.
 */
final class InvalidVersionedState extends LogicException
{
    public static function unmapped(string $class, string $field): self
    {
        return new self(\sprintf('%s::versionedState() names "%s", which the entity does not map: a version keeps mapped fields and associations, by their names.', $class, $field));
    }

    public static function noCipher(): self
    {
        return new self('PersonalFieldPolicy::Encrypt seals the personal fields of the versions with a VersionCipher, and none was given.');
    }

    public static function sealed(string $class, string $field): self
    {
        return new self(\sprintf('A version of %s holds "%s" sealed, and no VersionCipher was given to open it.', $class, $field));
    }
}
