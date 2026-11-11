<?php

declare(strict_types=1);

namespace Phil\Poll\Exception;

use InvalidArgumentException;

/**
 * A ballot does not follow the rules of its poll: a choice that is not one of its options, a choice given twice, too
 * few or too many choices, a value that is not an option's identifier.
 */
final class InvalidBallot extends InvalidArgumentException implements PollException
{
    public static function because(string $reason): self
    {
        return new self($reason);
    }
}
