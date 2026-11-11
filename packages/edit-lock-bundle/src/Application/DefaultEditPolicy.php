<?php

declare(strict_types=1);

namespace Phil\EditLockBundle\Application;

use Override;
use Phil\EditLockBundle\Domain\Model\Lease;
use Phil\EditLockBundle\Domain\ValueObject\Holder;

/**
 * The policy of `EditLocks` used from the application's own code when it configures none: anyone may edit, nobody
 * may take a lease over. The endpoints are never served on it: they need the application's policy.
 */
final readonly class DefaultEditPolicy implements EditPolicy
{
    #[Override]
    public function mayEdit(Holder $holder, string $resource): bool
    {
        return true;
    }

    #[Override]
    public function mayTakeOver(Holder $holder, Lease $lease): bool
    {
        return false;
    }
}
