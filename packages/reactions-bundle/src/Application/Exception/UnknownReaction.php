<?php

declare(strict_types=1);

namespace Phil\ReactionsBundle\Application\Exception;

use Phil\ReactionsBundle\Domain\Exception\ReactionsException;
use RuntimeException;

/**
 * The code is not one the site offers.
 */
final class UnknownReaction extends RuntimeException implements ReactionsException
{
    public static function code(string $code): self
    {
        return new self(\sprintf('The site offers no reaction "%s".', $code));
    }
}
