<?php

declare(strict_types=1);

namespace Phil\ReactionsBundle\Application\Contract;

use Phil\ReactionsBundle\Application\Exception\TooManyReactions;

/**
 * Holds back a member who changes reactions too fast.
 */
interface ReactionThrottle
{
    /**
     * @throws TooManyReactions when the member has to wait
     */
    public function consume(string $member): void;
}
