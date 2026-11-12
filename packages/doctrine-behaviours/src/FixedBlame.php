<?php

declare(strict_types=1);

namespace Phil\DoctrineBehaviours;

use Phil\DoctrineBehaviours\Contract\BlameResolver;

/**
 * Always the same one, or nobody: a command that acts for a known account, a test.
 */
final readonly class FixedBlame implements BlameResolver
{
    public function __construct(
        private ?string $who = null,
    ) {
    }

    public function current(): ?string
    {
        return $this->who;
    }
}
