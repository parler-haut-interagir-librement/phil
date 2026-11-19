<?php

declare(strict_types=1);

namespace Phil\DoctrineBehaviours;

use Override;
use Phil\DoctrineBehaviours\Contract\PersonalFields;

/**
 * No class holds personal information: the versions keep what `versionedState()` gives.
 */
final readonly class NoPersonalFields implements PersonalFields
{
    #[Override]
    public function of(string $class): array
    {
        return [];
    }
}
