<?php

declare(strict_types=1);

namespace Phil\ModerationBundle\Application;

use Override;
use Phil\ModerationBundle\Application\Contract\CurrentMember;
use Phil\ModerationBundle\Application\Contract\CurrentModerator;
use Phil\ModerationBundle\Domain\ValueObject\MemberId;

/**
 * Nobody is signed in: the default without Symfony Security.
 */
final readonly class NoCurrentMember implements CurrentMember, CurrentModerator
{
    #[Override]
    public function member(): ?MemberId
    {
        return null;
    }

    #[Override]
    public function moderator(): ?Moderator
    {
        return null;
    }
}
