<?php

declare(strict_types=1);

namespace Phil\Habits\Exception;

use InvalidArgumentException;

/**
 * A schedule that cannot be read or built: an unknown rule, a count or an interval out of bounds.
 */
final class InvalidSchedule extends InvalidArgumentException implements HabitsException
{
    public static function because(string $reason): self
    {
        return new self($reason);
    }
}
