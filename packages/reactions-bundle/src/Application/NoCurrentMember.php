<?php

declare(strict_types=1);

namespace Phil\ReactionsBundle\Application;

use Override;
use Phil\ReactionsBundle\Application\Contract\CurrentMember;

/**
 * What stands in when no Symfony Security is there and the application names no member: every viewer is a visitor.
 */
final readonly class NoCurrentMember implements CurrentMember
{
    #[Override]
    public function current(): ?string
    {
        return null;
    }
}
