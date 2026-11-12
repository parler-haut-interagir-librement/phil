<?php

declare(strict_types=1);

namespace Phil\DoctrineBehaviours;

use Phil\DoctrineBehaviours\Contract\Versionable;
use Phil\DoctrineBehaviours\Contract\VersionRestoreGuard;
use Phil\DoctrineBehaviours\Exception\VersionRestoreDenied;

/**
 * Lets no record go back to a past version: where no one can be asked for the right.
 */
final readonly class RefuseVersionRestore implements VersionRestoreGuard
{
    public function check(Versionable $entity, int $number): void
    {
        throw VersionRestoreDenied::of($entity::class, $entity->versionSubjectId(), $number);
    }
}
