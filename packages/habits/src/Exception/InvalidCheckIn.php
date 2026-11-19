<?php

declare(strict_types=1);

namespace Phil\Habits\Exception;

use InvalidArgumentException;

/**
 * A check-in that cannot be recorded: a day outside the habit's days, a value its status does not take, an archived habit.
 */
final class InvalidCheckIn extends InvalidArgumentException implements HabitsException
{
    public static function because(string $reason): self
    {
        return new self($reason);
    }
}
