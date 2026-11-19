<?php

declare(strict_types=1);

namespace Phil\Assignment\Exception;

use InvalidArgumentException;

/**
 * A problem that cannot be solved as given: an identifier, a capacity, a choice or a constraint out of bounds or in
 * contradiction with another.
 */
final class InvalidProblem extends InvalidArgumentException implements AssignmentException
{
    public static function because(string $reason): self
    {
        return new self($reason);
    }
}
