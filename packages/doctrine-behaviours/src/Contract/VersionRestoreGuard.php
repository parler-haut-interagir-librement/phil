<?php

declare(strict_types=1);

namespace Phil\DoctrineBehaviours\Contract;

use Phil\DoctrineBehaviours\Exception\VersionRestoreDenied;

/**
 * Whether a record may go back to a past version: a right of its own, apart from the right to edit the record.
 */
interface VersionRestoreGuard
{
    /**
     * @throws VersionRestoreDenied or an exception of the application's own, when the right is missing
     */
    public function check(Versionable $entity, int $number): void;
}
