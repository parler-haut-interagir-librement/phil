<?php

declare(strict_types=1);

namespace Phil\ReactionsBundle\Application\Contract;

use Phil\ReactionsBundle\Application\ReactionSubject;

/**
 * Whether a member may use a reaction on a content. A member may always take back the reaction they have.
 */
interface ReactionAccess
{
    public function canReact(ReactionSubject $subject): bool;
}
