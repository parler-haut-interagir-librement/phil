<?php

declare(strict_types=1);

namespace Phil\DoctrineBehaviours;

/**
 * What a frozen version keeps of the personal fields of its record.
 */
enum PersonalFieldPolicy: string
{
    /**
     * Nothing: the version leaves them out, and going back keeps the values the record holds. The default.
     */
    case Exclude = 'exclude';

    /**
     * Their values sealed by a `VersionCipher`, opened as the record goes back to the version.
     */
    case Encrypt = 'encrypt';

    /**
     * Their values as they are, until `VersionEraser` takes them out of the versions of a person erased.
     */
    case Keep = 'keep';
}
