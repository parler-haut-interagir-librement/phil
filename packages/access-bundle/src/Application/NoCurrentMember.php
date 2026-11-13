<?php

declare(strict_types=1);

namespace Phil\AccessBundle\Application;

use Override;
use Phil\AccessBundle\Application\Contract\CurrentMember;
use Phil\AccessBundle\Domain\ValueObject\MemberId;

/**
 * Nobody is signed in: every request is a visitor's. The default without Symfony Security.
 */
final readonly class NoCurrentMember implements CurrentMember
{
    #[Override]
    public function current(): ?MemberId
    {
        return null;
    }
}
