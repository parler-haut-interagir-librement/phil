<?php

declare(strict_types=1);

namespace Phil\FollowBundle\Application;

use Phil\FollowBundle\Domain\Model\Follow;
use Phil\FollowBundle\Domain\Status\FollowLevel;

/**
 * Where a member stands with a subject: their own follow, else the follow of the nearest parent it inherits from, and
 * whether a request of theirs waits for its approver.
 */
final readonly class FollowState
{
    public function __construct(
        public ?Follow $own = null,
        public ?Follow $inherited = null,
        public bool $pending = false,
    ) {
    }

    /**
     * The level that applies: the member's own, else the inherited one, else none.
     */
    public function level(): ?FollowLevel
    {
        return ($this->own ?? $this->inherited)?->level();
    }

    public function follows(): bool
    {
        return true === $this->level()?->follows();
    }
}
