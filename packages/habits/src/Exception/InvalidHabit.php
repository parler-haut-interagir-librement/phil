<?php

declare(strict_types=1);

namespace Phil\Habits\Exception;

use InvalidArgumentException;

/**
 * A habit that cannot be kept: an identifier, a name, a target or a day out of bounds.
 */
final class InvalidHabit extends InvalidArgumentException implements HabitsException
{
    public static function because(string $reason): self
    {
        return new self($reason);
    }
}
