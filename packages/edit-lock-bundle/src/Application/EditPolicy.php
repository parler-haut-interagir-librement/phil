<?php

declare(strict_types=1);

namespace Phil\EditLockBundle\Application;

use Phil\EditLockBundle\Domain\Model\Lease;
use Phil\EditLockBundle\Domain\ValueObject\Holder;

/**
 * The application's rules: who may edit a resource — and so lease it, see who else edits it and save its blocks —,
 * and who may take a lease over from its holder, an owner or an administrator for instance.
 */
interface EditPolicy
{
    public function mayEdit(Holder $holder, string $resource): bool;

    public function mayTakeOver(Holder $holder, Lease $lease): bool;
}
