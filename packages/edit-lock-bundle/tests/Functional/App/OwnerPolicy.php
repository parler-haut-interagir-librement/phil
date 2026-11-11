<?php

declare(strict_types=1);

namespace Phil\EditLockBundle\Tests\Functional\App;

use Override;
use Phil\EditLockBundle\Application\EditPolicy;
use Phil\EditLockBundle\Domain\Model\Lease;
use Phil\EditLockBundle\Domain\ValueObject\Holder;

/**
 * Anyone but `eve` edits; `ada` owns every page and takes over.
 */
final readonly class OwnerPolicy implements EditPolicy
{
    #[Override]
    public function mayEdit(Holder $holder, string $resource): bool
    {
        return 'eve' !== $holder->id;
    }

    #[Override]
    public function mayTakeOver(Holder $holder, Lease $lease): bool
    {
        return 'ada' === $holder->id;
    }
}
