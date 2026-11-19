<?php

declare(strict_types=1);

namespace Phil\DoctrineBehaviours\Contract;

/**
 * Which mapped fields of a class hold personal information: what `Versions` keeps out of a frozen version, or seals,
 * and what `VersionEraser` takes out of the versions of a person erased.
 */
interface PersonalFields
{
    /**
     * The names of the fields, as `versionedState()` keys them; none when the class holds no personal information.
     *
     * @param class-string $class
     *
     * @return list<string>
     */
    public function of(string $class): array;
}
