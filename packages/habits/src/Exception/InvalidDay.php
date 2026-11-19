<?php

declare(strict_types=1);

namespace Phil\Habits\Exception;

use InvalidArgumentException;

/**
 * A day that cannot be read: not written as YYYY-MM-DD, or not a day of the calendar.
 */
final class InvalidDay extends InvalidArgumentException implements HabitsException
{
    public static function because(string $reason): self
    {
        return new self($reason);
    }
}
