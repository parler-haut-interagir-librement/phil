<?php

declare(strict_types=1);

namespace Phil\ReactionsBundle\Application;

use Override;
use Phil\ReactionsBundle\Application\Contract\ReactionAccess;

/**
 * What stands in when no Symfony Security is there and the application names no access: nobody may react.
 */
final readonly class DenyingReactionAccess implements ReactionAccess
{
    #[Override]
    public function canReact(ReactionSubject $subject): bool
    {
        return false;
    }
}
