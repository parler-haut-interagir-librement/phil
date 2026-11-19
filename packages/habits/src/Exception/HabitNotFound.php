<?php

declare(strict_types=1);

namespace Phil\Habits\Exception;

use RuntimeException;

/**
 * No habit is kept under an identifier.
 */
final class HabitNotFound extends RuntimeException implements HabitsException
{
    public static function withId(string $id): self
    {
        return new self(\sprintf('No habit is kept under the identifier "%s".', $id));
    }
}
