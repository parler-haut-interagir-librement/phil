<?php

declare(strict_types=1);

namespace Phil\ReactionsBundle\Application\Contract;

use Phil\ReactionsBundle\Domain\ValueObject\ReactionGroup;

/**
 * A labelled source of reactions: the Unicode emojis and the emojis of the site declared in the configuration, or what
 * an application adds, read from its own storage. The group is asked once per request.
 */
interface ReactionSource
{
    public function group(): ReactionGroup;
}
