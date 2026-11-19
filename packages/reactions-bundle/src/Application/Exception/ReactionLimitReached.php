<?php

declare(strict_types=1);

namespace Phil\ReactionsBundle\Application\Exception;

use Phil\ReactionsBundle\Domain\Exception\ReactionsException;
use RuntimeException;

/**
 * A member already gave a content as many reactions as the site allows; taking one back makes room for another.
 */
final class ReactionLimitReached extends RuntimeException implements ReactionsException
{
    public static function of(int $cap): self
    {
        return new self(\sprintf('A member gives at most %d reactions to a content.', $cap));
    }
}
