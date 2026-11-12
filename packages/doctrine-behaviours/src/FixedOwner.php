<?php

declare(strict_types=1);

namespace Phil\DoctrineBehaviours;

use Phil\DoctrineBehaviours\Contract\OwnerResolver;

/**
 * Always the same owner, or nobody: a command that acts for a known account, a test.
 */
final readonly class FixedOwner implements OwnerResolver
{
    public function __construct(
        private ?OwnerId $owner = null,
    ) {
    }

    public function current(): ?OwnerId
    {
        return $this->owner;
    }
}
