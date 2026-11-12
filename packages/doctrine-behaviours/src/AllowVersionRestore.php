<?php

declare(strict_types=1);

namespace Phil\DoctrineBehaviours;

use Phil\DoctrineBehaviours\Contract\Versionable;
use Phil\DoctrineBehaviours\Contract\VersionRestoreGuard;

/**
 * Lets every record go back to a past version: for a command an operator runs, a test.
 */
final readonly class AllowVersionRestore implements VersionRestoreGuard
{
    public function check(Versionable $entity, int $number): void
    {
    }
}
