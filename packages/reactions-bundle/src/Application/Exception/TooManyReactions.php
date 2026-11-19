<?php

declare(strict_types=1);

namespace Phil\ReactionsBundle\Application\Exception;

use Phil\ReactionsBundle\Domain\Exception\ReactionsException;
use RuntimeException;

/**
 * A member changes reactions too fast, and can try again after some seconds.
 */
final class TooManyReactions extends RuntimeException implements ReactionsException
{
    public function __construct(
        public readonly int $retryAfter,
    ) {
        parent::__construct(\sprintf('Too many reactions: try again in %d seconds.', $retryAfter));
    }
}
