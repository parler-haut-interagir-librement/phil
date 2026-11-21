<?php

declare(strict_types=1);

namespace Phil\ImportWizard\Profile;

use Phil\ImportWizard\Exception\InvalidProfile;

/**
 * A column whose value is the natural key of a record of another importable type, or of the same one: a person's
 * unit, a unit's parent. The record referred to is found in the same file, even further down, or among the records the
 * target already holds.
 */
final readonly class Reference
{
    public function __construct(public string $type)
    {
        if (1 !== preg_match(ImportProfile::TYPE_PATTERN, $type)) {
            throw InvalidProfile::because(\sprintf('The type "%s" must be lower-case letters, digits and hyphens.', $type));
        }
    }
}
